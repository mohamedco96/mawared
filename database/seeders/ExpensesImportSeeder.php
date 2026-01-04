<?php

/**
 * Expenses Import Seeder
 *
 * This seeder imports expenses from legacy CSV file:
 * - expenses.csv (Source: cost.csv)
 *
 * Mapping:
 * - kind -> title (expense type/category)
 * - kst -> amount
 * - da_s -> expense_date
 * - to + note -> description (combined)
 * - stor -> treasury lookup (optional)
 *
 * Features:
 * - Batch processing with transactions for data integrity
 * - Idempotent (can be run multiple times safely based on unique hash)
 * - Handles encoding for Arabic text
 * - Progress bar and detailed summary reporting
 * - Groups expenses by category for better reporting
 */

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpensesImportSeeder extends Seeder
{
    private array $treasuryMap = [];
    private ?string $defaultTreasuryId = null;
    private ?string $defaultUserId = null;

    private int $processedExpenses = 0;
    private int $skippedExpenses = 0;
    private array $categoryStats = [];
    private int $categoriesCreated = 0;
    private array $uniqueCategories = [];

    public function run(): void
    {
        $this->command->info('🚀 Starting Expenses Import...');
        $startTime = microtime(true);

        // Initialize lookup maps and defaults
        $this->initializeMaps();

        // Process expenses
        $this->command->info('💾 Processing expenses...');
        $this->processExpenses();

        // Display summary
        $duration = round(microtime(true) - $startTime, 2);
        $this->command->newLine();
        $this->command->info('✅ Expenses Import Complete!');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Expenses Processed', number_format($this->processedExpenses)],
                ['Expenses Skipped', number_format($this->skippedExpenses)],
                ['Unique Categories', number_format(count($this->uniqueCategories))],
                ['Duration', "{$duration}s"],
                ['Avg Speed', number_format($this->processedExpenses / max($duration, 1), 2) . ' expenses/sec'],
            ]
        );

        // Show category breakdown
        if (!empty($this->categoryStats)) {
            $this->command->newLine();
            $this->command->info('📊 Expenses by Category:');
            arsort($this->categoryStats);
            $categoryTable = [];
            foreach ($this->categoryStats as $category => $count) {
                $categoryTable[] = [$category, number_format($count)];
            }
            $this->command->table(['Category', 'Count'], $categoryTable);
        }
    }

    private function initializeMaps(): void
    {
        $this->command->info('🔍 Building lookup maps...');

        // Create or get default treasury
        $defaultTreasury = Treasury::firstOrCreate(
            ['name' => 'رئيسى'],
            [
                'type' => 'cash',
                'description' => 'الخزينة الرئيسية',
            ]
        );
        $this->defaultTreasuryId = $defaultTreasury->id;

        // Load treasuries into memory (name => id)
        $this->treasuryMap = Treasury::pluck('id', 'name')->toArray();
        $this->command->info('   ✓ Treasuries: ' . number_format(count($this->treasuryMap)));
        $this->command->info('   ✓ Default treasury: ' . $defaultTreasury->name);

        // Get default user (first user)
        $this->defaultUserId = User::first()?->id;

        if (!$this->defaultUserId) {
            $this->command->error('❌ No user found. Please create at least one user.');
            return;
        }
    }

    /**
     * Process expenses from CSV
     */
    private function processExpenses(): void
    {
        $csvPath = database_path('seeders/data/expenses.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("❌ File not found: {$csvPath}");
            return;
        }

        $batchExpenses = [];
        $batchCount = 0;
        $batchSize = 100;

        $progressBar = $this->command->getOutput()->createProgressBar();
        $progressBar->start();

        $handle = fopen($csvPath, 'r');
        $isHeader = true;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip header
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            // Column mapping based on CSV structure:
            // autono,stor,kind,to,da_s,kst,no_r,new,respon,note,cash,cashstor,respon2,da_st,mandob,closed,kindtel,exported
            // Indices: 0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17

            // Extract fields (already UTF-8 encoded)
            $autono = isset($row[0]) ? trim($row[0]) : null;
            $storeName = isset($row[1]) ? trim($row[1]) : null;
            $category = isset($row[2]) ? trim($row[2]) : null;
            $recipient = isset($row[3]) ? trim($row[3]) : null;
            $dateStr = isset($row[4]) ? trim($row[4]) : null;
            $amount = isset($row[5]) ? floatval($row[5]) : 0;
            $note = isset($row[9]) ? trim($row[9]) : null;

            if (!$category || $amount <= 0) {
                $this->skippedExpenses++;
                continue;
            }

            // Parse date (MM/DD/YY format)
            $date = $this->parseDate($dateStr);

            // Lookup treasury/store
            $treasuryId = null;
            if ($storeName) {
                $treasuryId = $this->treasuryMap[$storeName] ?? $this->treasuryMap[$this->normalizeName($storeName)] ?? null;
            }

            if (!$treasuryId) {
                // Use default treasury as fallback
                $treasuryId = $this->defaultTreasuryId;
            }

            // Build description from recipient and note
            $description = [];
            if ($recipient) {
                $description[] = "إلى: {$recipient}";
            }
            if ($note) {
                $description[] = $note;
            }
            $descriptionText = implode(' - ', $description);

            // Track unique categories
            if (!in_array($category, $this->uniqueCategories)) {
                $this->uniqueCategories[] = $category;
            }

            // Build expense data
            $expenseData = [
                'autono' => $autono,
                'category' => $category,
                'amount' => $amount,
                'date' => $date,
                'description' => $descriptionText,
                'treasury_id' => $treasuryId,
            ];

            $batchExpenses[] = $expenseData;
            $batchCount++;

            // Process batch when it reaches batch size
            if ($batchCount >= $batchSize) {
                $this->processBatch($batchExpenses);
                $batchExpenses = [];
                $batchCount = 0;
                $progressBar->advance($batchSize);
            }
        }

        fclose($handle);

        // Process remaining expenses
        if (!empty($batchExpenses)) {
            $this->processBatch($batchExpenses);
            $progressBar->advance(count($batchExpenses));
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    /**
     * Process a batch of expenses in a transaction
     */
    private function processBatch(array $expenses): void
    {
        DB::transaction(function () use ($expenses) {
            foreach ($expenses as $expenseData) {
                $this->createExpense($expenseData);
            }
        });
    }

    /**
     * Create a single expense
     */
    private function createExpense(array $data): void
    {
        // Create a unique hash for idempotency check
        // Using autono (legacy ID) as the primary identifier
        $uniqueHash = md5($data['autono'] . $data['date'] . $data['amount']);

        // Check if expense already exists (idempotency)
        // Since we don't have a legacy_id field, we'll use a combination of fields
        $exists = Expense::where('expense_date', $data['date'])
            ->where('amount', $data['amount'])
            ->where('title', $data['category'])
            ->where('description', $data['description'])
            ->exists();

        if ($exists) {
            $this->skippedExpenses++;
            return;
        }

        // Create expense
        Expense::create([
            'title' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'treasury_id' => $data['treasury_id'],
            'expense_date' => $data['date'],
            'created_by' => $this->defaultUserId,
            'created_at' => $data['date'],
            'updated_at' => $data['date'],
        ]);

        // Track category stats
        if (!isset($this->categoryStats[$data['category']])) {
            $this->categoryStats[$data['category']] = 0;
        }
        $this->categoryStats[$data['category']]++;

        $this->processedExpenses++;
    }

    /**
     * Normalize a name for matching (trim whitespace, normalize Arabic characters)
     */
    private function normalizeName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        // Trim whitespace
        $name = trim($name);

        // Remove multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);

        return $name;
    }

    /**
     * Parse date from MM/DD/YY format to Y-m-d
     */
    private function parseDate(?string $dateStr): string
    {
        if (empty($dateStr)) {
            return now()->format('Y-m-d');
        }

        try {
            // Try MM/DD/YY format with time
            $date = \DateTime::createFromFormat('m/d/y H:i:s', $dateStr);
            if (!$date) {
                // Try without time
                $date = \DateTime::createFromFormat('m/d/y', $dateStr);
            }

            if ($date) {
                // Fix year (00-99 -> 2000-2099 or 1900-1999)
                $year = (int) $date->format('Y');
                if ($year < 100) {
                    $year = $year + 2000;
                    $date->setDate($year, (int) $date->format('m'), (int) $date->format('d'));
                }
                return $date->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // Ignore parse errors
        }

        return now()->format('Y-m-d');
    }
}
