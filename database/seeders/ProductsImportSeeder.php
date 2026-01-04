<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = database_path('seeders/data/main.csv');

        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: {$csvFile}");

            return;
        }

        $this->command->info('Starting Products Import from main.csv...');
        $this->command->info('Pre-loading lookup tables for performance...');

        // ============================================
        // STEP 1: Pre-load lookup tables into memory
        // ============================================

        // Categories: ['Name' => ID]
        $categories = ProductCategory::pluck('id', 'name')->toArray();
        $this->command->info('Loaded '.count($categories).' categories');

        // Suppliers: ['Name' => ID] (Filter by type='supplier' or 'unknown')
        $suppliers = Partner::whereIn('type', ['supplier', 'unknown'])
            ->pluck('id', 'name')
            ->toArray();
        $this->command->info('Loaded '.count($suppliers).' suppliers');

        // Warehouses: ['Name' => ID]
        $warehouses = Warehouse::pluck('id', 'name')->toArray();
        $this->command->info('Loaded '.count($warehouses).' warehouses');

        // Units: ['Name' => ID]
        $units = Unit::pluck('id', 'name')->toArray();
        $this->command->info('Loaded '.count($units).' units');

        $this->command->newLine();

        // ============================================
        // STEP 2: Read and process CSV with encoding detection
        // ============================================

        $rows = $this->readCsvWithEncoding($csvFile);

        if (empty($rows)) {
            $this->command->error('No data found in CSV file or invalid format');

            return;
        }

        $this->command->info('Successfully read '.count($rows).' rows from CSV');
        $this->command->newLine();

        // ============================================
        // STEP 3: Process rows
        // ============================================

        // Statistics
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped_no_name' => 0,
            'missing_category' => 0,
            'missing_supplier' => 0,
            'missing_warehouse' => 0,
            'units_created' => 0,
        ];

        $progressBar = $this->command->getOutput()->createProgressBar(count($rows));
        $progressBar->start();

        // Process each row
        foreach ($rows as $row) {
            $stats['total']++;

            // Skip if no name
            if (empty($row['name']) || trim($row['name']) === '') {
                $stats['skipped_no_name']++;
                $progressBar->advance();
                continue;
            }

            // ============================================
            // STEP 4: Lookup foreign keys
            // ============================================

            // Category lookup
            $categoryId = null;
            if (!empty($row['kind'])) {
                $categoryName = trim($row['kind']);
                if (isset($categories[$categoryName])) {
                    $categoryId = $categories[$categoryName];
                } else {
                    $stats['missing_category']++;
                    if ($stats['missing_category'] <= 5) {
                        $this->command->warn("\nCategory not found: '{$categoryName}' for product '{$row['name']}'");
                    }
                }
            }

            // Supplier lookup (Note: Product model has no supplier_id field!)
            // We'll track missing suppliers for reporting, but won't store in Product
            $supplierId = null;
            if (!empty($row['company'])) {
                $supplierName = trim($row['company']);
                if (isset($suppliers[$supplierName])) {
                    $supplierId = $suppliers[$supplierName];
                } else {
                    $stats['missing_supplier']++;
                    if ($stats['missing_supplier'] <= 5) {
                        $this->command->warn("\nSupplier not found: '{$supplierName}' for product '{$row['name']}'");
                    }
                }
            }

            // Warehouse lookup (for future stock movement, not stored in Product)
            $warehouseId = null;
            if (!empty($row['stor'])) {
                $warehouseName = trim($row['stor']);
                if (isset($warehouses[$warehouseName])) {
                    $warehouseId = $warehouses[$warehouseName];
                } else {
                    $stats['missing_warehouse']++;
                    if ($stats['missing_warehouse'] <= 5) {
                        $this->command->warn("\nWarehouse not found: '{$warehouseName}' for product '{$row['name']}'");
                    }
                }
            }

            // Unit lookup
            $smallUnitId = null;
            $largeUnitId = null;
            if (!empty($row['un1'])) {
                $unitName = trim($row['un1']);
                if (isset($units[$unitName])) {
                    $smallUnitId = $units[$unitName];
                } else {
                    // Create unit if not exists
                    $unit = Unit::firstOrCreate(['name' => $unitName]);
                    $smallUnitId = $unit->id;
                    $units[$unitName] = $unit->id; // Add to cache
                    $stats['units_created']++;
                }
            }

            if (!empty($row['un2'])) {
                $largeUnitName = trim($row['un2']);
                if (isset($units[$largeUnitName])) {
                    $largeUnitId = $units[$largeUnitName];
                } else {
                    // Create unit if not exists
                    $unit = Unit::firstOrCreate(['name' => $largeUnitName]);
                    $largeUnitId = $unit->id;
                    $units[$largeUnitName] = $unit->id; // Add to cache
                    $stats['units_created']++;
                }
            }

            // ============================================
            // STEP 5: Prepare product data
            // ============================================

            $productData = [
                'name' => trim($row['name']),
                'category_id' => $categoryId,
                'sku' => !empty($row['code']) ? trim($row['code']) : null,
                'small_unit_id' => $smallUnitId,
                'large_unit_id' => $largeUnitId,
                'factor' => !empty($row['qu2']) ? (int) floatval($row['qu2']) : null,
                'avg_cost' => !empty($row['pr_run1']) ? floatval($row['pr_run1']) : 0,
                'retail_price' => !empty($row['pr_s']) ? floatval($row['pr_s']) : 0,
                'wholesale_price' => !empty($row['pr_s']) ? floatval($row['pr_s']) : 0, // Same as retail if not provided
                'large_retail_price' => null, // Not in CSV
                'large_wholesale_price' => null, // Not in CSV
                'is_active' => !empty($row['vis']) && $row['vis'] === '1',
            ];

            // ============================================
            // STEP 6: Create or update product
            // ============================================

            try {
                // Use firstOrCreate based on SKU (or name if SKU is missing)
                if (!empty($productData['sku'])) {
                    $product = Product::where('sku', $productData['sku'])->first();
                } else {
                    $product = Product::where('name', $productData['name'])->first();
                }

                if ($product) {
                    // Update existing product
                    $product->update($productData);
                    $stats['updated']++;
                } else {
                    // Create new product
                    Product::create($productData);
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                $this->command->error("\nFailed to import product '{$row['name']}': ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        // ============================================
        // STEP 7: Display detailed statistics
        // ============================================

        $this->command->newLine(2);
        $this->command->info('✓ Products Import Complete!');
        $this->command->newLine();

        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Rows Processed', $stats['total']],
                ['Products Created', $stats['created']],
                ['Products Updated', $stats['updated']],
                ['Units Auto-Created', $stats['units_created']],
                ['Skipped (No Name)', $stats['skipped_no_name']],
                ['Missing Category Lookups', $stats['missing_category']],
                ['Missing Supplier Lookups', $stats['missing_supplier']],
                ['Missing Warehouse Lookups', $stats['missing_warehouse']],
            ]
        );

        $this->command->newLine();
        $this->command->warn('Note: Product model does not have a supplier_id field.');
        $this->command->warn('Supplier information from CSV is validated but not stored in Product table.');
        $this->command->warn('Consider adding a supplier_id migration if supplier tracking per product is needed.');
    }

    /**
     * Read CSV file with encoding conversion (Windows-1256 to UTF-8)
     *
     * @param string $filePath
     * @return array
     */
    private function readCsvWithEncoding(string $filePath): array
    {
        $data = [];
        $headers = [];

        // Read file content
        $fileContent = file_get_contents($filePath);

        // Detect encoding and convert to UTF-8
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'ASCII', 'ISO-8859-6'], true);

        // If detection fails or returns ASCII, try converting from Windows-1256
        if (!$encoding || $encoding === 'ASCII') {
            // Check if content has non-ASCII characters (likely Arabic)
            if (preg_match('/[^\x00-\x7F]/', $fileContent)) {
                $this->command->info('Detected non-ASCII content, attempting Windows-1256 to UTF-8 conversion...');
                // Try iconv instead of mb_convert_encoding for better Windows-1256 support
                $fileContent = iconv('Windows-1256', 'UTF-8//IGNORE', $fileContent);
            }
        } elseif ($encoding !== 'UTF-8') {
            $this->command->info("Detected encoding: {$encoding}, converting to UTF-8...");
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
        }

        // Parse CSV from converted content
        $lines = explode("\n", $fileContent);

        foreach ($lines as $index => $line) {
            // Skip empty lines
            if (trim($line) === '') {
                continue;
            }

            // Parse CSV line
            $row = str_getcsv($line);

            // First line is headers
            if ($index === 0) {
                $headers = array_map('trim', $row);
                continue;
            }

            // Combine headers with row data
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, array_map('trim', $row));
            } else {
                $this->command->warn('Row '.($index + 1).' has mismatched column count, skipping');
            }
        }

        return $data;
    }
}
