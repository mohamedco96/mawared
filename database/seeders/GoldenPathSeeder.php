<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Treasury;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CapitalService;
use App\Services\StockService;
use App\Services\TreasuryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Golden Path Database Seeder
 *
 * Creates a cohesive, chronologically consistent business story that follows
 * strict accounting and inventory rules. This seeder ensures:
 *
 * 1. CHRONOLOGICAL ORDER:
 *    - Initial capital deposit → Purchases → Sales → Returns → Payments
 *    - All transactions follow a logical timeline
 *
 * 2. PRICING LOGIC:
 *    - Sale prices always > Cost prices (20-40% margin)
 *    - No fractional cents (rounded to logical figures)
 *
 * 3. INVENTORY CONSISTENCY:
 *    - Sales only happen AFTER purchases
 *    - Stock never goes below zero
 *    - All stock movements are tracked
 *
 * 4. FINANCIAL BALANCING:
 *    - Treasury balance = Initial Capital + Sales Collections - Purchase Payments - Expenses
 *    - Invoice status matches payment records
 *    - Partner balances are accurate
 */
class GoldenPathSeeder extends Seeder
{
    private TreasuryService $treasuryService;

    private StockService $stockService;

    private CapitalService $capitalService;

    private User $admin;

    private Warehouse $mainWarehouse;

    private Treasury $mainTreasury;

    private Treasury $bankTreasury;

    // Business entities
    private array $suppliers = [];

    private array $customers = [];

    private array $shareholders = [];

    private array $products = [];

    // Track inventory levels in memory for fast lookups
    private array $inventoryLevels = [];

    // Current simulation date
    private Carbon $currentDate;

    // Financial tracking
    private float $expectedTreasuryBalance = 0;

    private array $financialLog = [];

    // Invoice counters for auto-numbering
    private int $purchaseInvoiceCounter = 1;

    private int $salesInvoiceCounter = 1;

    private int $salesReturnCounter = 1;

    private int $purchaseReturnCounter = 1;

    public function __construct()
    {
        $this->treasuryService = app(TreasuryService::class);
        $this->stockService = app(StockService::class);
        $this->capitalService = app(CapitalService::class);
    }

    public function run(): void
    {
        DB::transaction(function () {
            $this->log("🚀 Starting Golden Path Seeder...\n");
            $this->log(str_repeat('=', 80));

            // Start at beginning of current month
            $this->currentDate = now()->startOfMonth();

            // ====================================================================
            // PHASE 1: Foundation Setup
            // ====================================================================
            $this->setupFoundation();

            // ====================================================================
            // PHASE 2: Initial Capital Investment (Day 1)
            // ====================================================================
            $this->depositInitialCapital();

            // ====================================================================
            // PHASE 3: Business Operations Simulation (30 Days)
            // ====================================================================
            $this->simulateBusinessDays(30);

            // ====================================================================
            // PHASE 4: Verify Financial Integrity
            // ====================================================================
            $this->verifyFinancialIntegrity();

            // ====================================================================
            // PHASE 5: Recalculate All Balances
            // ====================================================================
            $this->recalculateBalances();

            $this->log(str_repeat('=', 80));
            $this->log("✅ Golden Path Seeder Completed Successfully!\n");
            $this->printSummary();
        });
    }

    // ========================================================================
    // PHASE 1: Foundation Setup
    // ========================================================================

    private function setupFoundation(): void
    {
        $this->log("\n📦 PHASE 1: Foundation Setup");
        $this->log(str_repeat('-', 80));

        // Get admin user
        $this->admin = User::where('email', 'mohamed@osoolerp.com')->first() ?? User::first();
        if (! $this->admin) {
            throw new \Exception('No admin user found. Run AdminUserSeeder first.');
        }
        $this->log("✓ Admin user: {$this->admin->name}");

        // Get warehouse
        $this->mainWarehouse = Warehouse::first();
        if (! $this->mainWarehouse) {
            throw new \Exception('No warehouse found. Run WarehouseSeeder first.');
        }
        $this->log("✓ Warehouse: {$this->mainWarehouse->name}");

        // Create treasuries
        $this->mainTreasury = Treasury::firstOrCreate(
            ['name' => 'الخزينة الرئيسية'],
            ['type' => 'cash', 'description' => 'خزينة المكتب الرئيسي']
        );
        $this->bankTreasury = Treasury::firstOrCreate(
            ['name' => 'البنك الأهلي المصري'],
            ['type' => 'bank', 'description' => 'الحساب البنكي للشركة']
        );
        $this->log('✓ Treasuries created');

        // Create partners
        $this->createPartners();

        // Create products
        $this->createProducts();

        $this->log("✓ Foundation setup complete\n");
    }

    private function createPartners(): void
    {
        // Shareholders (5)
        $shareholderNames = [
            'محمد حسن الدمياطي - الشريك المؤسس',
            'أحمد علي المنصوري - مستثمر',
            'خالد إبراهيم سالم - شريك',
            'سعيد محمود - شريك عيني (أصول)',
            'ياسر علي - شريك بدون رأس مال (جديد)',
        ];

        foreach ($shareholderNames as $name) {
            $this->shareholders[] = Partner::create([
                'name' => $name,
                'phone' => '0111'.rand(1000000, 9999999),
                'type' => 'shareholder',
                'gov_id' => 'دمياط',
                'opening_balance' => 0,
                'current_balance' => 0,
            ]);
        }
        $this->log('✓ Created '.count($this->shareholders).' shareholders');

        // Suppliers (5)
        $supplierNames = [
            'شركة دمياط للأدوات المنزلية',
            'مصنع المنصورة للبلاستيك',
            'موزع الدلتا للأواني',
            'شركة النيل الأزرق للتوريدات',
            'مؤسسة رأس البر التجارية',
        ];

        foreach ($supplierNames as $name) {
            $this->suppliers[] = Partner::create([
                'name' => $name,
                'phone' => '022'.rand(1000000, 9999999),
                'type' => 'supplier',
                'gov_id' => 'دمياط',
                'opening_balance' => 0,
                'current_balance' => 0,
            ]);
        }
        $this->log('✓ Created '.count($this->suppliers).' suppliers');

        // Customers (10)
        $customerNames = [
            'محمد إبراهيم أحمد',
            'أحمد حسن محمود',
            'علي عبد الله سالم',
            'حسن علي محمد',
            'خالد محمود حسن',
            'عمر أحمد علي',
            'يوسف محمد عبد الله',
            'عبد الرحمن خالد',
            'إبراهيم حسين محمد',
            'محمود سعيد علي',
        ];

        foreach ($customerNames as $name) {
            $this->customers[] = Partner::create([
                'name' => $name,
                'phone' => '0100'.rand(1000000, 9999999),
                'type' => 'customer',
                'gov_id' => 'دمياط',
                'opening_balance' => 0,
                'current_balance' => 0,
            ]);
        }
        $this->log('✓ Created '.count($this->customers).' customers');
    }

    private function createProducts(): void
    {
        $pieceUnit = Unit::where('name', 'قطعة')->first();
        $cartonUnit = Unit::where('name', 'كرتونة')->first();

        if (! $pieceUnit || ! $cartonUnit) {
            throw new \Exception('Units not found. Run UnitSeeder first.');
        }

        $category = ProductCategory::first();
        if (! $category) {
            throw new \Exception('No product category found. Run ProductCategorySeeder first.');
        }

        // Create 20 products with realistic pricing
        $productNames = [
            ['name' => 'طبق تقديم دائري', 'cost' => 10, 'margin' => 0.35],
            ['name' => 'صحن طعام سيراميك', 'cost' => 15, 'margin' => 0.30],
            ['name' => 'كوب شاي زجاج', 'cost' => 5, 'margin' => 0.40],
            ['name' => 'فنجان قهوة', 'cost' => 8, 'margin' => 0.35],
            ['name' => 'طنجرة ضغط', 'cost' => 120, 'margin' => 0.25],
            ['name' => 'مقلاة تيفال', 'cost' => 80, 'margin' => 0.30],
            ['name' => 'طقم ملاعق ستانلس', 'cost' => 25, 'margin' => 0.40],
            ['name' => 'طقم شوك ستانلس', 'cost' => 25, 'margin' => 0.40],
            ['name' => 'مصفاة ستانلس', 'cost' => 12, 'margin' => 0.35],
            ['name' => 'لوح تقطيع', 'cost' => 18, 'margin' => 0.30],
            ['name' => 'علبة حفظ بلاستيك', 'cost' => 7, 'margin' => 0.40],
            ['name' => 'علبة حفظ زجاج', 'cost' => 15, 'margin' => 0.35],
            ['name' => 'سكين مطبخ كبير', 'cost' => 30, 'margin' => 0.30],
            ['name' => 'مقص مطبخ', 'cost' => 20, 'margin' => 0.35],
            ['name' => 'ترمس قهوة', 'cost' => 50, 'margin' => 0.25],
            ['name' => 'إبريق ماء', 'cost' => 22, 'margin' => 0.30],
            ['name' => 'صينية تقديم', 'cost' => 35, 'margin' => 0.30],
            ['name' => 'طقم توابل', 'cost' => 40, 'margin' => 0.25],
            ['name' => 'قدر بخار', 'cost' => 95, 'margin' => 0.25],
            ['name' => 'كاسة عصير', 'cost' => 6, 'margin' => 0.40],
        ];

        foreach ($productNames as $index => $productData) {
            $cost = $productData['cost'];
            $margin = $productData['margin'];
            $retailPrice = round($cost * (1 + $margin), 2);
            $wholesalePrice = round($cost * (1 + $margin * 0.8), 2);
            $factor = 12; // 12 pieces per carton

            $product = Product::create([
                'category_id' => $category->id,
                'name' => $productData['name'],
                'description' => 'منتج عالي الجودة',
                'image' => 'https://images.pexels.com/photos/4112621/pexels-photo-4112621.jpeg',
                'barcode' => '6111'.str_pad($index + 1, 9, '0', STR_PAD_LEFT),
                'large_barcode' => '6111'.str_pad($index + 1, 9, '0', STR_PAD_LEFT).'C',
                'sku' => 'PRD-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'min_stock' => 50,
                'avg_cost' => $cost,
                'small_unit_id' => $pieceUnit->id,
                'large_unit_id' => $cartonUnit->id,
                'factor' => $factor,
                'retail_price' => $retailPrice,
                'wholesale_price' => $wholesalePrice,
                'large_retail_price' => round($retailPrice * $factor * 0.95, 2),
                'large_wholesale_price' => round($wholesalePrice * $factor * 0.92, 2),
                'is_visible_in_retail_catalog' => true,
                'is_visible_in_wholesale_catalog' => true,
            ]);

            $this->products[] = $product;
            // Initialize inventory tracking
            $this->inventoryLevels[$product->id] = 0;
        }

        $this->log('✓ Created '.count($this->products).' products');
    }

    // ========================================================================
    // PHASE 2: Initial Capital Investment
    // ========================================================================

    private function depositInitialCapital(): void
    {
        $this->log("\n💰 PHASE 2: Initial Capital Investment (Day 1)");
        $this->log(str_repeat('-', 80));
        $this->log('Date: '.$this->currentDate->format('Y-m-d'));

        // Shareholder 1: 300,000 Cash
        $this->capitalService->injectCapital(
            $this->shareholders[0],
            300000,
            'cash',
            [
                'treasury_id' => $this->mainTreasury->id,
                'description' => 'رأس المال - حصة الشريك المؤسس',
            ]
        );
        $this->logFinancial('capital_deposit', 300000, 'رأس المال - حصة الشريك المؤسس');
        $this->expectedTreasuryBalance += 300000;

        // Shareholder 2: 150,000 Cash
        $this->capitalService->injectCapital(
            $this->shareholders[1],
            150000,
            'cash',
            [
                'treasury_id' => $this->mainTreasury->id,
                'description' => 'رأس المال - حصة الشريك الثاني',
            ]
        );
        $this->logFinancial('capital_deposit', 150000, 'رأس المال - حصة الشريك الثاني');
        $this->expectedTreasuryBalance += 150000;

        // Shareholder 3: 50,000 Cash
        $this->capitalService->injectCapital(
            $this->shareholders[2],
            50000,
            'cash',
            [
                'treasury_id' => $this->mainTreasury->id,
                'description' => 'رأس المال - حصة الشريك الثالث',
            ]
        );
        $this->logFinancial('capital_deposit', 50000, 'رأس المال - حصة الشريك الثالث');
        $this->expectedTreasuryBalance += 50000;

        // Shareholder 4: 100,000 Asset (Building/Truck)
        // Note: We use type 'asset' so it doesn't affect treasury balance automatically
        $this->capitalService->injectCapital(
            $this->shareholders[3],
            100000,
            'asset',
            [
                'description' => 'رأس المال - حصة عينية (سيارة نقل)',
            ]
        );

        // Manually create the asset record
        \App\Models\FixedAsset::create([
            'name' => 'سيارة نقل بضائع',
            'description' => 'مساهمة عينية من الشريك سعيد محمود',
            'purchase_amount' => 100000,
            'purchase_date' => $this->currentDate,
            'funding_method' => 'equity',
            'treasury_id' => null, // Not paid from treasury
            'partner_id' => $this->shareholders[3]->id,
            'is_contributed_asset' => true,
            'contributing_partner_id' => $this->shareholders[3]->id,
            'created_by' => $this->admin->id,
            'status' => 'active',
        ]);

        // Shareholder 5: 0 Capital (New partner)
        // No action needed, they exist but have 0 capital and 0% equity

        $totalCashCapital = 500000; // 300k + 150k + 50k
        $totalAssetCapital = 100000; // 100k asset

        $this->log('✓ Total cash capital deposited: '.number_format($totalCashCapital, 2).' EGP');
        $this->log('✓ Total asset capital contribution: '.number_format($totalAssetCapital, 2).' EGP');
        $this->log('✓ Equity Period created/updated automatically');
        $this->log('✓ Main Treasury Balance: '.number_format($this->expectedTreasuryBalance, 2)." EGP\n");
    }

    // ========================================================================
    // PHASE 3: Business Operations Simulation
    // ========================================================================

    private function simulateBusinessDays(int $days): void
    {
        $this->log("\n📅 PHASE 3: Simulating {$days} Days of Business Operations");
        $this->log(str_repeat('-', 80));

        for ($day = 1; $day <= $days; $day++) {
            $this->currentDate = $this->currentDate->copy()->addDay();
            $this->log("\n--- Day {$day}: ".$this->currentDate->format('Y-m-d').' ---');

            // Purchase cycle: Days 1-10 (Buy inventory)
            if ($day <= 10) {
                $this->executePurchaseDay($day);
            }

            // Sales cycle: Days 5-30 (Sell after we have stock)
            if ($day >= 5 && $day <= 30) {
                $this->executeSalesDay($day);
            }

            // Payment collection: Days 10-30
            if ($day >= 10 && $day <= 30 && $day % 3 === 0) {
                $this->collectCustomerPayments();
            }

            // Supplier payments: Days 12-30
            if ($day >= 12 && $day <= 30 && $day % 4 === 0) {
                $this->paySuppliers();
            }

            // Operating expenses: Every 5 days
            if ($day % 5 === 0) {
                $this->recordExpenses();
            }

            // Returns: Occasionally (days 15, 22, 28)
            if (in_array($day, [15, 22, 28])) {
                $this->processReturns();
            }

            // Revenues: Occasionally (days 8, 18, 25)
            if (in_array($day, [8, 18, 25])) {
                $this->recordRevenues();
            }
        }

        $this->log("\n✓ Business operations simulation complete");
    }

    private function executePurchaseDay(int $day): void
    {
        // Create 1-2 purchase invoices per day in first 10 days
        $invoiceCount = $day <= 5 ? 2 : 1;

        for ($i = 0; $i < $invoiceCount; $i++) {
            $supplier = $this->suppliers[array_rand($this->suppliers)];
            $paymentMethod = $day <= 3 ? 'cash' : 'credit'; // First 3 days cash, then credit

            $invoice = PurchaseInvoice::create([
                'invoice_number' => 'PUR-'.str_pad($this->purchaseInvoiceCounter++, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $this->mainWarehouse->id,
                'partner_id' => $supplier->id,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'discount_type' => 'percentage',
                'discount_value' => 0,
                'notes' => 'فاتورة شراء - يوم '.$day,
                'created_by' => $this->admin->id,
            ]);

            // Add 3-5 products to purchase
            $productsToPurchase = array_rand($this->products, rand(3, 5));
            if (! is_array($productsToPurchase)) {
                $productsToPurchase = [$productsToPurchase];
            }

            $subtotal = 0;

            foreach ($productsToPurchase as $productIndex) {
                $product = $this->products[$productIndex];
                $quantity = rand(20, 100); // Buy in bulk
                $unitCost = $product->avg_cost;
                $total = $unitCost * $quantity;

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_type' => 'small',
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total' => $total,
                ]);

                $subtotal += $total;
            }

            // Apply small discount (0-5%)
            $discountPercent = rand(0, 5);
            $discountAmount = round($subtotal * $discountPercent / 100, 2);
            $total = $subtotal - $discountAmount;

            // Determine payment
            $paidAmount = 0;
            if ($paymentMethod === 'cash') {
                $paidAmount = $total; // Full payment
            } else {
                // Credit: 50% pay upfront, 50% pay later
                $paidAmount = rand(0, 1) ? round($total * 0.5, 2) : 0;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_value' => $discountPercent,
                'discount' => $discountAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $total - $paidAmount,
            ]);

            // Post the invoice
            try {
                // Eager load relationships to avoid lazy loading issues
                $invoice->load('items.product');

                $this->stockService->postPurchaseInvoice($invoice);
                $this->treasuryService->postPurchaseInvoice($invoice, $this->mainTreasury->id);
                $invoice->update(['status' => 'posted']);

                // Update our inventory tracking (use fresh items from DB)
                $invoiceItems = $invoice->items()->with('product')->get();
                foreach ($invoiceItems as $item) {
                    $qtyInSmallUnit = $item->quantity;
                    if ($item->unit_type === 'large' && $item->product) {
                        $qtyInSmallUnit *= $item->product->factor;
                    }
                    $this->inventoryLevels[$item->product_id] += $qtyInSmallUnit;
                }

                // Update treasury balance
                if ($paidAmount > 0) {
                    $this->expectedTreasuryBalance -= $paidAmount;
                    $this->logFinancial('Purchase Payment', -$paidAmount, $invoice->invoice_number);
                }

                $this->log("  ✓ Purchase Invoice {$invoice->invoice_number}: ".
                          number_format($total, 2).' EGP ('.
                          ($paymentMethod === 'cash' ? 'Cash' : 'Credit').')');
            } catch (\Exception $e) {
                $this->log('  ✗ Failed to post purchase invoice: '.$e->getMessage());
            }
        }
    }

    private function executeSalesDay(int $day): void
    {
        // Create 2-4 sales invoices per day after we have stock
        $invoiceCount = rand(2, 4);

        for ($i = 0; $i < $invoiceCount; $i++) {
            $customer = $this->customers[array_rand($this->customers)];
            $paymentMethod = rand(0, 100) < 40 ? 'cash' : 'credit'; // 40% cash, 60% credit

            $invoice = SalesInvoice::create([
                'invoice_number' => 'SAL-'.str_pad($this->salesInvoiceCounter++, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $this->mainWarehouse->id,
                'partner_id' => $customer->id,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'discount_type' => 'percentage',
                'discount_value' => 0,
                'notes' => 'فاتورة بيع - يوم '.$day,
                'created_by' => $this->admin->id,
            ]);

            // Add 1-4 products to sale (only products we have in stock)
            $availableProducts = array_filter($this->products, function ($product) {
                return $this->inventoryLevels[$product->id] > 10; // At least 10 in stock
            });

            if (empty($availableProducts)) {
                // No stock available, skip this sale
                $invoice->forceDelete();

                continue;
            }

            $productsToSell = array_rand($availableProducts, min(rand(1, 4), count($availableProducts)));
            if (! is_array($productsToSell)) {
                $productsToSell = [$productsToSell];
            }

            $subtotal = 0;
            $canFulfill = true;

            foreach ($productsToSell as $productIndex) {
                $product = $availableProducts[$productIndex];
                $availableQty = $this->inventoryLevels[$product->id];

                // Sell reasonable quantity (10-30% of available stock, max 50 units)
                $maxQty = min(50, (int) ($availableQty * 0.3));
                if ($maxQty < 1) {
                    $canFulfill = false;
                    break;
                }

                $quantity = rand(1, $maxQty);
                $unitPrice = $product->retail_price;
                $itemDiscount = rand(0, 10) < 2 ? rand(5, 20) : 0; // 20% chance of item discount
                $total = ($unitPrice * $quantity) - $itemDiscount;

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_type' => 'small',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'total' => $total,
                ]);

                $subtotal += $total;
            }

            if (! $canFulfill) {
                $invoice->forceDelete();

                continue;
            }

            // Apply invoice discount (0-10%)
            $discountPercent = rand(0, 10);
            $discountAmount = round($subtotal * $discountPercent / 100, 2);
            $total = $subtotal - $discountAmount;

            // Determine payment
            $paidAmount = 0;
            if ($paymentMethod === 'cash') {
                $paidAmount = $total; // Full payment
            } else {
                // Credit: 40% pay upfront, 60% pay later
                $paidAmount = rand(0, 100) < 40 ? round($total * rand(30, 50) / 100, 2) : 0;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_value' => $discountPercent,
                'discount' => $discountAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $total - $paidAmount,
            ]);

            // Post the invoice
            try {
                // Eager load relationships to avoid lazy loading issues
                $invoice->load('items.product');

                $this->stockService->postSalesInvoice($invoice);
                $this->treasuryService->postSalesInvoice($invoice, $this->mainTreasury->id);
                $invoice->update(['status' => 'posted']);

                // Update our inventory tracking (use fresh items from DB)
                $invoiceItems = $invoice->items()->with('product')->get();
                foreach ($invoiceItems as $item) {
                    $qtyInSmallUnit = $item->quantity;
                    if ($item->unit_type === 'large' && $item->product) {
                        $qtyInSmallUnit *= $item->product->factor;
                    }
                    $this->inventoryLevels[$item->product_id] -= $qtyInSmallUnit;
                }

                // Update treasury balance
                if ($paidAmount > 0) {
                    $this->expectedTreasuryBalance += $paidAmount;
                    $this->logFinancial('Sales Collection', $paidAmount, $invoice->invoice_number);
                }

                $this->log("  ✓ Sales Invoice {$invoice->invoice_number}: ".
                          number_format($total, 2).' EGP ('.
                          ($paymentMethod === 'cash' ? 'Cash' : 'Credit').')');
            } catch (\Exception $e) {
                $this->log('  ✗ Failed to post sales invoice: '.$e->getMessage());
                // Restore inventory if posting failed
                foreach ($invoice->items as $item) {
                    $qtyInSmallUnit = $item->quantity;
                    if ($item->unit_type === 'large') {
                        $qtyInSmallUnit *= $item->product->factor;
                    }
                    $this->inventoryLevels[$item->product_id] += $qtyInSmallUnit;
                }
            }
        }
    }

    private function collectCustomerPayments(): void
    {
        // Collect payments from customers with outstanding balances
        $unpaidInvoices = SalesInvoice::where('status', 'posted')
            ->where('payment_method', 'credit')
            ->where('remaining_amount', '>', 0)
            ->where('created_at', '<', $this->currentDate)
            ->limit(3)
            ->get();

        foreach ($unpaidInvoices as $invoice) {
            $remaining = floatval($invoice->remaining_amount);

            // Pay 50-100% of remaining
            $paymentPercent = rand(50, 100);
            $amount = round($remaining * $paymentPercent / 100, 2);

            // Occasional settlement discount (10% chance)
            $discount = rand(0, 10) < 1 ? round($amount * 0.05, 2) : 0;

            try {
                $this->treasuryService->recordInvoicePayment(
                    $invoice,
                    $amount,
                    $discount,
                    $this->mainTreasury->id,
                    'تحصيل من العميل'
                );

                $this->expectedTreasuryBalance += $amount;
                $this->logFinancial('Customer Payment', $amount, $invoice->invoice_number);

                $this->log('  ✓ Collected '.number_format($amount, 2).
                          " EGP from invoice {$invoice->invoice_number}");
            } catch (\Exception $e) {
                $this->log('  ✗ Failed to collect payment: '.$e->getMessage());
            }
        }
    }

    private function paySuppliers(): void
    {
        // Pay suppliers with outstanding balances
        $unpaidInvoices = PurchaseInvoice::where('status', 'posted')
            ->where('payment_method', 'credit')
            ->where('remaining_amount', '>', 0)
            ->where('created_at', '<', $this->currentDate)
            ->limit(2)
            ->get();

        foreach ($unpaidInvoices as $invoice) {
            $remaining = floatval($invoice->remaining_amount);

            // Check if we have enough cash
            if ($this->expectedTreasuryBalance < $remaining * 0.5) {
                $this->log('  ⚠ Insufficient funds to pay supplier');

                continue;
            }

            // Pay 40-80% of remaining
            $paymentPercent = rand(40, 80);
            $amount = round($remaining * $paymentPercent / 100, 2);

            // Occasional early payment discount (15% chance)
            $discount = rand(0, 100) < 15 ? round($amount * 0.03, 2) : 0;

            try {
                $this->treasuryService->recordInvoicePayment(
                    $invoice,
                    $amount,
                    $discount,
                    $this->mainTreasury->id,
                    'دفع للمورد'
                );

                $this->expectedTreasuryBalance -= $amount;
                $this->logFinancial('Supplier Payment', -$amount, $invoice->invoice_number);

                $this->log('  ✓ Paid '.number_format($amount, 2).
                          " EGP to supplier for invoice {$invoice->invoice_number}");
            } catch (\Exception $e) {
                $this->log('  ✗ Failed to pay supplier: '.$e->getMessage());
            }
        }
    }

    private function recordExpenses(): void
    {
        $expenseTypes = [
            ['title' => 'إيجار المكتب', 'amount' => 10000],
            ['title' => 'رواتب الموظفين', 'amount' => 25000],
            ['title' => 'فواتير الكهرباء والمياه', 'amount' => 1500],
            ['title' => 'مصاريف تسويق', 'amount' => 3000],
            ['title' => 'صيانة وإصلاحات', 'amount' => 2000],
        ];

        $expense = $expenseTypes[array_rand($expenseTypes)];
        $amount = $expense['amount'];

        // Check if we have enough cash
        if ($this->expectedTreasuryBalance < $amount) {
            $this->log("  ⚠ Insufficient funds for expense: {$expense['title']}");

            return;
        }

        try {
            $expenseRecord = Expense::create([
                'title' => $expense['title'],
                'description' => 'مصروف تشغيلي',
                'amount' => $amount,
                'treasury_id' => $this->mainTreasury->id,
                'expense_date' => $this->currentDate,
                'created_by' => $this->admin->id,
            ]);

            $this->treasuryService->postExpense($expenseRecord);

            $this->expectedTreasuryBalance -= $amount;
            $this->logFinancial('Expense', -$amount, $expense['title']);

            $this->log("  ✓ Expense: {$expense['title']} - ".number_format($amount, 2).' EGP');
        } catch (\Exception $e) {
            $this->log('  ✗ Failed to record expense: '.$e->getMessage());
        }
    }

    private function processReturns(): void
    {
        // Process 1-2 sales returns
        $postedSales = SalesInvoice::where('status', 'posted')
            ->whereHas('items')
            ->where('created_at', '<', $this->currentDate->copy()->subDays(3))
            ->with('items.product') // Eager load items and products
            ->limit(2)
            ->get();

        foreach ($postedSales as $invoice) {
            $paymentMethod = rand(0, 1) ? 'cash' : 'credit';

            $salesReturn = SalesReturn::create([
                'return_number' => 'SR-'.str_pad($this->salesReturnCounter++, 5, '0', STR_PAD_LEFT),
                'sales_invoice_id' => $invoice->id,
                'warehouse_id' => $invoice->warehouse_id,
                'partner_id' => $invoice->partner_id,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'notes' => 'مرتجع من فاتورة '.$invoice->invoice_number,
                'created_by' => $this->admin->id,
            ]);

            // Return 1 item
            $itemToReturn = $invoice->items->first();
            $returnQty = min(rand(1, 3), $itemToReturn->quantity);
            $returnTotal = round(($itemToReturn->unit_price * $returnQty) -
                               (($itemToReturn->discount / $itemToReturn->quantity) * $returnQty), 2);

            SalesReturnItem::create([
                'sales_return_id' => $salesReturn->id,
                'product_id' => $itemToReturn->product_id,
                'unit_type' => $itemToReturn->unit_type,
                'quantity' => $returnQty,
                'unit_price' => $itemToReturn->unit_price,
                'total' => $returnTotal,
            ]);

            $salesReturn->update([
                'subtotal' => $returnTotal,
                'total' => $returnTotal,
            ]);

            try {
                // Eager load relationships to avoid lazy loading issues
                $salesReturn->load('items.product');

                $this->stockService->postSalesReturn($salesReturn);
                $this->treasuryService->postSalesReturn($salesReturn, $this->mainTreasury->id);
                $salesReturn->update(['status' => 'posted']);

                // Update inventory
                $qtyInSmallUnit = $returnQty;
                if ($itemToReturn->unit_type === 'large') {
                    $qtyInSmallUnit *= $itemToReturn->product->factor;
                }
                $this->inventoryLevels[$itemToReturn->product_id] += $qtyInSmallUnit;

                // Update treasury (cash returns reduce treasury)
                if ($paymentMethod === 'cash') {
                    $this->expectedTreasuryBalance -= $returnTotal;
                    $this->logFinancial('Sales Return (Cash)', -$returnTotal, $salesReturn->return_number);
                }

                $this->log("  ✓ Sales Return {$salesReturn->return_number}: ".
                          number_format($returnTotal, 2).' EGP');
            } catch (\Exception $e) {
                $this->log('  ✗ Failed to post sales return: '.$e->getMessage());
            }
        }
    }

    private function recordRevenues(): void
    {
        $revenueTypes = [
            ['title' => 'عمولة وساطة', 'amount' => rand(2000, 5000)],
            ['title' => 'إيرادات خدمات', 'amount' => rand(3000, 8000)],
            ['title' => 'فوائد بنكية', 'amount' => rand(500, 1500)],
        ];

        $revenue = $revenueTypes[array_rand($revenueTypes)];

        try {
            $revenueRecord = Revenue::create([
                'title' => $revenue['title'],
                'description' => 'إيراد إضافي',
                'amount' => $revenue['amount'],
                'treasury_id' => $this->mainTreasury->id,
                'revenue_date' => $this->currentDate,
                'created_by' => $this->admin->id,
            ]);

            $this->treasuryService->postRevenue($revenueRecord);

            $this->expectedTreasuryBalance += $revenue['amount'];
            $this->logFinancial('Revenue', $revenue['amount'], $revenue['title']);

            $this->log("  ✓ Revenue: {$revenue['title']} - ".number_format($revenue['amount'], 2).' EGP');
        } catch (\Exception $e) {
            $this->log('  ✗ Failed to record revenue: '.$e->getMessage());
        }
    }

    // ========================================================================
    // PHASE 4: Verify Financial Integrity
    // ========================================================================

    private function verifyFinancialIntegrity(): void
    {
        $this->log("\n🔍 PHASE 4: Financial Integrity Verification");
        $this->log(str_repeat('-', 80));

        // Get actual treasury balance
        $actualBalance = $this->treasuryService->getTreasuryBalance($this->mainTreasury->id);

        $this->log('Expected Treasury Balance: '.number_format($this->expectedTreasuryBalance, 2).' EGP');
        $this->log('Actual Treasury Balance:   '.number_format($actualBalance, 2).' EGP');

        $difference = abs($actualBalance - $this->expectedTreasuryBalance);
        if ($difference < 0.01) {
            $this->log('✓ Treasury balances match perfectly!');
        } else {
            $this->log('⚠ Warning: Balance difference of '.number_format($difference, 2).' EGP');
        }

        // Verify stock levels
        $this->log("\n📊 Stock Verification:");
        $negativeStock = false;
        foreach ($this->products as $product) {
            $actualStock = $product->stockMovements()->sum('quantity');
            $expectedStock = $this->inventoryLevels[$product->id];

            if ($actualStock < 0) {
                $this->log("  ✗ {$product->name}: NEGATIVE STOCK ({$actualStock})");
                $negativeStock = true;
            } elseif ($actualStock != $expectedStock) {
                $this->log("  ⚠ {$product->name}: Expected {$expectedStock}, Actual {$actualStock}");
            }
        }

        if (! $negativeStock) {
            $this->log('  ✓ No negative stock detected');
        }
    }

    // ========================================================================
    // PHASE 5: Recalculate All Balances
    // ========================================================================

    private function recalculateBalances(): void
    {
        $this->log("\n🔄 PHASE 5: Recalculating Partner Balances");
        $this->log(str_repeat('-', 80));

        $partners = Partner::all();
        foreach ($partners as $partner) {
            $partner->recalculateBalance();
        }

        $this->log('✓ Recalculated '.$partners->count().' partner balances');
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function recordTransaction(
        Treasury $treasury,
        string $type,
        float $amount,
        string $description,
        ?Partner $partner = null
    ): void {
        $this->treasuryService->recordTransaction(
            $treasury->id,
            $type,
            $amount,
            $description,
            $partner?->id,
            null,
            null
        );

        $this->expectedTreasuryBalance += $amount;
        $this->logFinancial($type, $amount, $description);
    }

    private function log(string $message): void
    {
        echo $message."\n";
    }

    private function logFinancial(string $type, float $amount, string $reference): void
    {
        $this->financialLog[] = [
            'date' => $this->currentDate->format('Y-m-d'),
            'type' => $type,
            'amount' => $amount,
            'balance' => $this->expectedTreasuryBalance,
            'reference' => $reference,
        ];
    }

    private function printSummary(): void
    {
        echo "\n".str_repeat('=', 80)."\n";
        echo "📊 GOLDEN PATH SEEDER SUMMARY\n";
        echo str_repeat('=', 80)."\n";

        // Partners
        $customersCount = Partner::where('type', 'customer')->count();
        $suppliersCount = Partner::where('type', 'supplier')->count();
        $shareholdersCount = Partner::where('type', 'shareholder')->count();
        echo "👥 Partners: {$customersCount} customers, {$suppliersCount} suppliers, {$shareholdersCount} shareholders\n";

        // Products
        $productsCount = Product::count();
        echo "📦 Products: {$productsCount} products\n";

        // Invoices
        $purchasesCount = PurchaseInvoice::where('status', 'posted')->count();
        $salesCount = SalesInvoice::where('status', 'posted')->count();
        echo "📄 Invoices: {$purchasesCount} purchases, {$salesCount} sales\n";

        // Returns
        $salesReturns = SalesReturn::where('status', 'posted')->count();
        $purchaseReturns = PurchaseReturn::where('status', 'posted')->count();
        echo "↩️  Returns: {$salesReturns} sales returns, {$purchaseReturns} purchase returns\n";

        // Expenses & Revenues
        $expensesCount = Expense::count();
        $expensesTotal = Expense::sum('amount');
        $revenuesCount = Revenue::count();
        $revenuesTotal = Revenue::sum('amount');
        echo "💰 Finance: {$expensesCount} expenses (".number_format($expensesTotal, 2).' EGP), ';
        echo "{$revenuesCount} revenues (".number_format($revenuesTotal, 2)." EGP)\n";

        // Treasury Balance
        $actualBalance = $this->treasuryService->getTreasuryBalance($this->mainTreasury->id);
        echo '🏦 Main Treasury Balance: '.number_format($actualBalance, 2)." EGP\n";

        // Stock
        $totalStockValue = 0;
        foreach ($this->products as $product) {
            $stock = $product->stockMovements()->sum('quantity');
            $totalStockValue += $stock * $product->avg_cost;
        }
        echo '📊 Total Stock Value: '.number_format($totalStockValue, 2)." EGP\n";

        echo str_repeat('=', 80)."\n";
    }
}
