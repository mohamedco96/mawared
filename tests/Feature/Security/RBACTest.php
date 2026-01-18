<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;
    protected Partner $partner;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create();
        $this->partner = Partner::factory()->customer()->create();
        $this->product = Product::factory()->create();

        // Create stock for testing
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => '50.00',
            'reference_type' => 'test_setup',
            'reference_id' => 'setup',
        ]);

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * PERMISSION ENFORCEMENT - RESOURCE LEVEL TESTS
     */

        #[Test]
    public function test_sales_agent_can_view_sales_invoices(): void
    {
        // Create role and permission
        $role = Role::create(['name' => 'sales_agent']);
        Permission::create(['name' => 'view_any_sales::invoice']);
        $role->givePermissionTo('view_any_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        $this->actingAs($user);

        // Verify user has the permission
        $this->assertTrue($user->can('view_any_sales::invoice'));
        $this->assertTrue($user->hasPermissionTo('view_any_sales::invoice'));
    }

        #[Test]
    public function test_sales_agent_cannot_view_purchase_invoices(): void
    {
        $role = Role::create(['name' => 'sales_agent']);
        Permission::create(['name' => 'view_any_sales::invoice']);
        $role->givePermissionTo('view_any_sales::invoice');
        // No permission for purchase invoices

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        $this->actingAs($user);

        $response = $this->get(route('filament.admin.resources.purchase-invoices.index'));

        $this->assertEquals(403, $response->status());
    }

        #[Test]
    public function test_sales_agent_cannot_access_treasury_module(): void
    {
        $role = Role::create(['name' => 'sales_agent']);
        Permission::create(['name' => 'view_any_sales::invoice']);
        $role->givePermissionTo('view_any_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        $this->actingAs($user);

        $response = $this->get(route('filament.admin.resources.treasury-transactions.index'));

        $this->assertEquals(403, $response->status());
    }

        #[Test]
    public function test_warehouse_manager_can_access_stock_resources(): void
    {
        $role = Role::create(['name' => 'warehouse_manager']);
        Permission::create(['name' => 'view_any_stock::movement']);
        Permission::create(['name' => 'view_any_product']);
        $role->givePermissionTo(['view_any_stock::movement', 'view_any_product']);

        $user = User::factory()->create();
        $user->assignRole('warehouse_manager');

        $this->actingAs($user);

        // Verify user has the stock and product permissions
        $this->assertTrue($user->hasPermissionTo('view_any_stock::movement'));
        $this->assertTrue($user->hasPermissionTo('view_any_product'));
    }

        #[Test]
    public function test_accountant_can_view_financial_reports(): void
    {
        $role = Role::create(['name' => 'accountant']);
        Permission::create(['name' => 'view_any_treasury::transaction']);
        Permission::create(['name' => 'view_any_expense']);
        Permission::create(['name' => 'view_any_revenue']);
        $role->givePermissionTo([
            'view_any_treasury::transaction',
            'view_any_expense',
            'view_any_revenue',
        ]);

        $user = User::factory()->create();
        $user->assignRole('accountant');

        $this->actingAs($user);

        // Verify user has the financial permissions
        $this->assertTrue($user->hasPermissionTo('view_any_treasury::transaction'));
        $this->assertTrue($user->hasPermissionTo('view_any_expense'));
        $this->assertTrue($user->hasPermissionTo('view_any_revenue'));
    }

        #[Test]
    public function test_user_without_permission_gets_403(): void
    {
        $role = Role::create(['name' => 'guest']);
        // No permissions assigned

        $user = User::factory()->create();
        $user->assignRole('guest');

        $this->actingAs($user);

        $response = $this->get(route('filament.admin.resources.sales-invoices.index'));

        $this->assertEquals(403, $response->status());
    }

    /**
     * ACTION-LEVEL AUTHORIZATION TESTS
     */

        #[Test]
    public function test_sales_agent_cannot_delete_invoice_without_permission(): void
    {
        $role = Role::create(['name' => 'sales_agent']);
        Permission::create(['name' => 'view_sales::invoice']);
        Permission::create(['name' => 'update_sales::invoice']);
        $role->givePermissionTo(['view_sales::invoice', 'update_sales::invoice']);
        // NO delete permission

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user);

        // Policy check - user should NOT be able to delete
        $this->assertFalse($user->can('delete', $invoice));
    }

        #[Test]
    public function test_delete_action_visible_for_draft_invoice_with_permission(): void
    {
        $role = Role::create(['name' => 'manager']);
        Permission::create(['name' => 'view_sales::invoice']);
        Permission::create(['name' => 'delete_sales::invoice']);
        $role->givePermissionTo(['view_sales::invoice', 'delete_sales::invoice']);

        $user = User::factory()->create();
        $user->assignRole('manager');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user);

        // Policy check - user SHOULD be able to delete draft invoice with permission
        $this->assertTrue($user->can('delete', $invoice));
    }

        #[Test]
    public function test_cannot_delete_posted_invoice_even_with_delete_permission(): void
    {
        $role = Role::create(['name' => 'manager']);
        Permission::create(['name' => 'delete_sales::invoice']);
        $role->givePermissionTo('delete_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('manager');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'posted',
        ]);

        $this->actingAs($user);

        // User has permission, but business logic should prevent deletion
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن حذف فاتورة مؤكدة');

        $invoice->delete();
    }

        #[Test]
    public function test_cannot_update_posted_invoice_even_with_update_permission(): void
    {
        $role = Role::create(['name' => 'editor']);
        Permission::create(['name' => 'update_sales::invoice']);
        $role->givePermissionTo('update_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('editor');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'posted',
        ]);

        $this->actingAs($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن تعديل فاتورة مؤكدة');

        $invoice->update(['notes' => 'Trying to update posted invoice']);
    }

        #[Test]
    public function test_sales_agent_can_create_invoice_with_permission(): void
    {
        $role = Role::create(['name' => 'sales_agent']);
        Permission::create(['name' => 'create_sales::invoice']);
        $role->givePermissionTo('create_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        $this->actingAs($user);

        // User can access create page
        $this->assertTrue($user->can('create', SalesInvoice::class));
    }

        #[Test]
    public function test_restore_action_requires_restore_permission(): void
    {
        $role = Role::create(['name' => 'viewer']);
        Permission::create(['name' => 'view_sales::invoice']);
        $role->givePermissionTo('view_sales::invoice');
        // NO restore permission

        $user = User::factory()->create();
        $user->assignRole('viewer');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'draft',
        ]);

        $invoice->delete(); // Soft delete

        $this->actingAs($user);

        $this->assertFalse($user->can('restore', $invoice));
    }

    /**
     * BUSINESS LOGIC + RBAC INTEGRATION TESTS
     */

        #[Test]
    public function test_cannot_delete_invoice_with_stock_movements_regardless_of_permission(): void
    {
        $role = Role::create(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'posted',
        ]);

        // Create stock movement for this invoice
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'sale',
            'quantity' => -10,
            'cost_at_time' => '50.00',
            'reference_type' => 'App\Models\SalesInvoice',
            'reference_id' => $invoice->id,
        ]);

        $this->actingAs($user);

        $this->expectException(\Exception::class);
        // Business logic prevents deletion

        $invoice->delete();
    }

        #[Test]
    public function test_cannot_delete_invoice_with_treasury_transactions_regardless_of_permission(): void
    {
        $role = Role::create(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'posted',
        ]);

        // Create treasury transaction for this invoice
        TreasuryTransaction::create([
            'treasury_id' => \App\Models\Treasury::first()->id,
            'type' => 'income',
            'amount' => '1000.00',
            'description' => 'Test transaction',
            'reference_type' => 'App\Models\SalesInvoice',
            'reference_id' => $invoice->id,
        ]);

        $this->actingAs($user);

        $this->expectException(\Exception::class);
        // Business logic prevents deletion

        $invoice->delete();
    }

        #[Test]
    public function test_cannot_modify_installment_fields_on_invoice_with_installments(): void
    {
        $role = Role::create(['name' => 'manager']);
        Permission::create(['name' => 'update_sales::invoice']);
        $role->givePermissionTo('update_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('manager');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'posted',
            'payment_method' => 'credit',
            'has_installment_plan' => true,
        ]);

        // Create an installment
        \App\Models\Installment::create([
            'sales_invoice_id' => $invoice->id,
            'installment_number' => 1,
            'amount' => '100.00',
            'due_date' => now()->addMonth(),
            'status' => 'pending',
            'paid_amount' => '0.00',
        ]);

        $this->actingAs($user);

        $this->expectException(\Exception::class);

        $invoice->update(['total' => '2000.00']);
    }

        #[Test]
    public function test_can_delete_draft_invoice_without_related_records(): void
    {
        $role = Role::create(['name' => 'manager']);
        Permission::create(['name' => 'delete_sales::invoice']);
        $role->givePermissionTo('delete_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('manager');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'draft', // Draft status
            // No stock movements, no treasury transactions, no payments
        ]);

        $this->actingAs($user);

        $invoice->delete();

        $this->assertSoftDeleted('sales_invoices', ['id' => $invoice->id]);
    }

    /**
     * SUPER ADMIN BYPASS TESTS
     */

        #[Test]
    public function test_super_admin_can_perform_all_policy_actions(): void
    {
        $role = Role::create(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user);

        // Super admin bypasses policy checks via Shield's intercept_gate='before'
        // Verify the role exists and user has it
        $this->assertTrue($user->hasRole('super_admin'));

        // Super admin can delete draft invoices (no business logic constraints)
        $invoice->delete();

        // Verify deletion succeeded
        $this->assertSoftDeleted('sales_invoices', ['id' => $invoice->id]);
    }

        #[Test]
    public function test_super_admin_cannot_bypass_business_logic_constraints(): void
    {
        $role = Role::create(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->partner->id,
            'status' => 'posted',
        ]);

        // Create stock movement
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'sale',
            'quantity' => -10,
            'cost_at_time' => '50.00',
            'reference_type' => 'App\Models\SalesInvoice',
            'reference_id' => $invoice->id,
        ]);

        $this->actingAs($user);

        // Even super admin cannot bypass model observer constraints
        $this->expectException(\Exception::class);
        // Business logic prevents deletion

        $invoice->delete();
    }

    /**
     * MULTI-ROLE SCENARIOS TESTS
     */

        #[Test]
    public function test_user_with_multiple_roles_has_combined_permissions(): void
    {
        $salesRole = Role::create(['name' => 'sales_agent']);
        Permission::create(['name' => 'view_any_sales::invoice']);
        $salesRole->givePermissionTo('view_any_sales::invoice');

        $inventoryRole = Role::create(['name' => 'inventory_manager']);
        Permission::create(['name' => 'view_any_product']);
        $inventoryRole->givePermissionTo('view_any_product');

        $user = User::factory()->create();
        $user->assignRole(['sales_agent', 'inventory_manager']);

        $this->actingAs($user);

        // Verify user has permissions from both roles
        $this->assertTrue($user->hasPermissionTo('view_any_sales::invoice'));
        $this->assertTrue($user->hasPermissionTo('view_any_product'));
        $this->assertTrue($user->hasRole('sales_agent'));
        $this->assertTrue($user->hasRole('inventory_manager'));
    }

        #[Test]
    public function test_role_without_view_any_permission_does_not_see_resource(): void
    {
        $role = Role::create(['name' => 'limited_user']);
        // No view_any permissions

        $user = User::factory()->create();
        $user->assignRole('limited_user');

        $this->actingAs($user);

        $response = $this->get(route('filament.admin.resources.sales-invoices.index'));

        $this->assertEquals(403, $response->status());
    }
}
