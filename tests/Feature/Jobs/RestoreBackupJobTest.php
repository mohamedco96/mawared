<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RestoreBackupJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RestoreBackupJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_failure_notification_when_backup_not_found()
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $job = new RestoreBackupJob(
            backupPath: 'non-existent-backup.zip',
            disk: 'local',
            restoreDatabase: false,
            restoreStorage: false,
            userId: $user->id
        );

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => 'user',
        ]);

        // Check content
        $notification = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->first();

        $data = json_decode($notification->data, true);
        $this->assertEquals('فشلت استعادة النسخة الاحتياطية', $data['title']);
    }

    public function test_it_processes_backup_restore_successfully()
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $appName = config('backup.backup.name');
        // Ensure app name is safe for path
        $appName = str_replace(' ', '-', $appName); // Just in case, though usually handled by config
        // Actually BackupDestination uses the name as directory.

        // Let's assume the backup is in the root for now, or use the exact logic Spatie uses.
        // But better: place it where Spatie expects it.
        // If I don't know where it expects it, I can try to put it in root AND in subdir.

        // If backupPath passed to Job is 'backup.zip', and BackupDestination finds 'Laravel/backup.zip', they won't match.
        // So I should construct the path correctly.

        $zipPath = $appName.'/backup.zip';

        $zip = new ZipArchive;
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');
        if ($zip->open($tempFile, ZipArchive::CREATE) === true) {
            // Add a file in the expected structure: storage/app/test.txt
            $zip->addFromString('storage/app/test_restore.txt', 'restored content');
            $zip->close();
        }

        Storage::disk('local')->put($zipPath, file_get_contents($tempFile));
        unlink($tempFile);

        $job = new RestoreBackupJob(
            backupPath: $zipPath,
            disk: 'local',
            restoreDatabase: false, // Skip DB restore to simplify
            restoreStorage: true,
            userId: $user->id
        );

        $job->handle();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => 'user',
        ]);

        // Check content
        $notification = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $data = json_decode($notification->data, true);
        $this->assertEquals('تم استعادة النسخة الاحتياطية بنجاح', $data['title']);

        // Verify file was restored
        $this->assertFileExists(storage_path('app/test_restore.txt'));
        $this->assertEquals('restored content', file_get_contents(storage_path('app/test_restore.txt')));

        // Cleanup
        @unlink(storage_path('app/test_restore.txt'));
    }
}
