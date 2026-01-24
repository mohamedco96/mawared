<?php

namespace Tests\Feature\Filament\Expense;

use App\Enums\ExpenseCategoryType;
use App\Filament\Resources\ExpenseCategoryResource;
use App\Filament\Resources\ExpenseCategoryResource\Pages\CreateExpenseCategory;
use App\Filament\Resources\ExpenseCategoryResource\Pages\EditExpenseCategory;
use App\Filament\Resources\ExpenseCategoryResource\Pages\ListExpenseCategories;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        $this->actingAs($this->user);
    }

    public function test_can_render_index_page(): void
    {
        Livewire::test(ListExpenseCategories::class)
            ->assertStatus(200);
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateExpenseCategory::class)
            ->assertStatus(200);
    }

    public function test_can_create_expense_category(): void
    {
        Livewire::test(CreateExpenseCategory::class)
            ->fillForm([
                'name' => 'مصاريف إيجار',
                'type' => ExpenseCategoryType::OPERATIONAL->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'مصاريف إيجار',
            'type' => 'operational',
            'is_active' => true,
        ]);
    }

    public function test_can_create_expense_category_with_admin_type(): void
    {
        Livewire::test(CreateExpenseCategory::class)
            ->fillForm([
                'name' => 'رواتب الموظفين',
                'type' => ExpenseCategoryType::ADMIN->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'رواتب الموظفين',
            'type' => 'admin',
        ]);
    }

    public function test_can_create_expense_category_with_marketing_type(): void
    {
        Livewire::test(CreateExpenseCategory::class)
            ->fillForm([
                'name' => 'إعلانات',
                'type' => ExpenseCategoryType::MARKETING->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'إعلانات',
            'type' => 'marketing',
        ]);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateExpenseCategory::class)
            ->fillForm([
                'name' => '',
                'type' => ExpenseCategoryType::OPERATIONAL->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_type_is_required(): void
    {
        Livewire::test(CreateExpenseCategory::class)
            ->fillForm([
                'name' => 'Test Category',
                'type' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['type' => 'required']);
    }

    public function test_can_render_edit_page(): void
    {
        $category = ExpenseCategory::factory()->create();

        Livewire::test(EditExpenseCategory::class, ['record' => $category->id])
            ->assertStatus(200);
    }

    public function test_can_update_expense_category(): void
    {
        $category = ExpenseCategory::factory()->create([
            'name' => 'Original Name',
            'type' => ExpenseCategoryType::OPERATIONAL,
        ]);

        Livewire::test(EditExpenseCategory::class, ['record' => $category->id])
            ->fillForm([
                'name' => 'Updated Name',
                'type' => ExpenseCategoryType::ADMIN->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'type' => 'admin',
        ]);
    }

    public function test_can_toggle_active_status(): void
    {
        $category = ExpenseCategory::factory()->create(['is_active' => true]);

        Livewire::test(EditExpenseCategory::class, ['record' => $category->id])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_list_page_shows_categories(): void
    {
        ExpenseCategory::factory()->count(3)->create();

        Livewire::test(ListExpenseCategories::class)
            ->assertStatus(200)
            ->assertCanSeeTableRecords(ExpenseCategory::all());
    }

    public function test_can_filter_by_type(): void
    {
        $operational = ExpenseCategory::factory()->operational()->create();
        $admin = ExpenseCategory::factory()->admin()->create();

        Livewire::test(ListExpenseCategories::class)
            ->filterTable('type', 'operational')
            ->assertCanSeeTableRecords([$operational])
            ->assertCanNotSeeTableRecords([$admin]);
    }

    public function test_can_filter_by_active_status(): void
    {
        $active = ExpenseCategory::factory()->create(['is_active' => true]);
        $inactive = ExpenseCategory::factory()->inactive()->create();

        Livewire::test(ListExpenseCategories::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_can_search_categories(): void
    {
        $category1 = ExpenseCategory::factory()->create(['name' => 'مصاريف إيجار']);
        $category2 = ExpenseCategory::factory()->create(['name' => 'رواتب']);

        Livewire::test(ListExpenseCategories::class)
            ->searchTable('إيجار')
            ->assertCanSeeTableRecords([$category1])
            ->assertCanNotSeeTableRecords([$category2]);
    }

    public function test_cannot_delete_category_with_expenses(): void
    {
        $category = ExpenseCategory::factory()->create();
        $treasury = Treasury::factory()->create();

        Expense::factory()->create([
            'expense_category_id' => $category->id,
            'treasury_id' => $treasury->id,
        ]);

        $this->assertTrue($category->hasExpenses());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن حذف التصنيف لوجود مصروفات مرتبطة به');

        $category->delete();
    }

    public function test_can_delete_category_without_expenses(): void
    {
        $category = ExpenseCategory::factory()->create();

        $this->assertFalse($category->hasExpenses());

        $category->delete();

        $this->assertSoftDeleted('expense_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_expense_category_type_enum_labels(): void
    {
        $this->assertEquals('تشغيلية', ExpenseCategoryType::OPERATIONAL->getLabel());
        $this->assertEquals('إدارية', ExpenseCategoryType::ADMIN->getLabel());
        $this->assertEquals('تسويقية', ExpenseCategoryType::MARKETING->getLabel());
    }

    public function test_expense_category_type_enum_colors(): void
    {
        $this->assertEquals('primary', ExpenseCategoryType::OPERATIONAL->getColor());
        $this->assertEquals('info', ExpenseCategoryType::ADMIN->getColor());
        $this->assertEquals('success', ExpenseCategoryType::MARKETING->getColor());
    }

    public function test_expense_category_type_select_options(): void
    {
        $options = ExpenseCategoryType::getSelectOptions();

        $this->assertArrayHasKey('operational', $options);
        $this->assertArrayHasKey('admin', $options);
        $this->assertArrayHasKey('marketing', $options);
    }
}
