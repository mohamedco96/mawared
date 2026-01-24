<?php

namespace Tests\Feature\Filament\Expense;

use App\Enums\ExpenseCategoryType;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\ExpenseResource\Pages\CreateExpense;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Treasury $treasury;

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

        // Create a treasury with initial balance
        $this->treasury = Treasury::factory()->create(['type' => 'cash']);
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'income',
            'amount' => '100000.00',
            'description' => 'Initial balance',
        ]);

        Storage::fake('public');
    }

    public function test_can_render_index_page(): void
    {
        Livewire::test(ListExpenses::class)
            ->assertStatus(200);
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateExpense::class)
            ->assertStatus(200);
    }

    public function test_can_create_basic_expense(): void
    {
        $category = ExpenseCategory::factory()->create();

        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'فاتورة كهرباء',
                'description' => 'فاتورة كهرباء شهر يناير',
                'amount' => 500.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
                'expense_category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', [
            'title' => 'فاتورة كهرباء',
            'amount' => '500.0000',
            'treasury_id' => $this->treasury->id,
        ]);
    }

    public function test_can_create_expense_with_category(): void
    {
        $category = ExpenseCategory::factory()->operational()->create([
            'name' => 'مصاريف كهرباء',
        ]);

        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'فاتورة كهرباء',
                'amount' => 500.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
                'expense_category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', [
            'title' => 'فاتورة كهرباء',
            'expense_category_id' => $category->id,
        ]);
    }

    public function test_can_create_expense_with_beneficiary(): void
    {
        $category = ExpenseCategory::factory()->create();

        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'دفعة للمقاول',
                'amount' => 1000.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
                'expense_category_id' => $category->id,
                'beneficiary_name' => 'شركة المقاولات المتحدة',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', [
            'title' => 'دفعة للمقاول',
            'beneficiary_name' => 'شركة المقاولات المتحدة',
        ]);
    }

    public function test_can_create_expense_with_attachment(): void
    {
        $category = ExpenseCategory::factory()->create();
        $file = UploadedFile::fake()->image('receipt.jpg');

        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'مصروف مع إيصال',
                'amount' => 250.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
                'expense_category_id' => $category->id,
                'attachment' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::where('title', 'مصروف مع إيصال')->first();
        $this->assertNotNull($expense);
        $this->assertNotNull($expense->attachment);
    }

    public function test_can_create_expense_with_full_details(): void
    {
        $category = ExpenseCategory::factory()->admin()->create();

        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'راتب موظف',
                'description' => 'راتب شهر يناير',
                'amount' => 5000.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
                'expense_category_id' => $category->id,
                'beneficiary_name' => 'أحمد محمد',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', [
            'title' => 'راتب موظف',
            'amount' => '5000.0000',
            'expense_category_id' => $category->id,
            'beneficiary_name' => 'أحمد محمد',
        ]);
    }

    public function test_title_is_required(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => '',
                'amount' => 500.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_amount_is_required(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'Test',
                'amount' => null,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['amount' => 'required']);
    }

    public function test_amount_must_be_positive(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'Test',
                'amount' => 0,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['amount']);
    }

    public function test_treasury_is_required(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'Test',
                'amount' => 500.00,
                'treasury_id' => null,
                'expense_date' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['treasury_id' => 'required']);
    }

    public function test_expense_date_is_required(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'Test',
                'amount' => 500.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['expense_date' => 'required']);
    }

    public function test_expense_category_is_required(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm([
                'title' => 'Test',
                'amount' => 500.00,
                'treasury_id' => $this->treasury->id,
                'expense_date' => now(),
                'expense_category_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['expense_category_id' => 'required']);
    }

    public function test_list_page_shows_expenses(): void
    {
        $expenses = Expense::factory()->count(3)->create([
            'treasury_id' => $this->treasury->id,
        ]);

        Livewire::test(ListExpenses::class)
            ->assertStatus(200)
            ->assertCanSeeTableRecords($expenses);
    }

    public function test_list_page_shows_category_column(): void
    {
        $category = ExpenseCategory::factory()->create(['name' => 'مصاريف تشغيلية']);
        $expense = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => $category->id,
        ]);

        Livewire::test(ListExpenses::class)
            ->assertCanSeeTableRecords([$expense]);
    }

    public function test_list_page_shows_beneficiary_column(): void
    {
        $expense = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'beneficiary_name' => 'محمد أحمد',
        ]);

        Livewire::test(ListExpenses::class)
            ->assertCanSeeTableRecords([$expense]);
    }

    public function test_can_filter_by_category(): void
    {
        $category = ExpenseCategory::factory()->create();
        $withCategory = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => $category->id,
        ]);
        $withoutCategory = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => null,
        ]);

        Livewire::test(ListExpenses::class)
            ->filterTable('expense_category_id', $category->id)
            ->assertCanSeeTableRecords([$withCategory])
            ->assertCanNotSeeTableRecords([$withoutCategory]);
    }

    public function test_can_filter_by_treasury(): void
    {
        $treasury2 = Treasury::factory()->create();
        TreasuryTransaction::create([
            'treasury_id' => $treasury2->id,
            'type' => 'income',
            'amount' => '100000.00',
            'description' => 'Initial balance',
        ]);

        $expense1 = Expense::factory()->create(['treasury_id' => $this->treasury->id]);
        $expense2 = Expense::factory()->create(['treasury_id' => $treasury2->id]);

        Livewire::test(ListExpenses::class)
            ->filterTable('treasury_id', $this->treasury->id)
            ->assertCanSeeTableRecords([$expense1])
            ->assertCanNotSeeTableRecords([$expense2]);
    }

    public function test_can_filter_by_attachment_presence(): void
    {
        $withAttachment = Expense::factory()->withAttachment()->create([
            'treasury_id' => $this->treasury->id,
        ]);
        $withoutAttachment = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'attachment' => null,
        ]);

        Livewire::test(ListExpenses::class)
            ->filterTable('has_attachment', true)
            ->assertCanSeeTableRecords([$withAttachment])
            ->assertCanNotSeeTableRecords([$withoutAttachment]);
    }

    public function test_can_search_by_title(): void
    {
        $expense1 = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'title' => 'فاتورة كهرباء',
        ]);
        $expense2 = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'title' => 'راتب موظف',
        ]);

        Livewire::test(ListExpenses::class)
            ->searchTable('كهرباء')
            ->assertCanSeeTableRecords([$expense1])
            ->assertCanNotSeeTableRecords([$expense2]);
    }

    public function test_can_search_by_beneficiary(): void
    {
        $expense1 = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'beneficiary_name' => 'شركة الكهرباء',
        ]);
        $expense2 = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'beneficiary_name' => 'محمد أحمد',
        ]);

        Livewire::test(ListExpenses::class)
            ->searchTable('الكهرباء')
            ->assertCanSeeTableRecords([$expense1])
            ->assertCanNotSeeTableRecords([$expense2]);
    }

    public function test_expense_belongs_to_category(): void
    {
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => $category->id,
        ]);

        $this->assertTrue($expense->expenseCategory->is($category));
    }

    public function test_expense_category_is_nullable(): void
    {
        $expense = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => null,
        ]);

        $this->assertNull($expense->expenseCategory);
    }

    public function test_expense_factory_with_category(): void
    {
        $expense = Expense::factory()->withCategory()->create([
            'treasury_id' => $this->treasury->id,
        ]);

        $this->assertNotNull($expense->expense_category_id);
        $this->assertInstanceOf(ExpenseCategory::class, $expense->expenseCategory);
    }

    public function test_expense_factory_with_beneficiary(): void
    {
        $expense = Expense::factory()->withBeneficiary('أحمد محمد')->create([
            'treasury_id' => $this->treasury->id,
        ]);

        $this->assertEquals('أحمد محمد', $expense->beneficiary_name);
    }

    public function test_expense_factory_with_attachment(): void
    {
        $expense = Expense::factory()->withAttachment()->create([
            'treasury_id' => $this->treasury->id,
        ]);

        $this->assertNotNull($expense->attachment);
        $this->assertStringContainsString('expenses/', $expense->attachment);
    }

    public function test_expense_factory_with_full_details(): void
    {
        $expense = Expense::factory()->withFullDetails()->create([
            'treasury_id' => $this->treasury->id,
        ]);

        $this->assertNotNull($expense->expense_category_id);
        $this->assertNotNull($expense->beneficiary_name);
        $this->assertNotNull($expense->attachment);
    }
}
