<?php

use App\Models\Partner;
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
        DB::transaction(function () {
            $partners = Partner::all();
            $count = 0;

            echo "Recalculating balances for {$partners->count()} partners...\n";

            foreach ($partners as $partner) {
                $oldBalance = $partner->current_balance;
                $partner->recalculateBalance();
                $newBalance = $partner->fresh()->current_balance;

                if ($oldBalance != $newBalance) {
                    $count++;
                    echo "  • {$partner->name}: {$oldBalance} → {$newBalance}\n";
                }
            }

            Log::info("Recalculated {$count} partner balances (out of {$partners->count()} total)");
            echo "✓ Recalculated {$count} partner balances\n";
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be reversed as we don't store the old balances
        Log::warning('Recalculate balances migration cannot be reversed');
    }
};
