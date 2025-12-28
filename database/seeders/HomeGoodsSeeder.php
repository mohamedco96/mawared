<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TreasuryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HomeGoodsSeeder extends Seeder
{
    private $warehouse;
    private $treasury;
    private $user;
    private $pieceUnit;

    // Products
    private $products = [];

    // Partners
    private $shareholderOwner;
    private $supplierAlNour;
    private $supplierElGarhy;
    private $customerHanna;
    private $customerSmartKitchens;

    // Tracking
    private $expectedTreasuryBalance = 0;
    private $expectedStock = [];
    private $transactions = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->printHeader('🏠 HOME GOODS BUSINESS SEEDER');

        // Phase 1: Foundation
        $this->setupFoundation();
        $this->injectCapital();

        // Phase 2: Product Catalog
        $this->createProductCatalog();

        // Phase 3: Purchasing (Stock In)
        $this->purchasePhase();

        // Phase 4: Sales (Revenue)
        $this->salesPhase();

        // Phase 5: Returns & Operations
        $this->returnsAndOperations();

        // Final Verification
        $this->printVerificationReport();
    }

    /**
     * 1. Setup Foundation - Ensure required entities exist
     */
    private function setupFoundation(): void
    {
        $this->printStep('1️⃣  FOUNDATION SETUP');

        // Get or create admin user
        $this->user = User::where('email', 'admin@test.com')->first();
        if (!$this->user) {
            $this->user = User::create([
                'name' => 'Mohamed Ibrahim',
                'email' => 'admin@test.com',
                'password' => bcrypt('12345678'),
            ]);
            $this->log('Created User: Mohamed Ibrahim (admin@test.com)', 'success');
        } else {
            $this->log('Using existing User: ' . $this->user->name, 'info');
        }

        // Get warehouse
        $this->warehouse = Warehouse::where('code', 'WH-CAI-001')->first();
        if (!$this->warehouse) {
            $this->warehouse = Warehouse::create([
                'name' => 'المخزن الرئيسي - القاهرة',
                'code' => 'WH-CAI-001',
                'address' => 'القاهرة - مصر الجديدة',
            ]);
            $this->log('Created Main Warehouse', 'success');
        } else {
            $this->log('Using existing Warehouse: ' . $this->warehouse->name, 'info');
        }

        // Get treasury
        $this->treasury = Treasury::first();
        if (!$this->treasury) {
            $this->treasury = Treasury::create([
                'name' => 'الخزينة الرئيسية',
                'type' => 'cash',
                'description' => 'الخزينة النقدية الرئيسية',
            ]);
            $this->log('Created Main Treasury', 'success');
        } else {
            $this->log('Using existing Treasury: ' . $this->treasury->name, 'info');
        }

        // Get piece unit
        $this->pieceUnit = Unit::where('name', 'قطعة')->first();
        if (!$this->pieceUnit) {
            $this->pieceUnit = Unit::create([
                'name' => 'قطعة',
                'symbol' => 'pc',
            ]);
            $this->log('Created Piece Unit', 'success');
        } else {
            $this->log('Using existing Unit: ' . $this->pieceUnit->name, 'info');
        }

        echo "\n";
    }

    /**
     * 1.1. Inject Capital - Create initial treasury deposit
     */
    private function injectCapital(): void
    {
        $this->printStep('💰 CAPITAL INJECTION');

        // Create shareholder partner
        $this->shareholderOwner = Partner::create([
            'name' => 'Mohamed Ibrahim - Business Owner',
            'phone' => '01000000000',
            'type' => 'shareholder',
            'region' => 'القاهرة',
            'current_balance' => 0,
        ]);
        $this->log('Created Shareholder: ' . $this->shareholderOwner->name, 'info');

        $capitalAmount = 1000000.00;

        // Create capital deposit transaction linked to shareholder
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'capital_deposit',
            'amount' => $capitalAmount,
            'description' => 'رأس المال الأولي - إيداع تأسيسي من الشريك المؤسس',
            'partner_id' => $this->shareholderOwner->id,
            'reference_type' => 'shareholder_capital',
            'reference_id' => null,
        ]);

        // Update shareholder balance
        $this->shareholderOwner->recalculateBalance();

        $this->expectedTreasuryBalance += $capitalAmount;
        $this->transactions[] = ['type' => 'Capital Injection', 'amount' => $capitalAmount, 'balance' => $this->expectedTreasuryBalance];

        $this->log("Deposited {$this->formatMoney($capitalAmount)} as initial capital from {$this->shareholderOwner->name}", 'success');
        echo "\n";
    }

    /**
     * 2. Create Product Catalog - Home Appliances & Household Products
     */
    private function createProductCatalog(): void
    {
        $this->printStep('2️⃣  PRODUCT CATALOG CREATION');

        $productsData = [
            [
                'name' => 'طقم جرانيت 10 قطع',
                'sku' => 'HG-COOK-GRANITE-10',
                'avg_cost' => 4500.00,
                'retail_price' => 6000.00,
                'wholesale_price' => 5500.00,
                'min_stock' => 10,
            ],
            [
                'name' => 'خلاط يدوي 800 واط',
                'sku' => 'HG-BLEND-HAND-800',
                'avg_cost' => 800.00,
                'retail_price' => 1200.00,
                'wholesale_price' => 1100.00,
                'min_stock' => 20,
            ],
            [
                'name' => 'مكواة بخار 2200 واط',
                'sku' => 'HG-IRON-STEAM-2200',
                'avg_cost' => 1500.00,
                'retail_price' => 2100.00,
                'wholesale_price' => 1900.00,
                'min_stock' => 15,
            ],
            [
                'name' => 'قلاية هوائية 5 لتر',
                'sku' => 'HG-FRYER-AIR-5L',
                'avg_cost' => 3000.00,
                'retail_price' => 4200.00,
                'wholesale_price' => 3900.00,
                'min_stock' => 8,
            ],
            [
                'name' => 'ماكينة قهوة إسبريسو',
                'sku' => 'HG-COFFEE-ESPRESSO',
                'avg_cost' => 5500.00,
                'retail_price' => 7500.00,
                'wholesale_price' => 7000.00,
                'min_stock' => 5,
            ],
            [
                'name' => 'مكنسة كهربائية 2000 واط',
                'sku' => 'HG-VAC-CLEAN-2000',
                'avg_cost' => 2200.00,
                'retail_price' => 3200.00,
                'wholesale_price' => 2900.00,
                'min_stock' => 12,
            ],
            [
                'name' => 'منظم أدراج بلاستيك كبير',
                'sku' => 'HG-ORG-DRAWER-LG',
                'avg_cost' => 150.00,
                'retail_price' => 280.00,
                'wholesale_price' => 250.00,
                'min_stock' => 50,
            ],
            [
                'name' => 'صينية فرن مستطيل',
                'sku' => 'HG-TRAY-OVEN-RECT',
                'avg_cost' => 120.00,
                'retail_price' => 220.00,
                'wholesale_price' => 200.00,
                'min_stock' => 40,
            ],
            [
                'name' => 'محضر طعام متعدد الوظائف',
                'sku' => 'HG-PROC-FOOD-MULTI',
                'avg_cost' => 3500.00,
                'retail_price' => 5000.00,
                'wholesale_price' => 4600.00,
                'min_stock' => 6,
            ],
            [
                'name' => 'طقم سكاكين مطبخ احترافي 6 قطع',
                'sku' => 'HG-KNIFE-SET-PRO-6',
                'avg_cost' => 450.00,
                'retail_price' => 750.00,
                'wholesale_price' => 680.00,
                'min_stock' => 30,
            ],
        ];

        foreach ($productsData as $data) {
            // Check if product already exists
            $existing = Product::where('sku', $data['sku'])->first();

            if ($existing) {
                $product = $existing;
                $this->log("Using existing: {$product->name}", 'info');
            } else {
                $product = Product::create([
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'min_stock' => $data['min_stock'],
                    'avg_cost' => $data['avg_cost'],
                    'small_unit_id' => $this->pieceUnit->id,
                    'large_unit_id' => null,
                    'factor' => 1,
                    'retail_price' => $data['retail_price'],
                    'wholesale_price' => $data['wholesale_price'],
                ]);

                $this->log("Created: {$product->name} (Cost: {$this->formatMoney($product->avg_cost)}, Sell: {$this->formatMoney($product->retail_price)})", 'info');
            }

            $this->products[$data['sku']] = $product;
            $this->expectedStock[$product->id] = 0;
        }

        $this->log('Products ready: ' . count($productsData), 'success');
        echo "\n";
    }

    /**
     * 3. Purchasing Phase - Buy inventory from suppliers
     */
    private function purchasePhase(): void
    {
        $this->printStep('3️⃣  PURCHASING PHASE (Stock In)');

        // Create Suppliers
        $this->supplierAlNour = Partner::create([
            'name' => 'Al-Nour Trading',
            'phone' => '01012345678',
            'type' => 'supplier',
            'region' => 'القاهرة',
            'current_balance' => 0,
        ]);
        $this->log('Created Supplier: Al-Nour Trading', 'info');

        $this->supplierElGarhy = Partner::create([
            'name' => 'El-Garhy Appliances',
            'phone' => '01087654321',
            'type' => 'supplier',
            'region' => 'الجيزة',
            'current_balance' => 0,
        ]);
        $this->log('Created Supplier: El-Garhy Appliances', 'info');
        echo "\n";

        // Purchase 1: Cash - Air Fryers + Blenders from Al-Nour
        $this->createPurchaseInvoice(
            supplier: $this->supplierAlNour,
            items: [
                ['product' => $this->products['HG-FRYER-AIR-5L'], 'quantity' => 20],
                ['product' => $this->products['HG-BLEND-HAND-800'], 'quantity' => 20],
            ],
            paymentMethod: 'cash',
            paidPercentage: 100,
            description: 'Purchase Invoice #1 - Full Cash Payment'
        );

        // Purchase 2: Credit - Cookware Sets from El-Garhy
        $this->createPurchaseInvoice(
            supplier: $this->supplierElGarhy,
            items: [
                ['product' => $this->products['HG-COOK-GRANITE-10'], 'quantity' => 50],
            ],
            paymentMethod: 'credit',
            paidPercentage: 0,
            description: 'Purchase Invoice #2 - Deferred Payment'
        );

        echo "\n";
    }

    /**
     * 4. Sales Phase - Sell to customers
     */
    private function salesPhase(): void
    {
        $this->printStep('4️⃣  SALES PHASE (Revenue Generation)');

        // Create Customers
        $this->customerHanna = Partner::create([
            'name' => 'Mrs. Hanna',
            'phone' => '01098765432',
            'type' => 'customer',
            'region' => 'المعادي',
            'current_balance' => 0,
        ]);
        $this->log('Created Customer: Mrs. Hanna', 'info');

        $this->customerSmartKitchens = Partner::create([
            'name' => 'Smart Kitchens Co.',
            'phone' => '01123456789',
            'type' => 'customer',
            'region' => 'التجمع الخامس',
            'current_balance' => 0,
        ]);
        $this->log('Created Customer: Smart Kitchens Co.', 'info');
        echo "\n";

        // Sale 1: Full Cash - Air Fryers + Blenders to Mrs. Hanna
        $this->createSalesInvoice(
            customer: $this->customerHanna,
            items: [
                ['product' => $this->products['HG-FRYER-AIR-5L'], 'quantity' => 5],
                ['product' => $this->products['HG-BLEND-HAND-800'], 'quantity' => 5],
            ],
            paymentMethod: 'cash',
            paidPercentage: 100,
            description: 'Sales Invoice #1 - Full Cash'
        );

        // Sale 2: Partial Payment - Cookware Sets to Smart Kitchens Co.
        $this->createSalesInvoice(
            customer: $this->customerSmartKitchens,
            items: [
                ['product' => $this->products['HG-COOK-GRANITE-10'], 'quantity' => 20],
            ],
            paymentMethod: 'credit',
            paidPercentage: 50,
            description: 'Sales Invoice #2 - 50% Paid, 50% Credit'
        );

        echo "\n";
    }

    /**
     * 5. Returns & Operations - Handle returns and expenses
     */
    private function returnsAndOperations(): void
    {
        $this->printStep('5️⃣  RETURNS & OPERATIONS');

        // Sales Return: Mrs. Hanna returns 1 Blender (Defective)
        $this->createSalesReturn(
            customer: $this->customerHanna,
            items: [
                ['product' => $this->products['HG-BLEND-HAND-800'], 'quantity' => 1],
            ],
            paymentMethod: 'cash',
            description: 'Sales Return #1 - Defective Blender'
        );

        // Expense: Store Rent
        $this->createExpense(
            amount: 5000.00,
            title: 'إيجار المحل - شهر يناير',
            description: 'إيجار شهري للمحل التجاري'
        );

        echo "\n";
    }

    /**
     * Create Purchase Invoice and Post it
     */
    private function createPurchaseInvoice(Partner $supplier, array $items, string $paymentMethod, float $paidPercentage, string $description): void
    {
        $invoiceNumber = 'INV-PUR-' . str_pad(PurchaseInvoice::count() + 1, 5, '0', STR_PAD_LEFT);

        // Create invoice as draft
        $invoice = PurchaseInvoice::create([
            'invoice_number' => $invoiceNumber,
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => $paymentMethod,
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'notes' => $description,
            'created_by' => $this->user->id,
        ]);

        $subtotal = 0;
        $stockChanges = [];

        foreach ($items as $itemData) {
            $product = $itemData['product'];
            $quantity = $itemData['quantity'];
            $unitCost = $product->avg_cost;
            $itemTotal = $unitCost * $quantity;

            PurchaseInvoiceItem::create([
                'purchase_invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'unit_type' => 'small',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'discount' => 0,
                'total' => $itemTotal,
                'new_selling_price' => null,
                'new_large_selling_price' => null,
            ]);

            $subtotal += $itemTotal;
            $stockChanges[$product->id] = ($stockChanges[$product->id] ?? 0) + $quantity;
        }

        $total = $subtotal;
        $paidAmount = $total * ($paidPercentage / 100);

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => $total,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $total - $paidAmount,
        ]);

        // Post the invoice
        $this->postInvoice($invoice);

        // Update tracking
        $this->expectedTreasuryBalance -= $paidAmount;
        foreach ($stockChanges as $productId => $qty) {
            $this->expectedStock[$productId] = ($this->expectedStock[$productId] ?? 0) + $qty;
        }

        $this->transactions[] = [
            'type' => 'Purchase (Payment)',
            'amount' => -$paidAmount,
            'balance' => $this->expectedTreasuryBalance
        ];

        $this->log("✓ {$invoiceNumber} - Supplier: {$supplier->name}", 'success');
        $this->log("  Total: {$this->formatMoney($total)} | Paid: {$this->formatMoney($paidAmount)} | Debt: {$this->formatMoney($total - $paidAmount)}", 'info');
    }

    /**
     * Create Sales Invoice and Post it
     */
    private function createSalesInvoice(Partner $customer, array $items, string $paymentMethod, float $paidPercentage, string $description): void
    {
        $invoiceNumber = 'INV-SAL-' . str_pad(SalesInvoice::count() + 1, 5, '0', STR_PAD_LEFT);

        // Create invoice as draft
        $invoice = SalesInvoice::create([
            'invoice_number' => $invoiceNumber,
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => $paymentMethod,
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'notes' => $description,
            'created_by' => $this->user->id,
        ]);

        $subtotal = 0;
        $stockChanges = [];
        $profit = 0;

        foreach ($items as $itemData) {
            $product = $itemData['product'];
            $quantity = $itemData['quantity'];
            $unitPrice = $product->retail_price;
            $itemTotal = $unitPrice * $quantity;

            SalesInvoiceItem::create([
                'sales_invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'unit_type' => 'small',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'total' => $itemTotal,
            ]);

            $subtotal += $itemTotal;
            $stockChanges[$product->id] = ($stockChanges[$product->id] ?? 0) - $quantity;
            $profit += ($unitPrice - $product->avg_cost) * $quantity;
        }

        $total = $subtotal;
        $paidAmount = $total * ($paidPercentage / 100);

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => $total,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $total - $paidAmount,
        ]);

        // Post the invoice
        $this->postInvoice($invoice);

        // Update tracking
        $this->expectedTreasuryBalance += $paidAmount;
        foreach ($stockChanges as $productId => $qty) {
            $this->expectedStock[$productId] = ($this->expectedStock[$productId] ?? 0) + $qty;
        }

        $this->transactions[] = [
            'type' => 'Sale (Collection)',
            'amount' => $paidAmount,
            'balance' => $this->expectedTreasuryBalance
        ];

        $this->log("✓ {$invoiceNumber} - Customer: {$customer->name}", 'success');
        $this->log("  Total: {$this->formatMoney($total)} | Paid: {$this->formatMoney($paidAmount)} | Credit: {$this->formatMoney($total - $paidAmount)} | Profit: {$this->formatMoney($profit)}", 'info');
    }

    /**
     * Create Sales Return and Post it
     */
    private function createSalesReturn(Partner $customer, array $items, string $paymentMethod, string $description): void
    {
        $returnNumber = 'RET-SAL-' . str_pad(SalesReturn::count() + 1, 5, '0', STR_PAD_LEFT);

        // Create return as draft
        $return = SalesReturn::create([
            'return_number' => $returnNumber,
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $customer->id,
            'sales_invoice_id' => null,
            'status' => 'draft',
            'payment_method' => $paymentMethod,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'notes' => $description,
            'created_by' => $this->user->id,
        ]);

        $subtotal = 0;
        $stockChanges = [];

        foreach ($items as $itemData) {
            $product = $itemData['product'];
            $quantity = $itemData['quantity'];
            $unitPrice = $product->retail_price;
            $itemTotal = $unitPrice * $quantity;

            SalesReturnItem::create([
                'sales_return_id' => $return->id,
                'product_id' => $product->id,
                'unit_type' => 'small',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'total' => $itemTotal,
            ]);

            $subtotal += $itemTotal;
            $stockChanges[$product->id] = ($stockChanges[$product->id] ?? 0) + $quantity;
        }

        $return->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);

        // Post the return
        DB::transaction(function () use ($return) {
            app(StockService::class)->postSalesReturn($return);
            app(TreasuryService::class)->postSalesReturn($return);
            $return->status = 'posted';
            $return->saveQuietly();
            $return->partner->recalculateBalance();
        });

        // Update tracking
        $this->expectedTreasuryBalance -= $subtotal; // Cash refund
        foreach ($stockChanges as $productId => $qty) {
            $this->expectedStock[$productId] = ($this->expectedStock[$productId] ?? 0) + $qty;
        }

        $this->transactions[] = [
            'type' => 'Sales Return (Refund)',
            'amount' => -$subtotal,
            'balance' => $this->expectedTreasuryBalance
        ];

        $this->log("✓ {$returnNumber} - Customer: {$customer->name} | Refund: {$this->formatMoney($subtotal)}", 'success');
    }

    /**
     * Create Expense
     */
    private function createExpense(float $amount, string $title, string $description): void
    {
        $expense = Expense::create([
            'treasury_id' => $this->treasury->id,
            'title' => $title,
            'description' => $description,
            'amount' => $amount,
            'expense_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // Post expense
        app(TreasuryService::class)->postExpense($expense);

        $this->expectedTreasuryBalance -= $amount;
        $this->transactions[] = [
            'type' => 'Expense',
            'amount' => -$amount,
            'balance' => $this->expectedTreasuryBalance
        ];

        $this->log("✓ Expense: {$title} | Amount: {$this->formatMoney($amount)}", 'success');
    }

    /**
     * Post Invoice using Services (The Golden Rule)
     */
    private function postInvoice($invoice): void
    {
        DB::transaction(function () use ($invoice) {
            if ($invoice instanceof PurchaseInvoice) {
                app(StockService::class)->postPurchaseInvoice($invoice);
                app(TreasuryService::class)->postPurchaseInvoice($invoice);
            } elseif ($invoice instanceof SalesInvoice) {
                app(StockService::class)->postSalesInvoice($invoice);
                app(TreasuryService::class)->postSalesInvoice($invoice);
            }

            $invoice->status = 'posted';
            $invoice->saveQuietly();
            $invoice->partner->recalculateBalance();
        });
    }

    /**
     * Print Final Verification Report
     */
    private function printVerificationReport(): void
    {
        $this->printHeader('📊 VERIFICATION REPORT');

        // Treasury Balance Verification
        $actualTreasuryBalance = floatval(app(TreasuryService::class)->getTreasuryBalance($this->treasury->id));

        echo "\n┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ TREASURY BALANCE VERIFICATION                               │\n";
        echo "├─────────────────────────────────────────────────────────────┤\n";
        printf("│ Expected: %-49s │\n", $this->formatMoney($this->expectedTreasuryBalance));
        printf("│ Actual:   %-49s │\n", $this->formatMoney($actualTreasuryBalance));
        $diff = abs($actualTreasuryBalance - $this->expectedTreasuryBalance);
        $status = $diff < 0.01 ? '✓ PASS' : '✗ FAIL';
        printf("│ Status:   %-49s │\n", $status);
        echo "└─────────────────────────────────────────────────────────────┘\n\n";

        // Stock Verification
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ STOCK VERIFICATION                                          │\n";
        echo "├─────────────────────────────────────────────────────────────┤\n";

        $stockService = app(StockService::class);
        $stockMatches = true;

        foreach ($this->expectedStock as $productId => $expectedQty) {
            $actualQty = $stockService->getCurrentStock($this->warehouse->id, $productId);
            $product = Product::find($productId);
            $match = $actualQty == $expectedQty ? '✓' : '✗';

            if ($actualQty != $expectedQty) {
                $stockMatches = false;
            }

            printf("│ %-3s %-35s E:%-5d A:%-5d │\n",
                $match,
                substr($product->name, 0, 35),
                $expectedQty,
                $actualQty
            );
        }

        echo "└─────────────────────────────────────────────────────────────┘\n\n";

        // Transaction Log
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ TRANSACTION LOG                                             │\n";
        echo "├─────────────────────────────────────────────────────────────┤\n";

        foreach ($this->transactions as $transaction) {
            $amountStr = $this->formatMoney($transaction['amount']);
            $balanceStr = $this->formatMoney($transaction['balance']);
            printf("│ %-30s %13s → %13s │\n",
                $transaction['type'],
                $amountStr,
                $balanceStr
            );
        }

        echo "└─────────────────────────────────────────────────────────────┘\n\n";

        // Partner Balances
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ PARTNER BALANCES                                            │\n";
        echo "├─────────────────────────────────────────────────────────────┤\n";

        $partners = Partner::all();
        foreach ($partners as $partner) {
            $balance = floatval($partner->current_balance);
            $type = match($partner->type) {
                'supplier' => 'SUP',
                'customer' => 'CUS',
                default => 'OTH',
            };

            printf("│ [%s] %-35s %15s │\n",
                $type,
                substr($partner->name, 0, 35),
                $this->formatMoney($balance)
            );
        }

        echo "└─────────────────────────────────────────────────────────────┘\n\n";

        // Final Status
        if ($diff < 0.01 && $stockMatches) {
            $this->log('🎉 ALL CHECKS PASSED - DATABASE IS CONSISTENT', 'success');
        } else {
            $this->log('⚠️  VERIFICATION FAILED - PLEASE REVIEW', 'error');
        }

        echo "\n";
    }

    /**
     * Helper: Print Header
     */
    private function printHeader(string $title): void
    {
        $length = strlen($title) + 4;
        echo "\n" . str_repeat('═', $length) . "\n";
        echo "  {$title}  \n";
        echo str_repeat('═', $length) . "\n\n";
    }

    /**
     * Helper: Print Step
     */
    private function printStep(string $step): void
    {
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ {$step}" . str_repeat(' ', 59 - strlen($step)) . "│\n";
        echo "└─────────────────────────────────────────────────────────────┘\n";
    }

    /**
     * Helper: Log message with color
     */
    private function log(string $message, string $type = 'info'): void
    {
        $prefix = match($type) {
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠',
            default => '•',
        };

        echo "  {$prefix} {$message}\n";
    }

    /**
     * Helper: Format money
     */
    private function formatMoney(float $amount): string
    {
        $formatted = number_format(abs($amount), 2);
        return $amount < 0 ? "({$formatted})" : $formatted;
    }
}
