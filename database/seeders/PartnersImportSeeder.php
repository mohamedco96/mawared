<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnersImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Import legacy partner data (Customers & Suppliers) from Access system CSV export.
     * Handles Windows-1256/Arabic encoding and converts to UTF-8.
     * Maps 'عميل' to customer and 'مورد' to supplier.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/tel.csv');

        // Validate CSV file exists
        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            $this->command->warn("Please place your tel.csv file in: database/seeders/data/");
            return;
        }

        $this->command->info("Starting partners import from: {$csvPath}");

        // Read CSV with encoding conversion
        $rows = $this->readCsvWithEncoding($csvPath);

        if (empty($rows)) {
            $this->command->error("No data found in CSV file or invalid format");
            return;
        }

        // Field mapping configuration
        $mapping = [
            'autono' => 'legacy_id',   // Legacy ID
            'to' => 'name',            // Name
            'tel' => 'phone',          // Phone number
            'adres' => 'address',      // Address
            'kindtel' => 'type',       // Type (عميل/مورد)
            'vis' => 'is_active',      // Active status
        ];

        $stats = [
            'customers_created' => 0,
            'customers_existing' => 0,
            'suppliers_created' => 0,
            'suppliers_existing' => 0,
            'unknown_created' => 0,
            'unknown_existing' => 0,
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
                if (empty($row['to'])) {
                    $this->command->warn("Row {$rowNumber}: Missing partner name, skipping");
                    $stats['failed']++;
                    continue;
                }

                // Determine partner type
                $kindtel = isset($row['kindtel']) ? trim($row['kindtel']) : '';
                $partnerType = null;

                if ($kindtel === 'عميل') {
                    $partnerType = 'customer';
                } elseif ($kindtel === 'مورد') {
                    $partnerType = 'supplier';
                } else {
                    // Unknown or missing type
                    $partnerType = 'unknown';
                    if (empty($kindtel)) {
                        $this->command->comment("Row {$rowNumber}: Missing partner type (kindtel), setting as 'unknown'");
                    } else {
                        $this->command->comment("Row {$rowNumber}: Unknown partner type '{$kindtel}', setting as 'unknown'");
                    }
                }

                // Prepare partner data
                $partnerData = [
                    'legacy_id' => isset($row['autono']) && is_numeric($row['autono'])
                        ? (int) $row['autono']
                        : null,
                    'name' => trim($row['to']),
                    'phone' => !empty($row['tel']) ? trim($row['tel']) : null,
                    'address' => !empty($row['adres']) ? trim($row['adres']) : null,
                    'type' => $partnerType,
                    'is_banned' => false, // Default not banned
                    'current_balance' => 0, // Will be calculated from transactions
                    'opening_balance' => 0, // Default opening balance
                ];

                // Handle is_active field
                if (isset($row['vis'])) {
                    // If vis is boolean-like (0/1 or true/false)
                    $partnerData['is_banned'] = !(bool) $row['vis'];
                }

                try {
                    // Use firstOrCreate to prevent duplicates
                    // Priority: legacy_id (if exists), then name
                    $searchCriteria = $partnerData['legacy_id']
                        ? ['legacy_id' => $partnerData['legacy_id']]
                        : ['name' => $partnerData['name'], 'type' => $partnerType];

                    $partner = Partner::firstOrCreate(
                        $searchCriteria,
                        $partnerData
                    );

                    $typeLabel = match($partnerType) {
                        'customer' => 'Customer',
                        'supplier' => 'Supplier',
                        'unknown' => 'Unknown',
                        default => 'Partner',
                    };

                    if ($partner->wasRecentlyCreated) {
                        if ($partnerType === 'customer') {
                            $stats['customers_created']++;
                        } elseif ($partnerType === 'supplier') {
                            $stats['suppliers_created']++;
                        } else {
                            $stats['unknown_created']++;
                        }
                        $legacyInfo = $partnerData['legacy_id'] ? " (legacy_id: {$partnerData['legacy_id']})" : "";
                        $this->command->info("✓ Created {$typeLabel}: {$partnerData['name']}{$legacyInfo}");
                    } else {
                        if ($partnerType === 'customer') {
                            $stats['customers_existing']++;
                        } elseif ($partnerType === 'supplier') {
                            $stats['suppliers_existing']++;
                        } else {
                            $stats['unknown_existing']++;
                        }
                        $this->command->comment("○ Exists {$typeLabel}: {$partnerData['name']}");
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $this->command->error("✗ Row {$rowNumber} failed: {$e->getMessage()}");
                    Log::error("Partner import error", [
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
            $this->command->info("Partners Import Summary:");
            $this->command->info("───────────────────────────────────────");
            $this->command->info("CUSTOMERS:");
            $this->command->info("  ✓ Created:  {$stats['customers_created']}");
            $this->command->info("  ○ Existing: {$stats['customers_existing']}");
            $this->command->newLine();
            $this->command->info("SUPPLIERS:");
            $this->command->info("  ✓ Created:  {$stats['suppliers_created']}");
            $this->command->info("  ○ Existing: {$stats['suppliers_existing']}");
            $this->command->newLine();
            $this->command->info("UNKNOWN:");
            $this->command->info("  ✓ Created:  {$stats['unknown_created']}");
            $this->command->info("  ○ Existing: {$stats['unknown_existing']}");
            $this->command->newLine();
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
