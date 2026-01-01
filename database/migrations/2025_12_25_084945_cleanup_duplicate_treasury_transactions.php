<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DISABLED - CRITICAL SAFETY FIX
        // Pattern-based deletion is too risky for production
        // This migration could accidentally delete legitimate transactions
        // Manual review and targeted cleanup required instead

        Log::warning('Cleanup migration DISABLED for safety - pattern-based deletion too risky');
        return;

        /* DISABLED CODE - DO NOT UNCOMMENT WITHOUT CAREFUL REVIEW
        DB::transaction(function () {
            // Delete duplicate credit transactions (those with "(Credit)" or "(Paid Portion)" or "(آجل)" in description)
            // These are the transactions that incorrectly recorded the remaining_amount

            $deletedCount = DB::table('treasury_transactions')
                ->where(function ($query) {
                    $query->where('description', 'like', '%(Credit)%')
                          ->orWhere('description', 'like', '%(Paid Portion)%')
                          ->orWhere('description', 'like', '%(آجل)%');
                })
                ->whereIn('reference_type', [
                    'sales_invoice',
                    'purchase_invoice',
                    'sales_return',
                    'purchase_return'
                ])
                ->delete();

            Log::info("Cleaned up {$deletedCount} duplicate treasury transactions");

            echo "✓ Deleted {$deletedCount} duplicate treasury transactions\n";
        });
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be reversed as the duplicate transactions
        // were incorrectly created and should not be restored
        Log::warning('Cleanup migration cannot be reversed - duplicate transactions will not be restored');
    }
};
