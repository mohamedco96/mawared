<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use ZipArchive;

class RestoreBackupCommand extends Command
{
    protected $signature = 'backup:restore
                            {backup? : The backup filename to restore}
                            {--disk=backups : The disk where the backup is stored}
                            {--storage-only : Restore only storage files (default behavior)}
                            {--database : Restore database}';

    protected $description = 'Restore storage files from a backup';

    public function handle(): int
    {
        $backupDestination = BackupDestination::create(
            $this->option('disk'),
            config('backup.backup.name')
        );

        $backups = $backupDestination->backups();

        if ($backups->isEmpty()) {
            $this->error('No backups found!');
            return self::FAILURE;
        }

        // Select backup
        $backup = $this->selectBackup($backups);

        if (!$backup) {
            $this->error('No backup selected.');
            return self::FAILURE;
        }

        $this->info("Selected backup: {$backup->path()}");
        $this->info("Size: {$backup->sizeInMb()} MB");
        $this->info("Date: {$backup->date()->format('Y-m-d H:i:s')}");

        // Confirm restoration
        if (!$this->confirm('Are you sure you want to restore storage files from this backup? This will overwrite existing files.', false)) {
            $this->info('Restoration cancelled.');
            return self::SUCCESS;
        }

        try {
            // Restore based on options
            if ($this->option('database')) {
                $this->restoreDatabase($backup);
            } else {
                // Default: restore storage only
                $this->restoreStorageFiles($backup);
            }

            $this->info('✅ Restoration completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Restoration failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function selectBackup($backups)
    {
        $backupName = $this->argument('backup');

        if ($backupName) {
            return $backups->first(fn($backup) => basename($backup->path()) === $backupName);
        }

        // Interactive selection
        $choices = $backups->map(function ($backup, $index) {
            return sprintf(
                '%s - %s (%s MB)',
                $backup->date()->format('Y-m-d H:i:s'),
                basename($backup->path()),
                $backup->sizeInMb()
            );
        })->toArray();

        $selection = $this->choice('Select a backup to restore:', $choices, 0);
        $selectedIndex = array_search($selection, $choices);

        return $backups->get($selectedIndex);
    }

    protected function restoreStorageFiles($backup): void
    {
        $this->info('🔄 Restoring storage files...');

        $disk = Storage::disk($this->option('disk'));
        $tempPath = storage_path('app/backup-restore-temp');
        $zipPath = $tempPath . '/' . basename($backup->path());

        // Create temp directory
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        // Download backup to temp location
        $this->info('📥 Downloading backup file...');
        file_put_contents($zipPath, $disk->get($backup->path()));

        // Extract zip
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception('Failed to open backup zip file');
        }

        $this->info('📦 Extracting backup...');
        $extractPath = $tempPath . '/extracted';
        $zip->extractTo($extractPath);
        $zip->close();

        // Find and restore only storage folder
        $this->info('📂 Restoring storage files...');
        $storagePath = $this->findStorageInBackup($extractPath);

        if (!$storagePath) {
            throw new \Exception('Storage folder not found in backup');
        }

        // Restore storage/app folder (excluding backup folders)
        $this->restoreDirectory(
            $storagePath . '/app',
            storage_path('app'),
            ['backups', 'backup-temp', 'backup-restore-temp']
        );

        // Restore storage/public folder
        if (is_dir($storagePath . '/public')) {
            $this->restoreDirectory(
                $storagePath . '/public',
                storage_path('public')
            );
        }

        // Cleanup
        $this->info('🧹 Cleaning up temporary files...');
        $this->deleteDirectory($tempPath);

        $this->info('✅ Storage files restored successfully!');
    }

    protected function restoreDatabase($backup): void
    {
        $this->info('🔄 Restoring database...');

        $disk = Storage::disk($this->option('disk'));
        $tempPath = storage_path('app/backup-restore-temp');
        $zipPath = $tempPath . '/' . basename($backup->path());

        // Create temp directory
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        // Download backup to temp location
        $this->info('📥 Downloading backup file...');
        file_put_contents($zipPath, $disk->get($backup->path()));

        // Extract zip
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception('Failed to open backup zip file');
        }

        $this->info('📦 Extracting backup...');
        $extractPath = $tempPath . '/extracted';
        $zip->extractTo($extractPath);
        $zip->close();

        // Find SQL file
        $sqlFile = $this->findSqlFile($extractPath);

        if (!$sqlFile) {
            throw new \Exception('Database dump file not found in backup');
        }

        $this->info('💾 Importing database...');

        // Import database
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");
        $host = config("database.connections.{$connection}.host");

        $command = sprintf(
            'mysql -h%s -u%s -p%s %s < %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($sqlFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Database import failed');
        }

        // Cleanup
        $this->info('🧹 Cleaning up temporary files...');
        $this->deleteDirectory($tempPath);

        $this->info('✅ Database restored successfully!');
    }

    protected function findStorageInBackup(string $extractPath): ?string
    {
        // Look for storage folder in common locations
        $possiblePaths = [
            $extractPath . '/storage',
            $extractPath . '/*/storage',
        ];

        foreach ($possiblePaths as $pattern) {
            $matches = glob($pattern);
            if (!empty($matches) && is_dir($matches[0])) {
                return $matches[0];
            }
        }

        return null;
    }

    protected function findSqlFile(string $extractPath): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'sql') {
                return $file->getPathname();
            }
        }

        return null;
    }

    protected function restoreDirectory(string $source, string $destination, array $exclude = []): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);

            // Skip excluded directories
            $shouldExclude = false;
            foreach ($exclude as $excludePattern) {
                if (str_starts_with($relativePath, $excludePattern)) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            $destPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
