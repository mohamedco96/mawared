<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Import legacy warehouse data from Access system CSV export.
     * Handles Windows-1256/Arabic encoding and converts to UTF-8.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/stor.csv');

        // Validate CSV file exists
        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            $this->command->warn("Please place your stor.csv file in: database/seeders/data/");
            return;
        }

        $this->command->info("Starting warehouse import from: {$csvPath}");

        // Read CSV with encoding conversion
        $rows = $this->readCsvWithEncoding($csvPath);

        if (empty($rows)) {
            $this->command->error("No data found in CSV file or invalid format");
            return;
        }

        // Field mapping configuration
        $mapping = [
            'stor' => 'name',      // Store name -> Warehouse name
            'vis' => 'is_active',  // Visibility status -> Active status
            // 'printer' => null,  // Ignored (not in schema)
            // 'exported' => null, // Ignored (not in schema)
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
                if (empty($row['stor'])) {
                    $this->command->warn("Row {$rowNumber}: Missing store name, skipping");
                    $stats['failed']++;
                    continue;
                }

                // Prepare warehouse data
                $warehouseData = [
                    'name' => trim($row['stor']),
                    'is_active' => isset($row['vis']) ? (bool) $row['vis'] : true,
                    'code' => null, // Auto-generated or set to null
                    'address' => null,
                ];

                try {
                    // Use firstOrCreate to prevent duplicates (match by name)
                    $warehouse = Warehouse::firstOrCreate(
                        ['name' => $warehouseData['name']], // Search criteria
                        $warehouseData // Data to create if not found
                    );

                    if ($warehouse->wasRecentlyCreated) {
                        $stats['created']++;
                        $this->command->info("✓ Created: {$warehouseData['name']}");
                    } else {
                        $stats['existing']++;
                        $this->command->comment("○ Exists: {$warehouseData['name']}");
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $this->command->error("✗ Row {$rowNumber} failed: {$e->getMessage()}");
                    Log::error("Warehouse import error", [
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
            $this->command->info("Import Summary:");
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
