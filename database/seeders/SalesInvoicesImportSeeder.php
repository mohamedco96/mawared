<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesInvoicesImportSeeder extends Seeder
{
    private array $productMap = [];
    private array $customerMap = [];
    private ?string $defaultWarehouseId = null;
    private ?string $defaultUserId = null;
    private ?string $walkInCustomerId = null;

    private int $processedInvoices = 0;
    private int $skippedInvoices = 0;
    private int $totalItems = 0;
    private int $totalDiscounts = 0;
    private int $missingProducts = 0;
    private array $missingProductNames = [];

    public function run(): void
    {
        $this->command->info('🚀 Starting Sales Invoices Import...');
        $startTime = microtime(true);

        // Initialize lookup maps and defaults
        $this->initializeMaps();

        // Pre-load grouped data from CSVs
        $this->command->info('📊 Loading and grouping CSV data...');
        $itemsGrouped = $this->loadAndGroupItems();
        $discountsGrouped = $this->loadAndGroupDiscounts();

        // Process headers and create invoices
        $this->command->info('💾 Processing invoice headers...');
        $this->processInvoiceHeaders($itemsGrouped, $discountsGrouped);

        // Display summary
        $duration = round(microtime(true) - $startTime, 2);
        $this->command->newLine();
        $this->command->info('✅ Sales Invoices Import Complete!');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Invoices Processed', number_format($this->processedInvoices)],
                ['Invoices Skipped', number_format($this->skippedInvoices)],
                ['Invoice Items Created', number_format($this->totalItems)],
                ['Discounts Applied', number_format($this->totalDiscounts)],
                ['Missing Products (items skipped)', number_format($this->missingProducts)],
                ['Duration', "{$duration}s"],
                ['Avg Speed', number_format($this->processedInvoices / max($duration, 1), 2) . ' invoices/sec'],
            ]
        );

        // Show sample missing products
        if (!empty($this->missingProductNames)) {
            $this->command->newLine();
            $this->command->warn('⚠️  Sample Missing Products (first 20):');
            foreach ($this->missingProductNames as $name) {
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

        // Load customers into memory (name => id) - including 'unknown' type
        $this->customerMap = Partner::whereIn('type', ['customer', 'unknown'])
            ->pluck('id', 'name')
            ->toArray();
        $this->command->info('   ✓ Customers: ' . number_format(count($this->customerMap)));

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

        // Create or get "Walk-in Customer" for unknown customers
        $walkInCustomer = Partner::firstOrCreate(
            ['name' => 'عميل نقدي'],
            [
                'type' => 'customer',
                'phone' => null,
                'current_balance' => 0,
                'opening_balance' => 0,
            ]
        );
        $this->walkInCustomerId = $walkInCustomer->id;
        $this->command->info('   ✓ Walk-in customer ID: ' . $this->walkInCustomerId);
    }

    /**
     * Load and group items by invoice number (no_s)
     * Returns: ['invoice_no' => [item1, item2, ...]]
     */
    private function loadAndGroupItems(): array
    {
        $csvPath = database_path('seeders/data/sales_items.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("❌ File not found: {$csvPath}");
            return [];
        }

        $grouped = [];

        // Read entire file (already UTF-8)
        $content = file_get_contents($csvPath);

        // Parse CSV from string
        $lines = str_getcsv($content, "\n");
        $lineCount = 0;

        foreach ($lines as $index => $line) {
            // Skip header
            if ($index === 0) {
                continue;
            }

            $row = str_getcsv($line);
            $lineCount++;

            // Extract relevant columns
            $invoiceNo = isset($row[6]) ? (string) intval(trim($row[6])) : null;
            $productName = isset($row[9]) ? trim($row[9]) : null;
            $quantity = isset($row[10]) ? floatval($row[10]) : 0;
            $unitPrice = isset($row[11]) ? floatval($row[11]) : 0;

            if (!$invoiceNo || !$productName || $quantity <= 0) {
                continue;
            }

            if (!isset($grouped[$invoiceNo])) {
                $grouped[$invoiceNo] = [];
            }

            $grouped[$invoiceNo][] = [
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        $this->command->info('   ✓ Items loaded: ' . number_format($lineCount) . ' rows, ' . number_format(count($grouped)) . ' invoices');

        return $grouped;
    }

    /**
     * Load and group discounts by invoice number (no_s)
     * Returns: ['invoice_no' => total_discount]
     */
    private function loadAndGroupDiscounts(): array
    {
        $csvPath = database_path('seeders/data/sales_discounts.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("⚠️  File not found: {$csvPath}");
            return [];
        }

        $grouped = [];

        // Read entire file (already UTF-8)
        $content = file_get_contents($csvPath);

        // Parse CSV from string
        $lines = str_getcsv($content, "\n");
        $lineCount = 0;

        foreach ($lines as $index => $line) {
            // Skip header
            if ($index === 0) {
                continue;
            }

            $row = str_getcsv($line);
            $lineCount++;

            $invoiceNo = isset($row[3]) ? (string) intval(trim($row[3])) : null;
            $discountAmount = isset($row[6]) ? floatval($row[6]) : 0;

            if (!$invoiceNo || $discountAmount <= 0) {
                continue;
            }

            // Accumulate discounts for the same invoice (if multiple discount records)
            if (!isset($grouped[$invoiceNo])) {
                $grouped[$invoiceNo] = 0;
            }
            $grouped[$invoiceNo] += $discountAmount;
        }

        $this->command->info('   ✓ Discounts loaded: ' . number_format($lineCount) . ' rows, ' . number_format(count($grouped)) . ' invoices with discounts');

        return $grouped;
    }

    /**
     * Process invoice headers and create invoices with items
     */
    private function processInvoiceHeaders(array $itemsGrouped, array $discountsGrouped): void
    {
        $csvPath = database_path('seeders/data/sales_header.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("❌ File not found: {$csvPath}");
            return;
        }

        // Read entire file (already UTF-8)
        $content = file_get_contents($csvPath);

        // Parse CSV from string
        $lines = str_getcsv($content, "\n");

        $batchInvoices = [];
        $batchCount = 0;
        $batchSize = 100;

        $progressBar = $this->command->getOutput()->createProgressBar();
        $progressBar->start();

        foreach ($lines as $index => $line) {
            // Skip header
            if ($index === 0) {
                continue;
            }

            $row = str_getcsv($line);
            // Extract header data
            // Columns: autono,kst,to,da_s,no_r,new,no_s,kind,no_cb,cb,respon,qu_s,stor,cashstor,kind2,da_st,mandob,no_i,kindtel,kindtel2,exported
            $invoiceNo = isset($row[6]) ? (string) intval(trim($row[6])) : null;
            $customerName = isset($row[2]) ? trim($row[2]) : null;
            $dateStr = isset($row[3]) ? trim($row[3]) : null;
            $finalTotal = isset($row[1]) ? floatval($row[1]) : 0;
            $salesRep = isset($row[16]) ? trim($row[16]) : null;

            if (!$invoiceNo) {
                $this->skippedInvoices++;
                continue;
            }

            // Parse date (MM/DD/YY format)
            $date = $this->parseDate($dateStr);

            // Lookup customer
            $customerId = $this->customerMap[$customerName] ?? $this->walkInCustomerId;

            // Get items for this invoice
            $items = $itemsGrouped[$invoiceNo] ?? [];

            if (empty($items)) {
                $this->skippedInvoices++;
                continue; // Skip invoices without items
            }

            // Get discount for this invoice
            $discount = $discountsGrouped[$invoiceNo] ?? 0;

            // Calculate subtotal from items
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            // Total should be subtotal - discount
            $total = max(0, $subtotal - $discount);

            // Build invoice data
            $invoiceData = [
                'invoice_no' => $invoiceNo,
                'customer_id' => $customerId,
                'date' => $date,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'items' => $items,
                'sales_rep' => $salesRep,
            ];

            $batchInvoices[] = $invoiceData;
            $batchCount++;

            // Process batch when it reaches batch size
            if ($batchCount >= $batchSize) {
                $this->processBatch($batchInvoices);
                $batchInvoices = [];
                $batchCount = 0;
                $progressBar->advance($batchSize);
            }
        }

        // Process remaining invoices
        if (!empty($batchInvoices)) {
            $this->processBatch($batchInvoices);
            $progressBar->advance(count($batchInvoices));
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    /**
     * Process a batch of invoices in a transaction
     */
    private function processBatch(array $invoices): void
    {
        DB::transaction(function () use ($invoices) {
            foreach ($invoices as $invoiceData) {
                $this->createInvoice($invoiceData);
            }
        });
    }

    /**
     * Create a single invoice with its items
     */
    private function createInvoice(array $data): void
    {
        // Check if invoice already exists (idempotency)
        if (SalesInvoice::where('invoice_number', $data['invoice_no'])->exists()) {
            $this->skippedInvoices++;
            return;
        }

        // Build notes
        $notes = [];
        if (!empty($data['sales_rep'])) {
            $notes[] = "مندوب: {$data['sales_rep']}";
        }
        $notes[] = 'تم الاستيراد من النظام القديم';

        // Create invoice
        $invoice = SalesInvoice::create([
            'invoice_number' => $data['invoice_no'],
            'warehouse_id' => $this->defaultWarehouseId,
            'partner_id' => $data['customer_id'],
            'status' => 'posted', // Imported invoices are considered posted
            'payment_method' => 'credit', // Default to credit (can be adjusted)
            'discount_type' => 'fixed',
            'discount_value' => $data['discount'],
            'subtotal' => $data['subtotal'],
            'discount' => $data['discount'],
            'total' => $data['total'],
            'paid_amount' => 0, // No payment info in source (will be handled separately)
            'remaining_amount' => $data['total'],
            'notes' => implode(' | ', $notes),
            'created_by' => $this->defaultUserId,
            'created_at' => $data['date'],
            'updated_at' => $data['date'],
        ]);

        // Create invoice items
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
                if (count($this->missingProductNames) < 20) {
                    $this->missingProductNames[] = $productName;
                }
                continue;
            }

            $itemTotal = $item['quantity'] * $item['unit_price'];

            SalesInvoiceItem::create([
                'sales_invoice_id' => $invoice->id,
                'product_id' => $productId,
                'unit_type' => 'small', // Default to small unit
                'quantity' => (int) $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => 0, // Item-level discounts not in source data
                'total' => $itemTotal,
            ]);

            $itemsCreated++;
        }

        $this->processedInvoices++;
        $this->totalItems += $itemsCreated;
        if ($data['discount'] > 0) {
            $this->totalDiscounts++;
        }
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
            // Try MM/DD/YY format
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
