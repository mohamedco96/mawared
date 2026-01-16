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

    /**
     * Get the category this transaction type belongs to
     */
    public function getCategory(): string
    {
        return match($this) {
            self::COLLECTION, self::PAYMENT, self::REFUND => 'commercial',
            self::CAPITAL_DEPOSIT, self::PARTNER_DRAWING,
            self::PARTNER_LOAN_RECEIPT, self::PARTNER_LOAN_REPAYMENT,
            self::EMPLOYEE_ADVANCE, self::SALARY_PAYMENT => 'partners',
            self::INCOME, self::EXPENSE => 'system',
        };
    }

    /**
     * Get Arabic label for the transaction type
     */
    public function getLabel(): string
    {
        return match($this) {
            self::COLLECTION => 'تحصيل من عميل',
            self::PAYMENT => 'دفع لمورد',
            self::REFUND => 'مرتجع',
            self::CAPITAL_DEPOSIT => 'إيداع رأس المال',
            self::PARTNER_DRAWING => 'سحب شريك',
            self::PARTNER_LOAN_RECEIPT => 'استلام قرض من شريك',
            self::PARTNER_LOAN_REPAYMENT => 'سداد قرض لشريك',
            self::EMPLOYEE_ADVANCE => 'سلفة موظف',
            self::SALARY_PAYMENT => 'راتب موظف',
            self::INCOME => 'إيراد آخر',
            self::EXPENSE => 'مصروف تشغيلي',
        };
    }

    /**
     * Get the sign for amount calculations (+1 for deposits, -1 for withdrawals)
     */
    public function getSign(): int
    {
        return match($this) {
            self::COLLECTION, self::CAPITAL_DEPOSIT, self::INCOME,
            self::PARTNER_LOAN_RECEIPT => 1,
            self::PAYMENT, self::PARTNER_DRAWING, self::EMPLOYEE_ADVANCE,
            self::SALARY_PAYMENT, self::EXPENSE, self::PARTNER_LOAN_REPAYMENT, self::REFUND => -1,
        };
    }

    /**
     * Get badge color for display
     */
    public function getColor(): string
    {
        return match($this) {
            self::COLLECTION, self::INCOME, self::CAPITAL_DEPOSIT,
            self::PARTNER_LOAN_RECEIPT => 'success',
            self::PAYMENT, self::EXPENSE, self::PARTNER_DRAWING,
            self::EMPLOYEE_ADVANCE, self::SALARY_PAYMENT, self::PARTNER_LOAN_REPAYMENT => 'danger',
            self::REFUND => 'warning',
        };
    }

    /**
     * Check if this type is available for manual user selection
     */
    public function isUserSelectable(): bool
    {
        // System types (income, expense, refund) are created by TreasuryService only
        return !in_array($this, [self::INCOME, self::EXPENSE, self::REFUND]);
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
            'commercial' => 'عمليات تجارية',
            'partners' => 'عمليات الشركاء والموظفين',
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
