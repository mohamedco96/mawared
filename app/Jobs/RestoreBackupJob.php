<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Filament\Notifications\Notification;
use ZipArchive;
use Exception;

class RestoreBackupJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600; // 1 hour timeout

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $backupPath,
        public string $disk,
        public bool $restoreDatabase = false,
        public bool $restoreStorage = true,
        public ?string $userId = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $backupDestination = BackupDestination::create(
                $this->disk,
                config('backup.backup.name')
            );

            $backup = $backupDestination->backups()->first(
                fn($b) => $b->path() === $this->backupPath
            );

            if (!$backup) {
                throw new Exception('Backup not found');
            }

            if ($this->restoreStorage) {
                $this->restoreStorageFiles($backup);
            }

            if ($this->restoreDatabase) {
                $this->restoreDatabase($backup);
            }

            // Send success notification
            if ($this->userId) {
                Notification::make()
                    ->title('تم استعادة النسخة الاحتياطية بنجاح')
                    ->success()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

        } catch (Exception $e) {
            // Send failure notification
            if ($this->userId) {
                Notification::make()
                    ->title('فشلت استعادة النسخة الاحتياطية')
                    ->body($e->getMessage())
                    ->danger()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

            throw $e;
        }
    }

    protected function restoreStorageFiles($backup): void
    {
        $disk = Storage::disk($this->disk);
        $tempPath = storage_path('app/backup-restore-temp-' . uniqid());
        $zipPath = $tempPath . '/' . basename($backup->path());

        // Create temp directory
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        try {
            // Download backup to temp location
            file_put_contents($zipPath, $disk->get($backup->path()));

            // Extract zip
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('Failed to open backup zip file');
            }

            $extractPath = $tempPath . '/extracted';
            $zip->extractTo($extractPath);
            $zip->close();

            // Find and restore only storage folder
            $storagePath = $this->findStorageInBackup($extractPath);

            if (!$storagePath) {
                throw new Exception('Storage folder not found in backup');
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

        } finally {
            // Cleanup
            if (file_exists($tempPath)) {
                $this->deleteDirectory($tempPath);
            }
        }
    }

    protected function restoreDatabase($backup): void
    {
        $disk = Storage::disk($this->disk);
        $tempPath = storage_path('app/backup-restore-temp-' . uniqid());
        $zipPath = $tempPath . '/' . basename($backup->path());

        // Create temp directory
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        try {
            // Download backup to temp location
            file_put_contents($zipPath, $disk->get($backup->path()));

            // Extract zip
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('Failed to open backup zip file');
            }

            $extractPath = $tempPath . '/extracted';
            $zip->extractTo($extractPath);
            $zip->close();

            // Find SQL file
            $sqlFile = $this->findSqlFile($extractPath);

            if (!$sqlFile) {
                throw new Exception('Database dump file not found in backup');
            }

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
                throw new Exception('Database import failed');
            }

        } finally {
            // Cleanup
            if (file_exists($tempPath)) {
                $this->deleteDirectory($tempPath);
            }
        }
    }

    protected function findStorageInBackup(string $extractPath): ?string
    {
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
