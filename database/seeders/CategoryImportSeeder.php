<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Import legacy product category data from Access system CSV export.
     * Handles Windows-1256/Arabic encoding and converts to UTF-8.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/kind.csv');

        // Validate CSV file exists
        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            $this->command->warn("Please place your kind.csv file in: database/seeders/data/");
            return;
        }

        $this->command->info("Starting category import from: {$csvPath}");

        // Read CSV with encoding conversion
        $rows = $this->readCsvWithEncoding($csvPath);

        if (empty($rows)) {
            $this->command->error("No data found in CSV file or invalid format");
            return;
        }

        // Field mapping configuration
        $mapping = [
            'kind' => 'name',                    // Category name -> name
            'vis' => 'is_active',                 // Visibility -> Active status
            'pr_rkind' => 'default_profit_margin', // Profit rate -> Default profit margin
            'shortcode' => 'display_order',       // Shortcode -> Display order
            // 'exported' => null,                // Ignored (not in schema)
        ];

        $stats = [
            'created' => 0,
            'existing' => 0,
            'failed' => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 for header line and 0-index

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Validate required fields
                if (empty($row['kind'])) {
                    $this->command->warn("Row {$rowNumber}: Missing category name, skipping");
                    $stats['failed']++;
                    continue;
                }

                // Prepare category data
                $categoryData = [
                    'name' => trim($row['kind']),
                    'is_active' => isset($row['vis']) ? (bool) $row['vis'] : true,
                    'default_profit_margin' => isset($row['pr_rkind']) && is_numeric($row['pr_rkind'])
                        ? (float) $row['pr_rkind']
                        : null,
                    'display_order' => isset($row['shortcode']) && is_numeric($row['shortcode'])
                        ? (int) $row['shortcode']
                        : 0,
                    // Optional fields set to null (will use defaults)
                    'name_en' => null,
                    'description' => null,
                    'parent_id' => null, // All imported categories are root-level
                ];

                try {
                    // Use firstOrCreate to prevent duplicates (match by name)
                    $category = ProductCategory::firstOrCreate(
                        ['name' => $categoryData['name']], // Search criteria
                        $categoryData // Data to create if not found
                    );

                    if ($category->wasRecentlyCreated) {
                        $stats['created']++;
                        $profitInfo = $categoryData['default_profit_margin']
                            ? " (margin: {$categoryData['default_profit_margin']}%)"
                            : "";
                        $this->command->info("✓ Created: {$categoryData['name']}{$profitInfo}");
                    } else {
                        $stats['existing']++;
                        $this->command->comment("○ Exists: {$categoryData['name']}");
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $this->command->error("✗ Row {$rowNumber} failed: {$e->getMessage()}");
                    Log::error("Category import error", [
                        'row' => $rowNumber,
                        'data' => $row,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            // Display summary
            $this->command->newLine();
            $this->command->info("═══════════════════════════════════════");
            $this->command->info("Category Import Summary:");
            $this->command->info("───────────────────────────────────────");
            $this->command->info("✓ Created:  {$stats['created']}");
            $this->command->info("○ Existing: {$stats['existing']}");
            $this->command->error("✗ Failed:   {$stats['failed']}");
            $this->command->info("═══════════════════════════════════════");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Import failed: {$e->getMessage()}");
            throw $e;
        }
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
        // Note: Use CP1256 instead of Windows-1256 for PHP compatibility
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'ASCII', 'ISO-8859-6'], true);

        // If detection fails or returns ASCII, try converting from CP1256 (Windows-1256)
        if (!$encoding || $encoding === 'ASCII') {
            // Check if content has non-ASCII characters (likely Arabic)
            if (preg_match('/[^\x00-\x7F]/', $fileContent)) {
                $this->command->info("Detected non-ASCII content, attempting CP1256 to UTF-8 conversion...");
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'CP1256');
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
                $this->command->warn("Row " . ($index + 1) . " has mismatched column count, skipping");
            }
        }

        return $data;
    }
}
