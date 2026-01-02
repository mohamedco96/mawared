<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Partner;
use App\Models\PurchaseReturn;
use App\Services\TreasuryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixFinancialDiscrepancies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:fix-discrepancies {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix financial discrepancies: missing expense treasury transactions and purchase return partner balances';

    protected TreasuryService $treasuryService;

    public function __construct(TreasuryService $treasuryService)
    {
        parent::__construct();
        $this->treasuryService = $treasuryService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting financial discrepancy repair...');
        $this->newLine();

        // Fix 1: Missing Expense Treasury Transactions
        $this->fixMissingExpenseTreasuryTransactions($isDryRun);

        // Fix 2: Recalculate Partner Balances (for purchase returns)
        $this->fixPartnerBalances($isDryRun);

        $this->newLine();
        $this->info('✅ Financial discrepancy repair completed!');

        if ($isDryRun) {
            $this->newLine();
            $this->warn('This was a DRY RUN. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }

    /**
     * Fix missing treasury transactions for expenses
     */
    protected function fixMissingExpenseTreasuryTransactions(bool $isDryRun): void
    {
        $this->info('🔧 Checking for expenses without treasury transactions...');

        $expenses = Expense::all();
        $fixed = 0;
        $alreadyFixed = 0;

        foreach ($expenses as $expense) {
            $hasTransaction = $expense->treasuryTransactions()->exists();

            if (!$hasTransaction) {
                $this->line("  Found expense without treasury transaction:");
                $this->line("    ID: {$expense->id}");
                $this->line("    Title: {$expense->title}");
                $this->line("    Amount: " . number_format($expense->amount, 2));
                $this->line("    Date: {$expense->expense_date}");

                if (!$isDryRun) {
                    try {
                        DB::transaction(function () use ($expense) {
                            $this->treasuryService->recordTransaction(
                                treasuryId: $expense->treasury_id,
                                type: 'payment',
                                amount: (string) (-1 * abs(floatval($expense->amount))),
                                description: "مصروف: {$expense->title} (تسوية)",
                                partnerId: null,
                                referenceType: get_class($expense),
                                referenceId: $expense->id
                            );
                        });

                        $this->info("    ✓ Created treasury transaction");
                        $fixed++;
                    } catch (\Exception $e) {
                        $this->error("    ✗ Failed to create transaction: {$e->getMessage()}");
                    }
                } else {
                    $this->warn("    → Would create treasury transaction");
                    $fixed++;
                }

                $this->newLine();
            } else {
                $alreadyFixed++;
            }
        }

        $this->info("Summary:");
        $this->line("  Expenses with transactions: {$alreadyFixed}");
        if ($isDryRun) {
            $this->line("  Expenses that would be fixed: {$fixed}");
        } else {
            $this->line("  Expenses fixed: {$fixed}");
        }
        $this->newLine();
    }

    /**
     * Recalculate partner balances (handles purchase returns and other discrepancies)
     */
    protected function fixPartnerBalances(bool $isDryRun): void
    {
        $this->info('🔧 Recalculating partner balances...');

        $partners = Partner::all();
        $updated = 0;
        $unchanged = 0;

        foreach ($partners as $partner) {
            $currentBalance = $partner->current_balance;
            $calculatedBalance = $partner->calculateBalance();

            if (abs($currentBalance - $calculatedBalance) > 0.01) {
                $this->line("  Partner balance mismatch:");
                $this->line("    Name: {$partner->name}");
                $this->line("    Type: {$partner->type}");
                $this->line("    Current: " . number_format($currentBalance, 2));
                $this->line("    Calculated: " . number_format($calculatedBalance, 2));
                $this->line("    Difference: " . number_format($calculatedBalance - $currentBalance, 2));

                if (!$isDryRun) {
                    $partner->update(['current_balance' => $calculatedBalance]);
                    $this->info("    ✓ Updated balance");
                    $updated++;
                } else {
                    $this->warn("    → Would update balance");
                    $updated++;
                }

                $this->newLine();
            } else {
                $unchanged++;
            }
        }

        $this->info("Summary:");
        $this->line("  Partners with correct balance: {$unchanged}");
        if ($isDryRun) {
            $this->line("  Partners that would be updated: {$updated}");
        } else {
            $this->line("  Partners updated: {$updated}");
        }
        $this->newLine();
    }
}
