<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Installment;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InstallmentService;
use App\Services\StockService;
use App\Services\TreasuryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive Database Seeder for Real-World ERP Testing
 *
 * This seeder generates REALISTIC, DIVERSE data covering all edge cases:
 * - Partners: VIP/Regular/New Customers, Suppliers, Shareholders
 * - Products: High/Low/Out of stock items
 * - Invoices: Cash/Credit, Paid/Unpaid/Overdue, with discounts
 * - Returns: Cash refunds vs Credit notes
 * - Expenses & Revenues across multiple treasuries
 * - Treasury transfers between accounts
 *
 * CRITICAL: Uses Domain Services (TreasuryService, StockService) to ensure data integrity
 */
class ComprehensiveDatabaseSeeder extends Seeder
{
    private TreasuryService $treasuryService;
    private StockService $stockService;
    private InstallmentService $installmentService;
    private User $admin;
    private Warehouse $mainWarehouse;
    private array $treasuries = [];
    private array $customers = [];
    private array $suppliers = [];
    private array $shareholders = [];
    private array $products = [];

    public function __construct()
    {
        $this->treasuryService = app(TreasuryService::class);
        $this->stockService = app(StockService::class);
        $this->installmentService = app(InstallmentService::class);
    }

    public function run(): void
    {
        // Use Arabic locale for realistic data
        $faker = \Faker\Factory::create('ar_SA');

        DB::transaction(function () use ($faker) {
            echo "🚀 Starting Comprehensive Database Seeding...\n\n";

            // STEP 1: Setup Foundation
            $this->seedFoundation($faker);

            // STEP 2: Seed Partners (30+)
            $this->seedPartners($faker);

            // STEP 3: Seed Products (50+)
            $this->seedProducts($faker);

            // STEP 4: Initialize Treasury with Opening Capital
            $this->seedOpeningCapital($faker);

            // STEP 5: Simulate Business Operations
            $this->seedQuotations($faker, 20);
            $this->seedPurchaseInvoices($faker, 40);
            $this->seedSalesInvoices($faker, 60);
            $this->seedSalesInvoicesWithInstallments($faker, 15);
            $this->seedReturns($faker);
            $this->seedExpenses($faker, 30);
            $this->seedRevenues($faker, 10);
            $this->seedTreasuryTransfers($faker, 10);
            $this->seedSubsequentPayments($faker, 25);

            // STEP 6: Recalculate all partner balances
            $this->recalculatePartnerBalances();

            echo "\n✅ Comprehensive Database Seeding Completed Successfully!\n";
            $this->printSummary();
        });
    }

    /**
     * STEP 1: Setup Foundation (Users, Warehouses, Treasuries, Categories, Units)
     */
    private function seedFoundation($faker): void
    {
        echo "📦 [1/12] Setting up foundation...\n";

        // Get admin user (should already exist from AdminUserSeeder)
        $this->admin = User::where('email', 'admin@mawared.com')->first();

        if (!$this->admin) {
            // Fallback: get any existing user
            $this->admin = User::first();
        }

        if (!$this->admin) {
            echo "   ⚠️  Warning: No admin user found. Please run AdminUserSeeder first.\n";
            throw new \Exception('Admin user not found. Run AdminUserSeeder first.');
        }

        // Get or create warehouse
        $this->mainWarehouse = Warehouse::first() ?? Warehouse::create([
            'name' => 'المستودع الرئيسي',
            'code' => 'WH-MAIN-001',
            'address' => 'دمياط - رأس البر',
        ]);

        // Create treasuries with meaningful names
        $this->treasuries = [
            'main' => Treasury::firstOrCreate(
                ['name' => 'الخزينة الرئيسية'],
                ['type' => 'cash', 'description' => 'خزينة المكتب الرئيسي بدمياط للعمليات اليومية']
            ),
            'bank' => Treasury::firstOrCreate(
                ['name' => 'البنك الأهلي المصري - فرع دمياط'],
                ['type' => 'bank', 'description' => 'حساب جاري رقم 123456789']
            ),
            'petty_cash' => Treasury::firstOrCreate(
                ['name' => 'خزينة المصروفات الصغيرة'],
                ['type' => 'cash', 'description' => 'للمصروفات اليومية البسيطة']
            ),
            'branch' => Treasury::firstOrCreate(
                ['name' => 'خزينة فرع المنصورة'],
                ['type' => 'cash', 'description' => 'خزينة الفرع الثانوي بالمنصورة']
            ),
        ];

        echo "   ✓ Created 4 treasuries\n";
    }

    /**
     * STEP 2: Seed Partners (Customers, Suppliers, Shareholders)
     */
    private function seedPartners($faker): void
    {
        echo "👥 [2/12] Seeding partners...\n";

        $egyptianGovernorates = ['دمياط', 'الدقهلية', 'الشرقية', 'بورسعيد', 'الإسماعيلية', 'كفر الشيخ', 'الغربية'];
        $egyptianCustomerNames = [
            'محمد إبراهيم أحمد', 'أحمد حسن محمود', 'علي عبد الله سالم', 'حسن علي محمد', 'خالد محمود حسن',
            'عمر أحمد علي', 'يوسف محمد عبد الله', 'عبد الرحمن خالد أحمد', 'إبراهيم حسين محمد', 'محمود سعيد علي',
            'سعيد عبد العزيز محمد', 'طارق فتحي أحمد', 'ياسر محمد حسن', 'رامي أحمد سالم', 'وليد حسن محمود',
        ];

        $customerTypes = [
            'vip' => ['opening_balance' => 0, 'credit_limit' => 50000],
            'regular' => ['opening_balance' => 0, 'credit_limit' => 20000],
            'new' => ['opening_balance' => 0, 'credit_limit' => 5000],
            'bad_debt' => ['opening_balance' => rand(5000, 15000), 'credit_limit' => 0], // They owe us
        ];

        // Customers (15 customers)
        echo "   Creating customers...\n";
        $nameIndex = 0;
        foreach ($customerTypes as $type => $config) {
            for ($i = 0; $i < ($type === 'vip' ? 3 : ($type === 'bad_debt' ? 2 : 5)); $i++) {
                $this->customers[] = Partner::create([
                    'name' => $egyptianCustomerNames[$nameIndex++] ?? 'عميل ' . ($nameIndex + 1),
                    'phone' => '010' . rand(10000000, 99999999),
                    'type' => 'customer',
                    'gov_id' => $faker->randomElement($egyptianGovernorates),
                    'region' => $faker->streetName,
                    'opening_balance' => $config['opening_balance'],
                    'current_balance' => $config['opening_balance'],
                    'is_banned' => $type === 'bad_debt' && rand(0, 1),
                ]);
            }
        }

        // Suppliers (10 suppliers)
        echo "   Creating suppliers...\n";
        $supplierNames = [
            'شركة دمياط للأدوات المنزلية',
            'مصنع المنصورة للبلاستيك',
            'موزع الدلتا للأواني',
            'شركة النيل الأزرق للتوريدات',
            'مؤسسة رأس البر للتجارة',
            'شركة الشروق الدمياطي للاستيراد',
            'مصنع طلخا للمواد المنزلية',
            'شركة البحر المتوسط للتجارة',
            'موزع الدقهلية للمعدات',
            'شركة بورسعيد للتوكيلات',
        ];
        foreach ($supplierNames as $name) {
            // Some suppliers we owe money (negative opening balance from supplier's perspective)
            $openingBalance = rand(0, 1) ? 0 : -rand(10000, 50000);
            $this->suppliers[] = Partner::create([
                'name' => $name,
                'phone' => '02' . rand(20000000, 99999999),
                'type' => 'supplier',
                'gov_id' => $faker->randomElement($egyptianGovernorates),
                'region' => $faker->streetName,
                'opening_balance' => $openingBalance,
                'current_balance' => $openingBalance,
            ]);
        }

        // Shareholders (3 shareholders)
        echo "   Creating shareholders...\n";
        $shareholderNames = ['محمد حسن الدمياطي - شريك مؤسس', 'أحمد علي المنصوري - مستثمر', 'خالد حسن سالم - شريك صامت'];
        foreach ($shareholderNames as $name) {
            $this->shareholders[] = Partner::create([
                'name' => $name,
                'phone' => '011' . rand(10000000, 99999999),
                'type' => 'shareholder',
                'gov_id' => 'دمياط',
                'region' => $faker->streetName,
                'opening_balance' => 0,
                'current_balance' => 0,
            ]);
        }

        echo "   ✓ Created " . count($this->customers) . " customers, " .
             count($this->suppliers) . " suppliers, " .
             count($this->shareholders) . " shareholders\n";
    }

    /**
     * STEP 3: Seed Products (50+ realistic products)
     */
    private function seedProducts($faker): void
    {
        echo "📦 [3/12] Seeding products...\n";

        $pieceUnit = Unit::where('name', 'قطعة')->first();
        $cartonUnit = Unit::where('name', 'كرتونة')->first();

        if (!$pieceUnit || !$cartonUnit) {
            echo "   ⚠️  Warning: Units not found. Run UnitSeeder first.\n";
            return;
        }

        $categories = ProductCategory::all();
        if ($categories->isEmpty()) {
            echo "   ⚠️  Warning: Categories not found. Run ProductCategorySeeder first.\n";
            return;
        }

        $productNames = [
            'طبق تقديم دائري', 'صحن طعام سيراميك', 'صحن حلويات زجاج', 'طبق فاكهة كريستال',
            'كوب شاي زجاج', 'فنجان قهوة سيراميك', 'كوب ماء زجاج', 'كوب عصير بلاستيك',
            'طنجرة ضغط ستانلس', 'مقلاة تيفال', 'حلة طبخ ألومنيوم', 'طاسة جرانيت',
            'طقم ملاعق ستانلس', 'طقم شوك ستانلس', 'طقم سكاكين ستانلس', 'ملعقة تقديم خشبية',
            'مصفاة استانلس', 'لوح تقطيع بلاستيك', 'مبشرة متعددة الاستخدام', 'مضرب بيض يدوي',
            'علبة حفظ بلاستيك', 'علبة حفظ زجاج', 'طقم علب حفظ', 'برطمان زجاج محكم',
            'سكين مطبخ كبير', 'سكين تقشير صغير', 'مقص مطبخ ستانلس', 'فتاحة علب كهربائية',
            'ترمس قهوة', 'ترمس شاي', 'إبريق ماء بلاستيك', 'إبريق عصير زجاج',
            'صينية تقديم مستطيلة', 'صينية تقديم دائرية', 'صينية فرن ستانلس', 'صينية كيك مربعة',
            'طقم توابل زجاج', 'مملحة خشبية', 'طاحونة فلفل', 'وعاء سكر سيراميك',
            'قدر بخار ستانلس', 'قدر شوربة كبير', 'طاسة قلي عميقة', 'صاجة شاورما',
            'كاسة عصير ملونة', 'كاسة نبيذ كريستال', 'كوب قهوة ورقي', 'صحن كرتون مقوى',
            'شوكة بلاستيك', 'ملعقة خشبية', 'سكين بلاستيك', 'عيدان صينية خشبية',
        ];

        // Kitchen product image categories for realistic dummy URLs
        $imageCategories = [
            'plates' => ['plate', 'dish', 'dinnerware'],
            'cups' => ['cup', 'mug', 'glass'],
            'pots' => ['pot', 'pan', 'cookware'],
            'cutlery' => ['fork', 'knife', 'spoon', 'cutlery'],
            'tools' => ['kitchen-tool', 'utensil', 'gadget'],
            'storage' => ['container', 'jar', 'storage'],
            'misc' => ['kitchen', 'cookware', 'utensil'],
        ];

        foreach ($productNames as $index => $name) {
            // Determine stock level type
            $stockType = match(true) {
                $index % 5 === 0 => 'out_of_stock',  // 20% out of stock
                $index % 4 === 0 => 'low_stock',     // 25% low stock
                default => 'normal_stock'            // 55% normal stock
            };

            $minStock = rand(20, 200);
            $avgCost = rand(5, 200);
            $retailPrice = $avgCost * (1 + rand(30, 80) / 100);
            $wholesalePrice = $avgCost * (1 + rand(20, 50) / 100);
            $factor = rand(1, 1) ? 12 : 24;

            // Generate realistic kitchen product images
            $imageType = $faker->randomElement(array_keys($imageCategories));
            $keyword = $faker->randomElement($imageCategories[$imageType]);
            $imageId = $index + 1;

            // Use picsum.photos with kitchen-related seeds for consistency
            $mainImage = "https://picsum.photos/seed/kitchen-{$keyword}-{$imageId}/600/600";
            $additionalImages = [
                "https://picsum.photos/seed/kitchen-{$keyword}-{$imageId}-1/600/600",
                "https://picsum.photos/seed/kitchen-{$keyword}-{$imageId}-2/600/600",
            ];

            $this->products[] = Product::create([
                'category_id' => $categories->random()->id,
                'name' => $name . ' - ' . rand(100, 999),
                'description' => 'منتج عالي الجودة مناسب للاستخدام اليومي',
                'image' => $mainImage,
                'images' => rand(0, 1) ? $additionalImages : [$additionalImages[0]], // 50% have 2 images, 50% have 1
                'barcode' => '6111' . str_pad($index + 1, 9, '0', STR_PAD_LEFT),
                'large_barcode' => '6111' . str_pad($index + 1, 9, '0', STR_PAD_LEFT) . 'C',
                'sku' => 'PRD-' . strtoupper(substr(md5($name), 0, 8)),
                'min_stock' => $minStock,
                'avg_cost' => $avgCost,
                'small_unit_id' => $pieceUnit->id,
                'large_unit_id' => $cartonUnit->id,
                'factor' => $factor,
                'retail_price' => $retailPrice,
                'wholesale_price' => $wholesalePrice,
                'large_retail_price' => $retailPrice * $factor * 0.95,
                'large_wholesale_price' => $wholesalePrice * $factor * 0.92,
                'is_active' => true,
                'is_public' => rand(0, 10) > 2, // 80% public
            ]);
        }

        echo "   ✓ Created " . count($this->products) . " products\n";
    }

    /**
     * STEP 4: Initialize Treasury with Opening Capital from Shareholders
     */
    private function seedOpeningCapital($faker): void
    {
        echo "💰 [4/12] Depositing opening capital...\n";

        $totalCapital = 500000; // 500,000 EGP initial capital

        // Main shareholder deposits majority
        $this->treasuryService->recordTransaction(
            $this->treasuries['main']->id,
            'capital_deposit',
            $totalCapital * 0.6, // 300,000
            'رأس المال الافتتاحي - حصة الشريك الرئيسي',
            $this->shareholders[0]->id,
            null, // No reference_type for capital deposits
            null
        );

        // Second shareholder
        $this->treasuryService->recordTransaction(
            $this->treasuries['main']->id,
            'capital_deposit',
            $totalCapital * 0.3, // 150,000
            'رأس المال الافتتاحي - حصة الشريك الثاني',
            $this->shareholders[1]->id,
            null, // No reference_type for capital deposits
            null
        );

        // Third shareholder
        $this->treasuryService->recordTransaction(
            $this->treasuries['main']->id,
            'capital_deposit',
            $totalCapital * 0.1, // 50,000
            'رأس المال الافتتاحي - حصة الشريك الثالث',
            $this->shareholders[2]->id,
            null, // No reference_type for capital deposits
            null
        );

        // Deposit some to bank account
        $this->treasuryService->recordTransaction(
            $this->treasuries['bank']->id,
            'capital_deposit',
            200000,
            'إيداع رأس مال في الحساب البنكي',
            null,
            null, // No reference_type for capital deposits
            null
        );

        echo "   ✓ Deposited " . number_format($totalCapital + 200000, 2) . " EGP across treasuries\n";
    }

    /**
     * STEP 5A: Seed Quotations (Price quotes sent to customers)
     */
    private function seedQuotations($faker, int $count): void
    {
        echo "📋 [5A/12] Creating quotations...\n";

        $statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];
        $pricingTypes = ['retail', 'wholesale', 'manual'];
        $createdCount = 0;
        $convertedCount = 0;

        for ($i = 0; $i < $count; $i++) {
            // 70% to customers, 30% to guests (no partner)
            $hasPartner = rand(1, 100) <= 70;
            $customer = $hasPartner ? $faker->randomElement($this->customers) : null;

            // Determine status (60% sent, 20% draft, 10% accepted, 5% rejected, 5% expired)
            $statusRand = rand(1, 100);
            if ($statusRand <= 60) {
                $status = 'sent';
            } elseif ($statusRand <= 80) {
                $status = 'draft';
            } elseif ($statusRand <= 90) {
                $status = 'accepted';
            } elseif ($statusRand <= 95) {
                $status = 'rejected';
            } else {
                $status = 'expired';
            }

            // Create quotation
            $quotation = Quotation::create([
                'partner_id' => $customer?->id,
                'guest_name' => !$hasPartner ? $faker->name : null,
                'guest_phone' => !$hasPartner ? $faker->phoneNumber : null,
                'pricing_type' => $faker->randomElement($pricingTypes),
                'status' => $status,
                'valid_until' => $status === 'expired'
                    ? $faker->dateTimeBetween('-30 days', '-1 day')
                    : $faker->dateTimeBetween('+7 days', '+30 days'),
                'discount_type' => rand(1, 100) <= 30 ? $faker->randomElement(['percentage', 'fixed']) : null,
                'discount_value' => rand(1, 100) <= 30 ? rand(5, 20) : 0,
                'notes' => rand(1, 100) <= 40 ? 'عرض سعر خاص للعميل - ' . $faker->sentence : null,
                'internal_notes' => rand(1, 100) <= 30 ? 'ملاحظات داخلية - ' . $faker->sentence : null,
                'created_by' => $this->admin->id,
            ]);

            // Add 2-5 items to quotation
            $itemCount = rand(2, 5);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $faker->randomElement($this->products);
                $unitType = $faker->randomElement(['small', 'large']);
                $quantity = rand(1, 20);

                // Determine price based on pricing type
                $unitPrice = match($quotation->pricing_type) {
                    'retail' => $unitType === 'small' ? $product->retail_price : ($product->retail_price * $product->factor),
                    'wholesale' => $unitType === 'small' ? $product->wholesale_price : ($product->wholesale_price * $product->factor),
                    'manual' => rand(50, 500),
                };

                $itemDiscount = rand(1, 100) <= 20 ? rand(10, 100) : 0;
                $total = ($quantity * $unitPrice) - $itemDiscount;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_type' => $unitType,
                    'unit_name' => $unitType === 'small' ? $product->smallUnit->name : $product->largeUnit->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'total' => $total,
                    'notes' => rand(1, 100) <= 20 ? 'ملاحظة على الصنف' : null,
                ]);

                $subtotal += $total;
            }

            // Calculate discount
            $discount = 0;
            if ($quotation->discount_type === 'percentage') {
                $discount = ($subtotal * $quotation->discount_value) / 100;
            } elseif ($quotation->discount_type === 'fixed') {
                $discount = $quotation->discount_value;
            }

            // Update quotation totals
            $quotation->update([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
            ]);

            $createdCount++;

            // Convert some accepted quotations to sales invoices (50% of accepted)
            if ($status === 'accepted' && rand(1, 100) <= 50 && $customer) {
                try {
                    // Create sales invoice from quotation
                    $salesInvoice = SalesInvoice::create([
                        'warehouse_id' => $this->mainWarehouse->id,
                        'partner_id' => $customer->id,
                        'status' => 'draft',
                        'payment_method' => 'credit',
                        'discount_type' => $quotation->discount_type,
                        'discount_value' => $quotation->discount_value,
                        'subtotal' => $quotation->subtotal,
                        'discount' => $quotation->discount,
                        'total' => $quotation->total,
                        'paid_amount' => 0,
                        'remaining_amount' => $quotation->total,
                        'notes' => 'تم التحويل من عرض السعر ' . $quotation->quotation_number,
                        'created_by' => $this->admin->id,
                    ]);

                    // Copy items from quotation to sales invoice
                    foreach ($quotation->items as $quotationItem) {
                        SalesInvoiceItem::create([
                            'sales_invoice_id' => $salesInvoice->id,
                            'product_id' => $quotationItem->product_id,
                            'unit_type' => $quotationItem->unit_type,
                            'quantity' => $quotationItem->quantity,
                            'unit_price' => $quotationItem->unit_price,
                            'discount' => $quotationItem->discount,
                            'total' => $quotationItem->total,
                        ]);
                    }

                    // Post the sales invoice
                    $this->stockService->postSalesInvoice($salesInvoice);
                    $this->treasuryService->postSalesInvoice($salesInvoice, $this->treasuries['main']->id);
                    $salesInvoice->update(['status' => 'posted']);

                    // Mark quotation as converted
                    $quotation->update([
                        'status' => 'converted',
                        'converted_invoice_id' => $salesInvoice->id,
                    ]);

                    $convertedCount++;
                } catch (\Exception $e) {
                    // Skip if conversion fails (e.g., insufficient stock)
                    echo "   ⚠️  Failed to convert quotation {$quotation->quotation_number}: {$e->getMessage()}\n";
                }
            }
        }

        echo "   ✓ Created $createdCount quotations ($convertedCount converted to sales invoices)\n";
    }

    /**
     * STEP 5B: Seed Purchase Invoices (Stock increases)
     */
    private function seedPurchaseInvoices($faker, int $count): void
    {
        echo "📥 [5C/12] Creating purchase invoices...\n";

        $postedCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $supplier = $faker->randomElement($this->suppliers);
            $isDraft = $i >= ($count * 0.75); // 75% posted, 25% draft
            $paymentMethod = $faker->randomElement(['cash', 'cash', 'credit', 'credit', 'credit']); // 40% cash, 60% credit

            $invoice = PurchaseInvoice::create([
                'invoice_number' => 'PUR-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $this->mainWarehouse->id,
                'partner_id' => $supplier->id,
                'status' => $isDraft ? 'draft' : 'draft', // Start as draft, we'll post later
                'payment_method' => $paymentMethod,
                'discount_type' => $faker->randomElement(['percentage', 'fixed']),
                'discount_value' => 0,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'notes' => 'فاتورة شراء رقم ' . ($i + 1),
                'created_by' => $this->admin->id,
            ]);

            // Add 2-6 random items
            $itemCount = rand(2, 6);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $faker->randomElement($this->products);
                $unitType = rand(0, 1) ? 'small' : 'large';
                $quantity = rand(5, 50);
                $unitCost = rand(5, 150);

                // 50% chance to set new selling price
                $newSellingPrice = rand(0, 1) ? $unitCost * (1 + rand(40, 90) / 100) : null;
                $newLargeSellingPrice = $newSellingPrice && $unitType === 'large' ? $newSellingPrice * $product->factor * 0.95 : null;

                $itemTotal = $unitCost * $quantity;

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_type' => $unitType,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total' => $itemTotal,
                    'new_selling_price' => $newSellingPrice,
                    'new_large_selling_price' => $newLargeSellingPrice,
                ]);

                $subtotal += $itemTotal;
            }

            // Apply invoice-level discount
            $discountValue = rand(0, 1) ? rand(0, 10) : 0; // 50% have discount
            $discountAmount = $invoice->discount_type === 'percentage'
                ? ($subtotal * $discountValue / 100)
                : $discountValue;
            $total = $subtotal - $discountAmount;

            // Determine payment
            $paidAmount = 0;
            if ($paymentMethod === 'cash') {
                $paidAmount = $total; // Full payment
            } elseif ($paymentMethod === 'credit') {
                // 30% fully unpaid, 40% partially paid, 30% will be paid later
                $rand = rand(0, 9);
                if ($rand < 3) {
                    $paidAmount = 0; // Unpaid
                } elseif ($rand < 7) {
                    $paidAmount = $total * rand(30, 70) / 100; // Partial
                } else {
                    $paidAmount = 0; // Will pay later via subsequent payment
                }
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_value' => $discountValue,
                'discount' => $discountAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $total - $paidAmount,
            ]);

            // Post the invoice if not draft
            if (!$isDraft) {
                try {
                    // Post to stock first (this checks isDraft)
                    $this->stockService->postPurchaseInvoice($invoice);

                    // Post to treasury (this also checks isDraft, records payment if paid_amount > 0)
                    $this->treasuryService->postPurchaseInvoice(
                        $invoice,
                        $this->treasuries['main']->id
                    );

                    // THEN update status to posted (after both services have validated)
                    $invoice->update(['status' => 'posted']);

                    $postedCount++;
                } catch (\Exception $e) {
                    echo "   ⚠️  Failed to post invoice {$invoice->invoice_number}: {$e->getMessage()}\n";
                }
            }
        }

        echo "   ✓ Created $count purchase invoices ($postedCount posted, " . ($count - $postedCount) . " draft)\n";
    }

    /**
     * STEP 5B: Seed Sales Invoices (Stock decreases, Revenue)
     */
    private function seedSalesInvoices($faker, int $count): void
    {
        echo "📤 [5D/12] Creating sales invoices...\n";

        $postedCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $customer = $faker->randomElement($this->customers);
            $isDraft = $i >= ($count * 0.67); // 67% posted, 33% draft
            $paymentMethod = $faker->randomElement(['cash', 'cash', 'cash', 'credit', 'credit', 'credit', 'credit']); // 30% cash, 70% credit

            $invoice = SalesInvoice::create([
                'invoice_number' => 'SAL-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $this->mainWarehouse->id,
                'partner_id' => $customer->id,
                'status' => $isDraft ? 'draft' : 'draft',
                'payment_method' => $paymentMethod,
                'discount_type' => $faker->randomElement(['percentage', 'fixed', 'fixed']),
                'discount_value' => 0,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'notes' => 'فاتورة بيع رقم ' . ($i + 1),
                'created_by' => $this->admin->id,
            ]);

            // Add 1-5 random items
            $itemCount = rand(1, 5);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $faker->randomElement($this->products);
                $unitType = rand(0, 1) ? 'small' : 'large';
                $quantity = rand(1, 20);

                $unitPrice = $unitType === 'small'
                    ? $product->retail_price
                    : $product->large_retail_price;

                // Item-level discount (30% of items have discount)
                $itemDiscount = rand(0, 10) < 3 ? rand(5, 50) : 0;
                $itemTotal = ($unitPrice * $quantity) - $itemDiscount;

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_type' => $unitType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'total' => $itemTotal,
                ]);

                $subtotal += $itemTotal;
            }

            // Apply invoice-level discount (40% of invoices)
            $discountValue = rand(0, 10) < 4 ? rand(5, 15) : 0;
            $discountAmount = $invoice->discount_type === 'percentage'
                ? ($subtotal * $discountValue / 100)
                : min($discountValue, $subtotal * 0.2);
            $total = $subtotal - $discountAmount;

            // Determine payment
            $paidAmount = 0;
            if ($paymentMethod === 'cash') {
                $paidAmount = $total; // Full payment
            } elseif ($paymentMethod === 'credit') {
                // 20% fully unpaid, 25% partially paid, 30% fully paid later, 25% overdue
                $rand = rand(0, 19);
                if ($rand < 4) {
                    $paidAmount = 0; // Unpaid
                } elseif ($rand < 9) {
                    $paidAmount = $total * rand(20, 60) / 100; // Partial
                } elseif ($rand < 15) {
                    $paidAmount = 0; // Will pay later
                } else {
                    $paidAmount = 0; // Overdue (will mark as old invoice)
                }
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_value' => $discountValue,
                'discount' => $discountAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $total - $paidAmount,
            ]);

            // Post the invoice if not draft
            if (!$isDraft) {
                try {
                    // Post to stock first (this checks isDraft)
                    $this->stockService->postSalesInvoice($invoice);

                    // Post to treasury (this also checks isDraft, records payment if paid_amount > 0)
                    $this->treasuryService->postSalesInvoice(
                        $invoice,
                        $this->treasuries['main']->id
                    );

                    // THEN update status to posted (after both services have validated)
                    $invoice->update(['status' => 'posted']);

                    $postedCount++;
                } catch (\Exception $e) {
                    echo "   ⚠️  Failed to post invoice {$invoice->invoice_number}: {$e->getMessage()}\n";
                    // Continue with next invoice
                }
            }
        }

        echo "   ✓ Created $count sales invoices ($postedCount posted, " . ($count - $postedCount) . " draft)\n";
    }

    /**
     * STEP 5D: Seed Sales Invoices with Installment Plans
     */
    private function seedSalesInvoicesWithInstallments($faker, int $count): void
    {
        echo "📅 [5E/12] Creating sales invoices with installment plans...\n";

        $createdCount = 0;
        $installmentsGeneratedCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $customer = $faker->randomElement($this->customers);

            // Installment invoices are always on credit
            $invoice = SalesInvoice::create([
                'invoice_number' => 'SAL-INST-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $this->mainWarehouse->id,
                'partner_id' => $customer->id,
                'status' => 'draft',
                'payment_method' => 'credit',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'has_installment_plan' => true,
                'installment_months' => $faker->randomElement([3, 6, 12, 24]), // 3, 6, 12, or 24 months
                'installment_start_date' => now()->addDays(rand(1, 15)),
                'installment_notes' => 'خطة تقسيط - ' . $faker->sentence,
                'notes' => 'فاتورة بالتقسيط - عميل VIP',
                'created_by' => $this->admin->id,
            ]);

            // Add 2-6 random items (larger orders for installment plans)
            $itemCount = rand(2, 6);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $faker->randomElement($this->products);
                $unitType = rand(0, 1) ? 'small' : 'large';
                $quantity = rand(5, 30); // Higher quantities for installment plans

                $unitPrice = $unitType === 'small'
                    ? $product->retail_price
                    : $product->large_retail_price;

                $itemDiscount = rand(0, 10) < 2 ? rand(10, 100) : 0;
                $itemTotal = ($unitPrice * $quantity) - $itemDiscount;

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_type' => $unitType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'total' => $itemTotal,
                ]);

                $subtotal += $itemTotal;
            }

            // Calculate totals (minimal discount for installment plans)
            $discountAmount = rand(0, 10) < 3 ? rand(100, 500) : 0;
            $total = $subtotal - $discountAmount;

            // Down payment (20-40% of total)
            $downPaymentPercent = rand(20, 40);
            $paidAmount = $total * $downPaymentPercent / 100;

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_value' => $discountAmount,
                'discount' => $discountAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $total - $paidAmount,
            ]);

            // Post the invoice
            try {
                // Post to stock first
                $this->stockService->postSalesInvoice($invoice);

                // Post to treasury (with down payment)
                $this->treasuryService->postSalesInvoice(
                    $invoice,
                    $this->treasuries['main']->id
                );

                // THEN update status to posted
                $invoice->update(['status' => 'posted']);

                // Generate installment schedule using the service
                if ($invoice->remaining_amount > 0) {
                    try {
                        $this->installmentService->generateInstallmentSchedule($invoice);
                        $installmentsGeneratedCount++;
                    } catch (\Exception $e) {
                        echo "   ⚠️  Failed to generate installments for {$invoice->invoice_number}: {$e->getMessage()}\n";
                    }
                }

                $createdCount++;
            } catch (\Exception $e) {
                echo "   ⚠️  Failed to post installment invoice {$invoice->invoice_number}: {$e->getMessage()}\n";
            }
        }

        echo "   ✓ Created $createdCount installment invoices ($installmentsGeneratedCount with installment schedules)\n";
    }

    /**
     * STEP 5E: Seed Returns (Both Sales & Purchase Returns)
     */
    private function seedReturns($faker): void
    {
        echo "↩️  [5F/12] Creating returns...\n";

        $salesReturnsCount = 0;
        $purchaseReturnsCount = 0;

        // Sales Returns (from posted sales invoices)
        $postedSalesInvoices = SalesInvoice::where('status', 'posted')
            ->with('items.product')
            ->get();

        foreach ($postedSalesInvoices->random(min(12, $postedSalesInvoices->count())) as $index => $invoice) {
            $paymentMethod = $faker->randomElement(['cash', 'credit']); // 50/50 cash vs credit

            $salesReturn = SalesReturn::create([
                'return_number' => 'SR-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'sales_invoice_id' => $invoice->id,
                'warehouse_id' => $invoice->warehouse_id,
                'partner_id' => $invoice->partner_id,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'subtotal' => 0,
                'total' => 0,
                'notes' => 'مرتجع فاتورة ' . $invoice->invoice_number,
                'created_by' => $this->admin->id,
            ]);

            // Return 1-2 items from the original invoice
            $itemsToReturn = $invoice->items->random(min(rand(1, 2), $invoice->items->count()));
            $total = 0;

            foreach ($itemsToReturn as $originalItem) {
                $returnQuantity = min(rand(1, $originalItem->quantity), $originalItem->quantity);
                $returnTotal = ($originalItem->unit_price * $returnQuantity) -
                              (($originalItem->discount / $originalItem->quantity) * $returnQuantity);

                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id' => $originalItem->product_id,
                    'unit_type' => $originalItem->unit_type,
                    'quantity' => $returnQuantity,
                    'unit_price' => $originalItem->unit_price,
                    'total' => $returnTotal,
                ]);

                $total += $returnTotal;
            }

            $salesReturn->update([
                'subtotal' => $total,
                'total' => $total,
            ]);

            // Post the return
            try {
                // Post to stock first (checks isDraft)
                $this->stockService->postSalesReturn($salesReturn);

                // Post to treasury (checks isDraft)
                $this->treasuryService->postSalesReturn(
                    $salesReturn,
                    $this->treasuries['main']->id
                );

                // THEN update status
                $salesReturn->update(['status' => 'posted']);

                $salesReturnsCount++;
            } catch (\Exception $e) {
                echo "   ⚠️  Failed to post sales return {$salesReturn->return_number}: {$e->getMessage()}\n";
            }
        }

        // Purchase Returns (from posted purchase invoices)
        $postedPurchaseInvoices = PurchaseInvoice::where('status', 'posted')
            ->with('items.product')
            ->get();

        foreach ($postedPurchaseInvoices->random(min(8, $postedPurchaseInvoices->count())) as $index => $invoice) {
            $paymentMethod = $faker->randomElement(['cash', 'credit']);

            $purchaseReturn = PurchaseReturn::create([
                'return_number' => 'PR-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'purchase_invoice_id' => $invoice->id,
                'warehouse_id' => $invoice->warehouse_id,
                'partner_id' => $invoice->partner_id,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'subtotal' => 0,
                'total' => 0,
                'notes' => 'مرتجع فاتورة شراء ' . $invoice->invoice_number,
                'created_by' => $this->admin->id,
            ]);

            $itemsToReturn = $invoice->items->random(min(rand(1, 2), $invoice->items->count()));
            $total = 0;

            foreach ($itemsToReturn as $originalItem) {
                $returnQuantity = min(rand(1, $originalItem->quantity), $originalItem->quantity);
                $returnTotal = $originalItem->unit_cost * $returnQuantity;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $originalItem->product_id,
                    'unit_type' => $originalItem->unit_type,
                    'quantity' => $returnQuantity,
                    'unit_cost' => $originalItem->unit_cost,
                    'total' => $returnTotal,
                ]);

                $total += $returnTotal;
            }

            $purchaseReturn->update([
                'subtotal' => $total,
                'total' => $total,
            ]);

            // Post the return
            try {
                // Post to stock first (checks isDraft)
                $this->stockService->postPurchaseReturn($purchaseReturn);

                // Post to treasury (checks isDraft)
                $this->treasuryService->postPurchaseReturn(
                    $purchaseReturn,
                    $this->treasuries['main']->id
                );

                // THEN update status
                $purchaseReturn->update(['status' => 'posted']);

                $purchaseReturnsCount++;
            } catch (\Exception $e) {
                echo "   ⚠️  Failed to post purchase return {$purchaseReturn->return_number}: {$e->getMessage()}\n";
            }
        }

        echo "   ✓ Created $salesReturnsCount sales returns, $purchaseReturnsCount purchase returns\n";
    }

    /**
     * STEP 5D: Seed Expenses
     */
    private function seedExpenses($faker, int $count): void
    {
        echo "💸 [5G/12] Creating expenses...\n";

        $expenseCategories = [
            ['title' => 'إيجار المكتب', 'amount' => [8000, 12000], 'treasury' => 'main'],
            ['title' => 'فاتورة كهرباء', 'amount' => [800, 2000], 'treasury' => 'petty_cash'],
            ['title' => 'فاتورة مياه', 'amount' => [200, 500], 'treasury' => 'petty_cash'],
            ['title' => 'فاتورة إنترنت', 'amount' => [300, 800], 'treasury' => 'bank'],
            ['title' => 'مرتبات الموظفين', 'amount' => [20000, 40000], 'treasury' => 'bank'],
            ['title' => 'صيانة معدات', 'amount' => [500, 3000], 'treasury' => 'main'],
            ['title' => 'مصاريف تسويق', 'amount' => [1000, 5000], 'treasury' => 'main'],
            ['title' => 'مصاريف إدارية', 'amount' => [500, 2000], 'treasury' => 'petty_cash'],
            ['title' => 'أدوات مكتبية', 'amount' => [200, 1000], 'treasury' => 'petty_cash'],
            ['title' => 'وقود ومواصلات', 'amount' => [1000, 3000], 'treasury' => 'main'],
        ];

        $createdCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $category = $faker->randomElement($expenseCategories);
            $treasury = $this->treasuries[$category['treasury']];
            $amount = rand($category['amount'][0], $category['amount'][1]);

            // Check if treasury has sufficient balance
            $currentBalance = $this->treasuryService->getTreasuryBalance($treasury->id);

            if ($currentBalance < $amount) {
                // Skip this expense if insufficient balance
                echo "   ⚠️  Skipping expense (insufficient balance in {$treasury->name})\n";
                continue;
            }

            try {
                $expense = Expense::create([
                    'title' => $category['title'] . ' - ' . $faker->monthName,
                    'description' => 'مصروف تشغيلي',
                    'amount' => $amount,
                    'treasury_id' => $treasury->id,
                    'expense_date' => $faker->dateTimeBetween('-60 days', 'now'),
                    'created_by' => $this->admin->id,
                ]);

                // Post expense to treasury
                $this->treasuryService->postExpense($expense);
                $createdCount++;
            } catch (\Exception $e) {
                echo "   ⚠️  Failed to create expense: {$e->getMessage()}\n";
            }
        }

        echo "   ✓ Created $createdCount expenses\n";
    }

    /**
     * STEP 5E: Seed Revenues
     */
    private function seedRevenues($faker, int $count): void
    {
        echo "💰 [5H/12] Creating revenues...\n";

        $revenueCategories = [
            ['title' => 'عمولة وساطة', 'amount' => [1000, 5000]],
            ['title' => 'إيرادات خدمات', 'amount' => [2000, 8000]],
            ['title' => 'فوائد بنكية', 'amount' => [500, 2000]],
            ['title' => 'إيرادات استشارات', 'amount' => [3000, 10000]],
            ['title' => 'إيرادات متنوعة', 'amount' => [500, 3000]],
        ];

        for ($i = 0; $i < $count; $i++) {
            $category = $faker->randomElement($revenueCategories);
            $treasury = $faker->randomElement([$this->treasuries['main'], $this->treasuries['bank']]);

            $revenue = Revenue::create([
                'title' => $category['title'],
                'description' => 'إيراد إضافي',
                'amount' => rand($category['amount'][0], $category['amount'][1]),
                'treasury_id' => $treasury->id,
                'revenue_date' => $faker->dateTimeBetween('-60 days', 'now'),
                'created_by' => $this->admin->id,
            ]);

            // Post revenue to treasury
            $this->treasuryService->postRevenue($revenue);
        }

        echo "   ✓ Created $count revenues\n";
    }

    /**
     * STEP 5F: Seed Internal Treasury Transfers
     */
    private function seedTreasuryTransfers($faker, int $count): void
    {
        echo "🔄 [5I/12] Creating treasury transfers...\n";

        $transferPairs = [
            ['from' => 'main', 'to' => 'bank', 'description' => 'إيداع نقدية في البنك'],
            ['from' => 'bank', 'to' => 'main', 'description' => 'سحب نقدية من البنك'],
            ['from' => 'main', 'to' => 'petty_cash', 'description' => 'تغذية خزينة المصروفات الصغيرة'],
            ['from' => 'branch', 'to' => 'main', 'description' => 'تحويل من الفرع للخزينة الرئيسية'],
        ];

        for ($i = 0; $i < $count; $i++) {
            $transfer = $faker->randomElement($transferPairs);
            $amount = rand(5000, 50000);

            try {
                // Withdrawal from source (use 'payment' for outgoing transfer)
                $this->treasuryService->recordTransaction(
                    $this->treasuries[$transfer['from']]->id,
                    'payment',
                    -$amount,
                    $transfer['description'] . ' (تحويل #' . ($i + 1) . ')',
                    null,
                    null,
                    null
                );

                // Deposit to destination (use 'income' for incoming transfer)
                $this->treasuryService->recordTransaction(
                    $this->treasuries[$transfer['to']]->id,
                    'income',
                    $amount,
                    $transfer['description'] . ' (استلام #' . ($i + 1) . ')',
                    null,
                    null,
                    null
                );
            } catch (\Exception $e) {
                echo "   ⚠️  Transfer failed: {$e->getMessage()}\n";
            }
        }

        echo "   ✓ Created $count treasury transfers\n";
    }

    /**
     * STEP 5G: Seed Subsequent Invoice Payments
     */
    private function seedSubsequentPayments($faker, int $count): void
    {
        echo "💳 [5J/12] Creating subsequent invoice payments...\n";

        // Get credit invoices with remaining balance
        $unpaidSalesInvoices = SalesInvoice::where('status', 'posted')
            ->where('payment_method', 'credit')
            ->where('remaining_amount', '>', 0)
            ->get();

        $unpaidPurchaseInvoices = PurchaseInvoice::where('status', 'posted')
            ->where('payment_method', 'credit')
            ->where('remaining_amount', '>', 0)
            ->get();

        $paymentsCreated = 0;

        // Sales invoice payments (collections)
        foreach ($unpaidSalesInvoices->random(min(15, $unpaidSalesInvoices->count())) as $invoice) {
            $remainingAmount = floatval($invoice->remaining_amount);

            // Random payment: full, partial, or with discount
            $paymentType = rand(0, 2);

            if ($paymentType === 0) {
                // Full payment
                $amount = $remainingAmount;
                $discount = 0;
            } elseif ($paymentType === 1) {
                // Partial payment
                $amount = $remainingAmount * rand(30, 70) / 100;
                $discount = 0;
            } else {
                // Full payment with settlement discount
                $discount = $remainingAmount * rand(5, 15) / 100;
                $amount = $remainingAmount - $discount;
            }

            try {
                $this->treasuryService->recordInvoicePayment(
                    $invoice,
                    $amount,
                    $discount,
                    $this->treasuries['main']->id,
                    'تسديد جزئي/كلي من العميل'
                );
                $paymentsCreated++;
            } catch (\Exception $e) {
                // Skip if insufficient balance or other error
            }
        }

        // Purchase invoice payments
        foreach ($unpaidPurchaseInvoices->random(min(10, $unpaidPurchaseInvoices->count())) as $invoice) {
            $remainingAmount = floatval($invoice->remaining_amount);

            $paymentType = rand(0, 2);

            if ($paymentType === 0) {
                $amount = $remainingAmount;
                $discount = 0;
            } elseif ($paymentType === 1) {
                $amount = $remainingAmount * rand(30, 70) / 100;
                $discount = 0;
            } else {
                $discount = $remainingAmount * rand(3, 10) / 100;
                $amount = $remainingAmount - $discount;
            }

            try {
                $this->treasuryService->recordInvoicePayment(
                    $invoice,
                    $amount,
                    $discount,
                    $this->treasuries['bank']->id,
                    'سداد للمورد'
                );
                $paymentsCreated++;
            } catch (\Exception $e) {
                // Skip if insufficient balance
            }
        }

        echo "   ✓ Created $paymentsCreated subsequent payments\n";
    }

    /**
     * STEP 6: Recalculate all partner balances
     */
    private function recalculatePartnerBalances(): void
    {
        echo "🔄 Recalculating partner balances...\n";

        $partners = Partner::all();
        foreach ($partners as $partner) {
            $partner->recalculateBalance();
        }

        echo "   ✓ Recalculated " . $partners->count() . " partner balances\n";
    }

    /**
     * Print Summary Statistics
     */
    private function printSummary(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 SEEDING SUMMARY\n";
        echo str_repeat("=", 60) . "\n";

        // Partners
        $customersCount = Partner::where('type', 'customer')->count();
        $suppliersCount = Partner::where('type', 'supplier')->count();
        $shareholdersCount = Partner::where('type', 'shareholder')->count();
        echo "👥 Partners: $customersCount customers, $suppliersCount suppliers, $shareholdersCount shareholders\n";

        // Products
        $productsCount = Product::count();
        echo "📦 Products: $productsCount products created\n";

        // Quotations
        $quotationsCount = Quotation::count();
        $quotationsConverted = Quotation::where('status', 'converted')->count();
        echo "📋 Quotations: $quotationsCount quotations ($quotationsConverted converted to invoices)\n";

        // Invoices
        $salesCount = SalesInvoice::count();
        $salesPosted = SalesInvoice::where('status', 'posted')->count();
        $salesWithInstallments = SalesInvoice::where('has_installment_plan', true)->count();
        $purchaseCount = PurchaseInvoice::count();
        $purchasePosted = PurchaseInvoice::where('status', 'posted')->count();
        echo "📄 Invoices: $salesCount sales ($salesPosted posted, $salesWithInstallments with installments), $purchaseCount purchases ($purchasePosted posted)\n";

        // Installments
        $installmentsCount = Installment::count();
        $installmentsPaid = Installment::where('status', 'paid')->count();
        $installmentsOverdue = Installment::where('status', 'overdue')->count();
        echo "📅 Installments: $installmentsCount total ($installmentsPaid paid, $installmentsOverdue overdue)\n";

        // Returns
        $salesReturns = SalesReturn::where('status', 'posted')->count();
        $purchaseReturns = PurchaseReturn::where('status', 'posted')->count();
        echo "↩️  Returns: $salesReturns sales returns, $purchaseReturns purchase returns\n";

        // Expenses & Revenues
        $expensesCount = Expense::count();
        $revenuesCount = Revenue::count();
        echo "💰 Finance: $expensesCount expenses, $revenuesCount revenues\n";

        // Treasury Balances
        echo "🏦 Treasury Balances:\n";
        foreach ($this->treasuries as $key => $treasury) {
            $balance = $this->treasuryService->getTreasuryBalance($treasury->id);
            echo "   - {$treasury->name}: " . number_format($balance, 2) . " EGP\n";
        }

        // Stock Summary
        $totalStockMovements = \App\Models\StockMovement::count();
        echo "📊 Stock: $totalStockMovements stock movements recorded\n";

        // Transactions
        $treasuryTransactions = TreasuryTransaction::count();
        echo "💳 Transactions: $treasuryTransactions treasury transactions\n";

        echo str_repeat("=", 60) . "\n";
    }
}
