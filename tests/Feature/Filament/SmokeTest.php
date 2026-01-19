<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Setup Super Admin User
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->assignRole($role);

        // Grant all permissions via Gate::before
        // Note: Filament Shield usually handles this if configured, but in tests we need to be explicit
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            // dump("Checking ability: $ability for user: " . $user->id);
            return $user->hasRole('super_admin') ? true : null;
        });

        $this->actingAs($user);

        // Ensure common dependencies exist
        Warehouse::factory()->create(['name' => 'Main Warehouse']);
    }

    public function test_can_render_all_resource_index_pages()
    {
        $resources = Filament::getResources();

        $this->assertNotEmpty($resources, 'No Filament resources found. Check panel configuration.');

        $failedResources = [];

        foreach ($resources as $resource) {
            try {
                if (! method_exists($resource, 'getPages')) {
                    continue;
                }

                $pages = $resource::getPages();
                if (! isset($pages['index'])) {
                    continue;
                }

                $pageRegistration = $pages['index'];
                $pageClass = $pageRegistration->getPage();

                \Livewire\Livewire::test($pageClass)
                    ->assertStatus(200);

            } catch (\Exception $e) {
                $failedResources[$resource] = 'Exception: '.$e->getMessage();
            }
        }

        if (! empty($failedResources)) {
            $this->fail('Failed to render index pages for: '.print_r($failedResources, true));
        }
    }

    public function test_can_render_create_pages()
    {
        $resources = Filament::getResources();
        $failedResources = [];

        foreach ($resources as $resource) {
            try {
                if (! method_exists($resource, 'getPages')) {
                    continue;
                }

                $pages = $resource::getPages();
                if (! isset($pages['create'])) {
                    continue;
                }

                $pageRegistration = $pages['create'];
                $pageClass = $pageRegistration->getPage();

                \Livewire\Livewire::test($pageClass)
                    ->assertStatus(200);

            } catch (\Exception $e) {
                $failedResources[$resource] = 'Exception: '.$e->getMessage();
            }
        }

        if (! empty($failedResources)) {
            $this->fail('Failed to render create pages for: '.print_r($failedResources, true));
        }
    }
}
