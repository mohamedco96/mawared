<?php

/**
 * Purchase Returns Import Seeder
 *
 * This seeder imports purchase returns from legacy CSV files:
 * - purchase_return_headers.csv (Source: seqr.csv from invo_w)
 * - purchase_return_items.csv (Source: return.csv from invo_w)
 *
 * Mapping:
 * - Header: no_s -> invoice_number (prefixed with 'PR-'), to -> partner_id, da_s -> date, kst -> total
 * - Items: name -> product_id, qu_s -> quantity, pr_s -> unit_price
 *
 * Features:
 * - Memory caching for Products and Partners for optimal performance
 * - Batch processing with transactions for data integrity
 * - Idempotent (can be run multiple times safely)
 * - Handles missing products/suppliers gracefully with fallbacks
 * - Progress bar and detailed summary reporting
 * - All returns are marked as 'posted' status
 */

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseReturnsImportSeeder extends Seeder
{
    private array $productMap = [];
    private array $supplierMap = [];
    private ?string $defaultWarehouseId = null;
    private ?string $defaultUserId = null;
    private ?string $defaultSupplierId = null;

    private int $processedReturns = 0;
    private int $skippedReturns = 0;
    private int $totalItems = 0;
    private int $missingProducts = 0;
    private int $missingSuppliers = 0;
    private array $missingProductNames = [];
    private array $missingSupplierNames = [];

    public function run(): void
    {
        $this->command->info('🚀 Starting Purchase Returns Import...');
        $startTime = microtime(true);

        // Initialize lookup maps and defaults
        $this->initializeMaps();

        // Pre-load grouped data from CSVs
        $this->command->info('📊 Loading and grouping CSV data...');
        $itemsGrouped = $this->loadAndGroupItems();

        // Process headers and create returns
        $this->command->info('💾 Processing purchase return headers...');
        $this->processReturnHeaders($itemsGrouped);

        // Display summary
        $duration = round(microtime(true) - $startTime, 2);
        $this->command->newLine();
        $this->command->info('✅ Purchase Returns Import Complete!');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Returns Processed', number_format($this->processedReturns)],
                ['Returns Skipped', number_format($this->skippedReturns)],
                ['Return Items Created', number_format($this->totalItems)],
                ['Missing Products (items skipped)', number_format($this->missingProducts)],
                ['Missing Suppliers', number_format($this->missingSuppliers)],
                ['Duration', "{$duration}s"],
                ['Avg Speed', number_format($this->processedReturns / max($duration, 1), 2) . ' returns/sec'],
            ]
        );

        // Show sample missing products
        if (!empty($this->missingProductNames)) {
            $this->command->newLine();
            $this->command->warn('⚠️  Sample Missing Products (first 20):');
            foreach (array_slice($this->missingProductNames, 0, 20) as $name) {
                $this->command->line('   - ' . $name);
            }
        }

        // Show sample missing suppliers
        if (!empty($this->missingSupplierNames)) {
            $this->command->newLine();
            $this->command->warn('⚠️  Sample Missing Suppliers (first 20):');
            foreach (array_slice($this->missingSupplierNames, 0, 20) as $name) {
                $this->command->line('   - ' . $name);
            }
        }
    }

    private function initializeMaps(): void
    {
        $this->command->info('🔍 Building lookup maps...');

        // Load products into memory (name => id) - normalize names for better matching
        $products = Product::all(['id', 'name']);
        foreach ($products as $product) {
            // Store with original name and also a normalized version
            $this->productMap[$product->name] = $product->id;
            // Also store with trimmed and normalized name
            $normalizedName = $this->normalizeName($product->name);
            if ($normalizedName !== $product->name) {
                $this->productMap[$normalizedName] = $product->id;
            }
        }
        $this->command->info('   ✓ Products: ' . number_format(count($products)));

        // Load suppliers into memory (name => id) - including 'unknown' type
        $this->supplierMap = Partner::whereIn('type', ['supplier', 'unknown'])
            ->pluck('id', 'name')
            ->toArray();
        $this->command->info('   ✓ Suppliers: ' . number_format(count($this->supplierMap)));

        // Get default warehouse (first active warehouse)
        $this->defaultWarehouseId = Warehouse::where('is_active', true)->first()?->id
            ?? Warehouse::first()?->id;

        if (!$this->defaultWarehouseId) {
            $this->command->error('❌ No warehouse found. Please create at least one warehouse.');
            return;
        }

        // Get default user (first user)
        $this->defaultUserId = User::first()?->id;

        if (!$this->defaultUserId) {
            $this->command->error('❌ No user found. Please create at least one user.');
            return;
        }

        // Create or get default supplier for unknown suppliers
        $defaultSupplier = Partner::firstOrCreate(
            ['name' => 'مورد غير معروف'],
            [
                'type' => 'supplier',
                'phone' => null,
                'current_balance' => 0,
                'opening_balance' => 0,
            ]
        );
        $this->defaultSupplierId = $defaultSupplier->id;
        $this->command->info('   ✓ Default supplier ID: ' . $this->defaultSupplierId);
    }

    /**
     * Load and group items by return number (no_s)
     * Returns: ['return_no' => [item1, item2, ...]]
     */
    private function loadAndGroupItems(): array
    {
        $csvPath = database_path('seeders/data/purchase_return_items.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("❌ File not found: {$csvPath}");
            return [];
        }

        $grouped = [];

        // Read file with Windows-1256 encoding
        $handle = fopen($csvPath, 'r');
        $lineCount = 0;
        $isHeader = true;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip header
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            $lineCount++;

            // Column mapping based on CSV structure:
            // autono,stor,kind,color,farz,unit,no_s,da_s,to,name,qu_s,pr_s,...
            // Indices: 0,1,2,3,4,5,6,7,8,9,10,11,...

            // Extract fields (already UTF-8 encoded)
            $returnNo = isset($row[6]) ? $this->cleanScientificNotation(trim($row[6])) : null;
            $productName = isset($row[9]) ? trim($row[9]) : null;
            $quantity = isset($row[10]) ? floatval($row[10]) : 0;
            $unitPrice = isset($row[11]) ? floatval($row[11]) : 0;

            if (!$returnNo || !$productName || $quantity <= 0) {
                continue;
            }

            if (!isset($grouped[$returnNo])) {
                $grouped[$returnNo] = [];
            }

            $grouped[$returnNo][] = [
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        fclose($handle);

        $this->command->info('   ✓ Items loaded: ' . number_format($lineCount) . ' rows, ' . number_format(count($grouped)) . ' returns');

        return $grouped;
    }

    /**
     * Process return headers and create returns with items
     */
    private function processReturnHeaders(array $itemsGrouped): void
    {
        $csvPath = database_path('seeders/data/purchase_return_headers.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("❌ File not found: {$csvPath}");
            return;
        }

        $batchReturns = [];
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
            // autono,to,da_s,kst,no_r,new,no_s,kind,no_cb,cb,respon,stor,cashstor,qu_s,kind2,da_st,mandob,no_i,kindtel,kindtel2,exported
            // Indices: 0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20

            // Extract fields (already UTF-8 encoded)
            $returnNo = isset($row[6]) ? $this->cleanScientificNotation(trim($row[6])) : null;
            $supplierName = isset($row[1]) ? trim($row[1]) : null;
            $dateStr = isset($row[2]) ? trim($row[2]) : null;
            $total = isset($row[3]) ? floatval($row[3]) : 0;

            if (!$returnNo) {
                $this->skippedReturns++;
                continue;
            }

            // Parse date (MM/DD/YY format)
            $date = $this->parseDate($dateStr);

            // Lookup supplier
            $supplierId = null;
            if ($supplierName) {
                $supplierId = $this->supplierMap[$supplierName] ?? $this->supplierMap[$this->normalizeName($supplierName)] ?? null;
            }

            if (!$supplierId) {
                // Track missing suppliers
                if ($supplierName && !in_array($supplierName, $this->missingSupplierNames)) {
                    $this->missingSupplierNames[] = $supplierName;
                    $this->missingSuppliers++;
                }
                // Use default supplier as fallback
                $supplierId = $this->defaultSupplierId;
            }

            // Get items for this return
            $items = $itemsGrouped[$returnNo] ?? [];

            if (empty($items)) {
                $this->skippedReturns++;
                continue; // Skip returns without items
            }

            // Calculate subtotal from items
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            // Build return data
            $returnData = [
                'return_no' => $returnNo,
                'supplier_id' => $supplierId,
                'date' => $date,
                'subtotal' => $subtotal,
                'total' => $total > 0 ? $total : $subtotal,
                'items' => $items,
            ];

            $batchReturns[] = $returnData;
            $batchCount++;

            // Process batch when it reaches batch size
            if ($batchCount >= $batchSize) {
                $this->processBatch($batchReturns);
                $batchReturns = [];
                $batchCount = 0;
                $progressBar->advance($batchSize);
            }
        }

        fclose($handle);

        // Process remaining returns
        if (!empty($batchReturns)) {
            $this->processBatch($batchReturns);
            $progressBar->advance(count($batchReturns));
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    /**
     * Process a batch of returns in a transaction
     */
    private function processBatch(array $returns): void
    {
        DB::transaction(function () use ($returns) {
            foreach ($returns as $returnData) {
                $this->createReturn($returnData);
            }
        });
    }

    /**
     * Create a single return with its items
     */
    private function createReturn(array $data): void
    {
        // Build return number with PR- prefix
        $returnNumber = 'PR-' . $data['return_no'];

        // Check if return already exists (idempotency)
        if (PurchaseReturn::where('return_number', $returnNumber)->exists()) {
            $this->skippedReturns++;
            return;
        }

        // Build notes
        $notes = ['تم الاستيراد من النظام القديم'];

        // Create purchase return
        $return = PurchaseReturn::create([
            'return_number' => $returnNumber,
            'warehouse_id' => $this->defaultWarehouseId,
            'partner_id' => $data['supplier_id'],
            'purchase_invoice_id' => null, // No direct link to invoice in source data
            'status' => 'posted', // Imported returns are considered posted
            'payment_method' => 'credit', // Default to credit
            'subtotal' => $data['subtotal'],
            'discount' => 0, // No discount info in source
            'total' => $data['total'],
            'notes' => implode(' | ', $notes),
            'created_by' => $this->defaultUserId,
            'created_at' => $data['date'],
            'updated_at' => $data['date'],
        ]);

        // Create return items
        $itemsCreated = 0;
        foreach ($data['items'] as $item) {
            // Try to find product with exact name first, then normalized
            $productName = $item['product_name'];
            $productId = $this->productMap[$productName] ?? null;

            if (!$productId) {
                // Try normalized name
                $normalizedName = $this->normalizeName($productName);
                $productId = $this->productMap[$normalizedName] ?? null;
            }

            if (!$productId) {
                // Track missing products
                $this->missingProducts++;
                if (!in_array($productName, $this->missingProductNames)) {
                    $this->missingProductNames[] = $productName;
                }
                continue;
            }

            $itemTotal = $item['quantity'] * $item['unit_price'];

            PurchaseReturnItem::create([
                'purchase_return_id' => $return->id,
                'product_id' => $productId,
                'unit_type' => 'small', // Default to small unit
                'quantity' => (int) $item['quantity'],
                'unit_cost' => $item['unit_price'],
                'discount' => 0, // Item-level discounts not in source data
                'total' => $itemTotal,
            ]);

            $itemsCreated++;
        }

        $this->processedReturns++;
        $this->totalItems += $itemsCreated;
    }

    /**
     * Clean scientific notation from CSV (e.g., 1.0e+00 -> 1)
     */
    private function cleanScientificNotation(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Convert to float then to int to handle scientific notation
        return (string) intval(floatval($value));
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
