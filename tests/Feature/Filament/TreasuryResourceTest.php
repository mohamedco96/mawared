<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TreasuryResource\Pages\CreateTreasury;
use App\Filament\Resources\TreasuryResource\Pages\EditTreasury;
use App\Filament\Resources\TreasuryResource\Pages\ListTreasuries;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TreasuryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as a user for all tests
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);

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
        Livewire::test(ListTreasuries::class)
            ->assertStatus(200)
            ->assertSee('الخزائن');
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateTreasury::class)
            ->assertStatus(200);
    }

    public function test_can_render_edit_page(): void
    {
        $treasury = Treasury::factory()->create();

        Livewire::test(EditTreasury::class, ['record' => $treasury->id])
            ->assertStatus(200);
    }

    // ===== CRUD TESTS =====

    public function test_can_create_treasury(): void
    {
        Livewire::test(CreateTreasury::class)
            ->fillForm([
                'name' => 'New Treasury',
                'type' => 'cash',
                'description' => 'Main Cash Treasury',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('treasuries', [
            'name' => 'New Treasury',
            'type' => 'cash',
        ]);
    }

    public function test_can_edit_treasury(): void
    {
        $treasury = Treasury::factory()->create();

        Livewire::test(EditTreasury::class, ['record' => $treasury->id])
            ->fillForm([
                'name' => 'Updated Treasury',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('Updated Treasury', $treasury->fresh()->name);
    }

    public function test_can_delete_treasury(): void
    {
        $treasury = Treasury::factory()->create();

        Livewire::test(ListTreasuries::class)
            ->callTableAction('delete', $treasury);

        $this->assertDatabaseMissing('treasuries', [
            'id' => $treasury->id,
        ]);
    }

    // ===== BALANCE CALCULATION TESTS =====

    public function test_shows_correct_balance_in_table(): void
    {
        $treasury = Treasury::factory()->create();

        // Add transactions
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => 1000,
            'description' => 'Initial',
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'expense',
            'amount' => -200,
            'description' => 'Expense',
        ]);

        // Balance should be 800
        Livewire::test(ListTreasuries::class)
            ->assertTableColumnStateSet('current_balance', 800.00, $treasury);
    }

    // ===== VALIDATION TESTS =====

    public function test_validates_required_fields(): void
    {
        Livewire::test(CreateTreasury::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);
    }
}
