<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as a user for all tests
        $role = Role::firstOrCreate(['name' => 'super_admin']);

        $user = User::factory()->create();
        $user->assignRole($role);

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        $this->actingAs($user);
    }

    // ===== PAGE RENDERING TESTS =====

    public function test_can_render_list_page(): void
    {
        Livewire::test(ListUsers::class)
            ->assertStatus(200)
            ->assertSee('المستخدمين');
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateUser::class)
            ->assertStatus(200);
    }

    public function test_can_render_edit_page(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->assertStatus(200);
    }

    // ===== CRUD TESTS =====

    public function test_can_create_user(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'Password123',
                'salary_type' => 'monthly',
                'salary_amount' => 5000,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'salary_type' => 'monthly',
            'salary_amount' => 5000,
        ]);
    }

    public function test_can_edit_user(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
                'salary_amount' => 6000,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('Updated Name', $user->fresh()->name);
        $this->assertEquals(6000, $user->fresh()->salary_amount);
    }

    public function test_can_delete_user(): void
    {
        $user = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $user);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    // ===== ROLE ASSIGNMENT TESTS =====

    public function test_can_assign_roles(): void
    {
        $role = Role::create(['name' => 'manager']);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Manager User',
                'email' => 'manager@example.com',
                'password' => 'Password123',
                'roles' => [$role->id],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $user = User::where('email', 'manager@example.com')->first();
        $this->assertTrue($user->hasRole('manager'));
    }

    // ===== VALIDATION TESTS =====

    public function test_validates_password_complexity(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Weak Password',
                'email' => 'weak@example.com',
                'password' => 'weak', // Too short, no numbers
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_validates_national_id_length(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Bad ID',
                'email' => 'badid@example.com',
                'password' => 'Password123',
                'national_id' => '123', // Too short
            ])
            ->call('create')
            ->assertHasFormErrors(['national_id']);
    }
}
