# Backup & Restore Guide

## Overview

This project includes a comprehensive backup and restore system built on top of Spatie Laravel Backup package with custom restore functionality.

## Features

### Backup Features (Existing)
- ✅ Create backups of database and files
- ✅ Automated backup scheduling
- ✅ Download backup files
- ✅ Delete old backups
- ✅ Backup health monitoring
- ✅ Email notifications

### Restore Features (NEW)
- ✅ Restore storage files only (safe, doesn't affect code)
- ✅ Restore database only
- ✅ Restore both storage and database
- ✅ CLI command for manual restoration
- ✅ Filament UI integration with user-friendly interface
- ✅ Background job processing for large restores
- ✅ Permission-based access control
- ✅ Real-time notifications

---

## Restore Methods

### Method 1: Filament Admin Panel (Recommended for most users)

1. Navigate to **Settings > Backups** (إعدادات النظام > النسخ الاحتياطي)
2. Find the backup you want to restore in the table
3. Click the **Restore** (استعادة) button (yellow button with arrow icon)
4. Select what you want to restore:
   - **Storage Files Only** (ملفات التخزين فقط) - Recommended, safe option
     - Restores: Uploaded images, documents, user files
     - Does NOT affect: Application code, logic, or configuration
   - **Database Only** (قاعدة البيانات فقط)
     - Restores: All database records
   - **Both** (كلاهما)
     - Restores: Both storage files and database
5. Confirm the restoration
6. You'll receive a notification when the process starts
7. You'll receive another notification when it completes (success or failure)

**Permissions Required:** User must have `restore-backup` permission

---

### Method 2: Artisan Command (CLI)

#### Interactive Mode (Easiest)
```bash
php artisan backup:restore
```
This will:
- Show you a list of available backups
- Let you select which backup to restore
- Ask for confirmation
- Restore storage files by default

#### Restore Specific Backup
```bash
php artisan backup:restore 2026-01-04-03-25-40.zip
```

#### Restore Database Only
```bash
php artisan backup:restore --database
```

#### Restore from Different Disk
```bash
php artisan backup:restore --disk=s3
```

#### Non-Interactive Mode (for scripts)
```bash
php artisan backup:restore backup-name.zip --no-interaction
```
⚠️ **Warning:** This will restore without asking for confirmation!

---

## What Gets Restored

### Storage Files (Default & Recommended)
When you restore storage files, the following directories are restored:

✅ **Restored:**
- `storage/app/*` (except backup folders)
  - Uploaded files
  - User documents
  - Generated reports
  - Application data files
- `storage/public/*`
  - Public uploads
  - User avatars
  - Product images

❌ **NOT Restored (Excluded for safety):**
- `storage/app/backups/` - Backup files themselves
- `storage/app/backup-temp/` - Temporary backup processing
- `storage/app/backup-restore-temp/` - Temporary restore processing
- Application code files (`app/`, `config/`, `routes/`, etc.)
- Vendor packages
- Node modules
- Environment files (.env)

### Database
When you restore database:
- All tables are dropped and recreated from the backup
- ⚠️ **Warning:** This will overwrite ALL current data!
- Make sure to create a fresh backup before restoring an old one

---

## Safety Features

### 1. Confirmation Required
- All restore operations require explicit confirmation
- No accidental restores

### 2. Permission Control
Only users with `restore-backup` permission can restore backups:
- Super Admin: ✅ Has permission by default
- Admin: ✅ Has permission by default
- Other roles: ❌ Need explicit permission grant

### 3. Background Processing
- Large restores run in background jobs
- Won't timeout or crash the browser
- System remains responsive

### 4. Notifications
- Start notification: "Restore process started"
- Success notification: "Restore completed successfully"
- Failure notification: "Restore failed" with error details

### 5. Temporary Files Cleanup
- Extracted files are automatically cleaned up
- No disk space wasted on temporary files

---

## Best Practices

### Before Restoring
1. **Create a fresh backup** before restoring an old one
   ```bash
   php artisan backup:run
   ```
2. **Verify the backup** you want to restore is the correct one
3. **Check backup health**
   ```bash
   php artisan backup:list
   ```
4. **Notify your team** if you're restoring in production

### When to Restore Storage Only
- User accidentally deleted uploaded files
- Need to recover lost images/documents
- Want to restore user data without affecting database
- **This is the SAFEST option** - doesn't affect application logic

### When to Restore Database Only
- Need to recover deleted records
- Want to rollback database changes
- Testing/development purposes

### When to Restore Both
- Complete disaster recovery
- Moving to a new server
- Testing full system restore

---

## Troubleshooting

### Issue: "Permission denied"
**Solution:** Make sure your user has the `restore-backup` permission
```bash
# Grant permission to a user
php artisan tinker
>>> $user = User::find(YOUR_USER_ID);
>>> $user->givePermissionTo('restore-backup');
```

### Issue: "Backup not found"
**Solution:** Check available backups
```bash
php artisan backup:list
```

### Issue: "Storage folder not found in backup"
**Cause:** Backup might be corrupted or created without files
**Solution:** Try a different backup or create a fresh one

### Issue: "Database import failed"
**Possible causes:**
- MySQL not running
- Wrong database credentials
- Insufficient permissions
**Solution:** Check database connection and credentials in `.env`

### Issue: Restore takes too long
**Normal behavior:** Large backups (>100MB) may take several minutes
**Solution:** Use the Filament UI method - it runs in background

---

## Technical Details

### Files Created
1. `app/Console/Commands/RestoreBackupCommand.php` - Artisan command
2. `app/Jobs/RestoreBackupJob.php` - Background job for processing
3. `app/Filament/Components/BackupDestinationListRecords.php` - UI component with restore action
4. `resources/views/filament/pages/backups.blade.php` - Custom view

### Permissions
- `download-backup` - Download backup files
- `delete-backup` - Delete backup files
- `restore-backup` - **NEW** - Restore from backups

### Queue Configuration
Restore jobs run on the `default` queue. For production:
1. Configure queue worker in supervisor
2. Or use `php artisan queue:work` to process jobs

---

## Examples

### Example 1: Restore User Uploaded Files
```bash
# Safe restore of only storage files
php artisan backup:restore
# Select the backup with the date before files were lost
# Confirm restoration
```

### Example 2: Complete System Restore
```bash
# 1. Create safety backup first
php artisan backup:run --only-db

# 2. Restore both storage and database
# Use Filament UI and select "Both" option
```

### Example 3: Scheduled Restore Testing
```bash
# In your testing script
php artisan backup:restore latest-backup.zip --no-interaction
```

---

## Support

For issues or questions:
1. Check this guide first
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check queue logs if using background jobs
4. Contact system administrator

---

**Last Updated:** 2026-01-04
**Version:** 1.0.0
