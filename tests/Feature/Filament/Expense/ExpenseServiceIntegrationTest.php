<?php

namespace Tests\Feature\Filament\Expense;

use App\Enums\ExpenseCategoryType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $service;

    protected Treasury $treasury;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TreasuryService();
        $this->treasury = Treasury::factory()->create();
        $this->user = User::factory()->create();

        // Add initial balance
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'income',
            'amount' => '50000.00',
            'description' => 'Initial balance',
        ]);
    }

    public function test_creates_expense_transaction_with_category(): void
    {
        $category = ExpenseCategory::factory()->operational()->create([
            'name' => 'مصاريف تشغيلية',
        ]);

        $expense = Expense::create([
            'title' => 'فاتورة كهرباء',
            'description' => 'فاتورة شهر يناير',
            'amount' => '1500.00',
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->user->id,
            'expense_category_id' => $category->id,
            'beneficiary_name' => 'شركة الكهرباء',
        ]);

        $this->service->postExpense($expense);

        // Verify treasury transaction was created
        $transaction = TreasuryTransaction::where('type', 'expense')
            ->where('reference_id', $expense->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('-1500.0000', $transaction->amount);

        // Verify treasury balance
        $balance = $this->service->getTreasuryBalance($this->treasury->id);
        $this->assertEquals('48500', $balance); // 50000 - 1500
    }

    public function test_creates_expense_transaction_with_beneficiary(): void
    {
        $expense = Expense::create([
            'title' => 'دفعة للمقاول',
            'amount' => '5000.00',
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->user->id,
            'beneficiary_name' => 'شركة البناء المتحدة',
        ]);

        $this->service->postExpense($expense);

        $transaction = TreasuryTransaction::where('type', 'expense')
            ->where('reference_id', $expense->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('-5000.0000', $transaction->amount);
    }

    public function test_creates_expense_transaction_with_attachment(): void
    {
        $expense = Expense::create([
            'title' => 'مصروف مع إيصال',
            'amount' => '250.00',
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->user->id,
            'attachment' => 'expenses/receipt-123.pdf',
        ]);

        $this->service->postExpense($expense);

        $transaction = TreasuryTransaction::where('type', 'expense')
            ->where('reference_id', $expense->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('-250.0000', $transaction->amount);
    }

    public function test_creates_expense_with_all_new_fields(): void
    {
        $category = ExpenseCategory::factory()->admin()->create();

        $expense = Expense::create([
            'title' => 'راتب موظف',
            'description' => 'راتب شهر يناير',
            'amount' => '8000.00',
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->user->id,
            'expense_category_id' => $category->id,
            'beneficiary_name' => 'أحمد محمد علي',
            'attachment' => 'expenses/salary-slip-jan.pdf',
        ]);

        $this->service->postExpense($expense);

        // Verify expense was created correctly
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'title' => 'راتب موظف',
            'expense_category_id' => $category->id,
            'beneficiary_name' => 'أحمد محمد علي',
            'attachment' => 'expenses/salary-slip-jan.pdf',
        ]);

        // Verify treasury transaction
        $transaction = TreasuryTransaction::where('type', 'expense')
            ->where('reference_id', $expense->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('-8000.0000', $transaction->amount);
    }

    public function test_expense_category_relationship(): void
    {
        $category = ExpenseCategory::factory()->marketing()->create([
            'name' => 'إعلانات ودعاية',
        ]);

        $expense = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => $category->id,
        ]);

        $this->assertTrue($expense->expenseCategory->is($category));
        $this->assertEquals('إعلانات ودعاية', $expense->expenseCategory->name);
        $this->assertEquals(ExpenseCategoryType::MARKETING, $expense->expenseCategory->type);
    }

    public function test_expense_category_has_many_expenses(): void
    {
        $category = ExpenseCategory::factory()->create();

        $expense1 = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => $category->id,
        ]);

        $expense2 = Expense::factory()->create([
            'treasury_id' => $this->treasury->id,
            'expense_category_id' => $category->id,
        ]);

        $this->assertCount(2, $category->expenses);
        $this->assertTrue($category->expenses->contains($expense1));
        $this->assertTrue($category->expenses->contains($expense2));
    }

    public function test_expense_without_category_still_works(): void
    {
        $expense = Expense::create([
            'title' => 'مصروف بدون تصنيف',
            'amount' => '100.00',
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->user->id,
            'expense_category_id' => null,
        ]);

        $this->service->postExpense($expense);

        $transaction = TreasuryTransaction::where('type', 'expense')
            ->where('reference_id', $expense->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertNull($expense->expenseCategory);
    }

    public function test_multiple_expenses_in_same_category(): void
    {
        $category = ExpenseCategory::factory()->operational()->create();

        $expenses = collect([
            Expense::factory()->create([
                'treasury_id' => $this->treasury->id,
                'expense_category_id' => $category->id,
                'amount' => '100.00',
            ]),
            Expense::factory()->create([
                'treasury_id' => $this->treasury->id,
                'expense_category_id' => $category->id,
                'amount' => '200.00',
            ]),
            Expense::factory()->create([
                'treasury_id' => $this->treasury->id,
                'expense_category_id' => $category->id,
                'amount' => '300.00',
            ]),
        ]);

        $category->refresh();
        $this->assertCount(3, $category->expenses);

        // Verify total
        $total = $category->expenses->sum('amount');
        $this->assertEquals(600.0, $total);
    }

    public function test_expense_category_types_are_correct(): void
    {
        $operational = ExpenseCategory::factory()->operational()->create();
        $admin = ExpenseCategory::factory()->admin()->create();
        $marketing = ExpenseCategory::factory()->marketing()->create();

        $this->assertEquals(ExpenseCategoryType::OPERATIONAL, $operational->type);
        $this->assertEquals(ExpenseCategoryType::ADMIN, $admin->type);
        $this->assertEquals(ExpenseCategoryType::MARKETING, $marketing->type);
    }

    public function test_active_scope_filters_inactive_categories(): void
    {
        ExpenseCategory::factory()->count(2)->create(['is_active' => true]);
        ExpenseCategory::factory()->count(3)->inactive()->create();

        $this->assertCount(2, ExpenseCategory::active()->get());
    }

    public function test_expense_category_soft_deletes(): void
    {
        $category = ExpenseCategory::factory()->create();

        $category->delete();

        $this->assertSoftDeleted('expense_categories', ['id' => $category->id]);
    }
}
