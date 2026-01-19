<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestoreBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_no_backups_found()
    {
        // ARRANGE
        Storage::fake('backups');

        // We need to ensure the backup destination sees no backups.
        // Since we can't easily control the external package's internal logic without complex mocking,
        // we'll rely on the fact that an empty fake disk should result in no backups.

        // ACT
        $this->artisan('backup:restore')
            ->expectsOutput('No backups found!')
            ->assertExitCode(1);
    }

    // Testing the success path is difficult because it involves:
    // 1. Spatie Backup finding the file (which might need real file system or complex mocking)
    // 2. Unzipping (ZipArchive)
    // 3. DB restoration

    // We will stick to testing the failure case for now to ensure at least some coverage.
}
