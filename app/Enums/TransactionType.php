<?php

namespace App\Enums;

enum TransactionType: string
{
    // Commercial Category
    case COLLECTION = 'collection';
    case PAYMENT = 'payment';
    case REFUND = 'refund';

    // Partners Category (includes HR)
    case CAPITAL_DEPOSIT = 'capital_deposit';
    case PARTNER_DRAWING = 'partner_drawing';
    case PARTNER_LOAN_RECEIPT = 'partner_loan_receipt';
    case PARTNER_LOAN_REPAYMENT = 'partner_loan_repayment';
    case EMPLOYEE_ADVANCE = 'employee_advance';
    case SALARY_PAYMENT = 'salary_payment';

    // System Types (not user-selectable)
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case PROFIT_ALLOCATION = 'profit_allocation';
    case ASSET_CONTRIBUTION = 'asset_contribution';
    case DEPRECIATION_EXPENSE = 'depreciation_expense';
    case COMMISSION_PAYOUT = 'commission_payout';
    case COMMISSION_REVERSAL = 'commission_reversal';

    /**
     * Get the category this transaction type belongs to
     */
    public function getCategory(): string
    {
        return match($this) {
            self::COLLECTION, self::PAYMENT, self::REFUND => 'commercial',
            self::CAPITAL_DEPOSIT, self::PARTNER_DRAWING,
            self::PARTNER_LOAN_RECEIPT, self::PARTNER_LOAN_REPAYMENT,
            self::EMPLOYEE_ADVANCE, self::SALARY_PAYMENT,
            self::PROFIT_ALLOCATION, self::ASSET_CONTRIBUTION,
            self::COMMISSION_PAYOUT, self::COMMISSION_REVERSAL => 'partners',
            self::INCOME, self::EXPENSE, self::DEPRECIATION_EXPENSE => 'system',
        };
    }

    /**
     * Get Arabic label for the transaction type
     */
    public function getLabel(): string
    {
        return match($this) {
            self::COLLECTION => 'تحصيل من عميل (دخول فلوس)',
            self::PAYMENT => 'سداد لمورد (خروج فلوس)',
            self::REFUND => 'مرتجع',
            self::CAPITAL_DEPOSIT => 'إيداع رأس مال (فلوس من شريك)',
            self::PARTNER_DRAWING => 'سحب شريك (خروج فلوس)',
            self::PARTNER_LOAN_RECEIPT => 'قرض من شريك (دخول فلوس)',
            self::PARTNER_LOAN_REPAYMENT => 'سداد قرض لشريك (خروج فلوس)',
            self::EMPLOYEE_ADVANCE => 'سلفة موظف (خروج فلوس)',
            self::SALARY_PAYMENT => 'راتب موظف (خروج فلوس)',
            self::INCOME => 'إيراد آخر (دخول فلوس)',
            self::EXPENSE => 'مصروف تشغيلي (خروج فلوس)',
            self::PROFIT_ALLOCATION => 'توزيع أرباح',
            self::ASSET_CONTRIBUTION => 'مساهمة بأصل ثابت',
            self::DEPRECIATION_EXPENSE => 'استهلاك أصول',
            self::COMMISSION_PAYOUT => 'دفع عمولة مبيعات (خروج)',
            self::COMMISSION_REVERSAL => 'عكس عمولة (مرتجع)',
        };
    }

    /**
     * Get the sign for amount calculations (+1 for deposits, -1 for withdrawals)
     */
    public function getSign(): int
    {
        return match($this) {
            self::COLLECTION, self::CAPITAL_DEPOSIT, self::INCOME,
            self::PARTNER_LOAN_RECEIPT, self::PROFIT_ALLOCATION, self::ASSET_CONTRIBUTION,
            self::COMMISSION_REVERSAL => 1,
            self::PAYMENT, self::PARTNER_DRAWING, self::EMPLOYEE_ADVANCE,
            self::SALARY_PAYMENT, self::EXPENSE, self::PARTNER_LOAN_REPAYMENT,
            self::REFUND, self::DEPRECIATION_EXPENSE, self::COMMISSION_PAYOUT => -1,
        };
    }

    /**
     * Get badge color for display
     */
    public function getColor(): string
    {
        return match($this) {
            self::COLLECTION, self::INCOME, self::CAPITAL_DEPOSIT,
            self::PARTNER_LOAN_RECEIPT, self::PROFIT_ALLOCATION, self::ASSET_CONTRIBUTION => 'success',
            self::PAYMENT, self::EXPENSE, self::PARTNER_DRAWING,
            self::EMPLOYEE_ADVANCE, self::SALARY_PAYMENT, self::PARTNER_LOAN_REPAYMENT,
            self::DEPRECIATION_EXPENSE, self::COMMISSION_PAYOUT => 'danger',
            self::REFUND, self::COMMISSION_REVERSAL => 'warning',
        };
    }

    /**
     * Check if this type is available for manual user selection
     */
    public function isUserSelectable(): bool
    {
        // System types are created by services only, not selectable by users
        return !in_array($this, [
            self::INCOME,
            self::EXPENSE,
            self::REFUND,
            self::PROFIT_ALLOCATION,
            self::ASSET_CONTRIBUTION,
            self::DEPRECIATION_EXPENSE,
            self::COMMISSION_PAYOUT,
            self::COMMISSION_REVERSAL,
        ]);
    }

    /**
     * Get all types for a specific category
     */
    public static function forCategory(string $category): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getCategory() === $category && $type->isUserSelectable()
        );
    }

    /**
     * Get category label in Arabic
     */
    public static function getCategoryLabel(string $category): string
    {
        return match($category) {
            'commercial' => 'عمليات تجارية (قبض وصرف)',
            'partners' => 'عمليات الشركاء والموظفين (أصحاب الشغل)',
            default => $category,
        };
    }

    /**
     * Get all user-selectable types as options array for Filament
     */
    public static function getSelectOptions(): array
    {
        return collect(self::cases())
            ->filter(fn(self $type) => $type->isUserSelectable())
            ->mapWithKeys(fn(self $type) => [$type->value => $type->getLabel()])
            ->toArray();
    }

    /**
     * Get all categories as options array for Filament
     */
    public static function getCategoryOptions(): array
    {
        return [
            'commercial' => self::getCategoryLabel('commercial'),
            'partners' => self::getCategoryLabel('partners'),
        ];
    }
}
