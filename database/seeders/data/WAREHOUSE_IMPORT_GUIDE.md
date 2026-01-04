# Warehouse Import Guide - Legacy Access Data Migration

## Overview
This guide documents the process of migrating warehouse/store data from your legacy Access system (`stor.csv`) into the Laravel Warehouse model.

## Schema Compatibility Analysis

### Source Data (Access System CSV)
| Column | Type | Description |
|--------|------|-------------|
| `stor` | String | Store/Warehouse name (Arabic text) |
| `vis` | Boolean (1/0) | Visibility/Active status |
| `printer` | String | Printer name (nullable) |
| `exported` | Boolean | Export status flag |

### Target Schema (Laravel Warehouse Model)
| Column | Type | Description | Source Mapping |
|--------|------|-------------|----------------|
| `id` | ULID | Primary key | Auto-generated |
| `name` | String | Warehouse name | `stor` |
| `code` | String | Warehouse code | Set to null (not in source) |
| `address` | Text | Warehouse address | Set to null (not in source) |
| `is_active` | Boolean | Active status | `vis` |
| `created_at` | Timestamp | Created timestamp | Auto-generated |
| `updated_at` | Timestamp | Updated timestamp | Auto-generated |

## Field Mapping Strategy

```php
$mapping = [
    'stor' => 'name',      // Store name → Warehouse name
    'vis' => 'is_active',  // Visibility → Active status
    // 'printer' => null,  // IGNORED (not in current schema)
    // 'exported' => null, // IGNORED (not in current schema)
];
```

### Missing Fields in Current System
The following legacy fields are **NOT** imported:
- ❌ `printer` - No equivalent field (would need migration to add)
- ❌ `exported` - No equivalent field (would need migration to add)

## Installation Steps

### 1. Run the Migration
Add the `is_active` field to the warehouses table:

```bash
php artisan migrate
```

This will execute:
- `2026_01_03_103443_add_is_active_to_warehouses_table.php`

### 2. Prepare Your CSV File
Place your legacy `stor.csv` file in:
```
database/seeders/data/stor.csv
```

#### Expected CSV Format:
```csv
stor,vis,printer,exported
رئيسي,1,HP LaserJet,0
فرع المنصورة,1,,0
مخزن طلخا,0,Canon Printer,1
```

**Important Notes:**
- Header row is **required**
- Column order doesn't matter (matched by header name)
- Encoding: The seeder auto-detects and converts Windows-1256/Arabic to UTF-8
- Empty values are allowed (will be set to null/default)

### 3. Run the Import Seeder

```bash
php artisan db:seed --class=WarehouseImportSeeder
```

## Seeder Features

### ✅ Robust Import Features
1. **Encoding Handling**
   - Auto-detects encoding (UTF-8, ASCII, ISO-8859-6)
   - Attempts CP1256 (Windows-1256) conversion for Arabic text
   - Converts to UTF-8 automatically
   - Handles Arabic text properly

2. **Duplicate Prevention**
   - Uses `firstOrCreate()` to check existing warehouses
   - Matches by `name` field
   - Skips duplicates without error

3. **Data Validation**
   - Validates CSV file exists
   - Checks for required fields
   - Skips empty rows
   - Reports malformed data

4. **Transaction Safety**
   - Wraps import in database transaction
   - Rolls back on critical errors
   - Logs failures for debugging

5. **Progress Reporting**
   - Shows real-time import status
   - Color-coded output (Created/Existing/Failed)
   - Summary statistics at completion

### Example Output:
```
Starting warehouse import from: database/seeders/data/stor.csv
✓ Created: رئيسى
✓ Created: فرع المنصورة
○ Exists: مخزن طلخا

═══════════════════════════════════════
Import Summary:
───────────────────────────────────────
✓ Created:  2
○ Existing: 1
✗ Failed:   0
═══════════════════════════════════════
```

**Note:** If non-ASCII (Arabic) content is detected in the CSV, you'll see:
```
Detected non-ASCII content, attempting CP1256 to UTF-8 conversion...
```

## Troubleshooting

### CSV File Not Found
```
CSV file not found: database/seeders/data/stor.csv
Please place your stor.csv file in: database/seeders/data/
```
**Solution:** Copy `stor.csv` to the correct directory

### Encoding Issues
If Arabic text appears garbled:
1. Check source file encoding (should be CP1256/Windows-1256 or UTF-8)
2. The seeder auto-detects and converts non-ASCII content from CP1256
3. For manual conversion if needed:
   ```bash
   iconv -f CP1256 -t UTF-8 stor.csv > stor_utf8.csv
   ```

### Duplicate Key Error
If you get unique constraint errors on `name`:
- The seeder uses `firstOrCreate()` which should prevent this
- Check if there are exact duplicate names in your CSV
- Review the `name` column for trailing spaces

### Missing Required Fields
```
Row 5: Missing store name, skipping
```
**Solution:** Ensure all rows have the `stor` column filled

## Post-Import Verification

Check imported warehouses:
```bash
php artisan tinker
```

```php
// Count total warehouses
\App\Models\Warehouse::count();

// View all imported warehouses
\App\Models\Warehouse::all();

// Check active warehouses only
\App\Models\Warehouse::where('is_active', true)->get();

// Find specific warehouse
\App\Models\Warehouse::where('name', 'رئيسي')->first();
```

## Re-running the Import

The seeder is **idempotent** - safe to run multiple times:
- Existing warehouses (same name) are skipped
- Only new warehouses are created
- No duplicates will be created

To re-import after fixing data:
```bash
php artisan db:seed --class=WarehouseImportSeeder
```

## Manual Cleanup (if needed)

To remove imported warehouses and start fresh:
```bash
php artisan tinker
```

```php
// Delete all warehouses (CAUTION: This deletes everything!)
\App\Models\Warehouse::truncate();

// Or delete specific ones
\App\Models\Warehouse::where('name', 'رئيسي')->delete();
```

## Files Modified/Created

### Database
- [Migration: 2026_01_03_103443_add_is_active_to_warehouses_table.php](../../migrations/2026_01_03_103443_add_is_active_to_warehouses_table.php)

### Models
- [app/Models/Warehouse.php](../../../app/Models/Warehouse.php) - Added `is_active` to fillable and casts

### Seeders
- [database/seeders/WarehouseImportSeeder.php](../WarehouseImportSeeder.php) - Main import seeder

### Data Files
- [database/seeders/data/stor.csv](stor.csv) - Place your CSV here
- [database/seeders/data/stor.csv.example](stor.csv.example) - Sample format reference

## Future Enhancements

If you need to import the `printer` and `exported` fields in the future:

1. Create migration:
```bash
php artisan make:migration add_printer_exported_to_warehouses --table=warehouses
```

2. Add columns:
```php
$table->string('printer')->nullable();
$table->boolean('exported')->default(false);
```

3. Update Warehouse model fillable:
```php
protected $fillable = ['name', 'code', 'address', 'is_active', 'printer', 'exported'];
```

4. Update seeder mapping:
```php
$mapping = [
    'stor' => 'name',
    'vis' => 'is_active',
    'printer' => 'printer',
    'exported' => 'exported',
];
```

---

**Last Updated:** 2026-01-03
**Laravel Version:** 11.x
**PHP Version:** 8.2+
