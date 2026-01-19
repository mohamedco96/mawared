<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InitializeCompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initializes_missing_settings()
    {
        // ARRANGE
        // Ensure settings table is empty initially (except what might be seeded)
        DB::table('settings')->delete();

        // ACT
        $this->artisan('settings:initialize-company')
            ->expectsOutputToContain('Initializing company settings...')
            ->expectsOutputToContain('Added: company.company_name')
            ->assertExitCode(0);

        // ASSERT
        $this->assertDatabaseHas('settings', [
            'group' => 'company',
            'name' => 'company_name',
        ]);

        $this->assertDatabaseHas('settings', [
            'group' => 'company',
            'name' => 'currency',
            'payload' => json_encode('EGP'),
        ]);
    }

    public function test_skips_existing_settings()
    {
        // ARRANGE
        // Clear existing settings first to ensure clean state
        DB::table('settings')->delete();

        DB::table('settings')->insert([
            'group' => 'company',
            'name' => 'company_name',
            'locked' => false,
            'payload' => json_encode('Existing Company Name'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ACT
        $this->artisan('settings:initialize-company')
            ->expectsOutputToContain('Skipped (exists): company.company_name')
            ->assertExitCode(0);

        // ASSERT
        $this->assertDatabaseHas('settings', [
            'group' => 'company',
            'name' => 'company_name',
            'payload' => json_encode('Existing Company Name'),
        ]);
    }
}
