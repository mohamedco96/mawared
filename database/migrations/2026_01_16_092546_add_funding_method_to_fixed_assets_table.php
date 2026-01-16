<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Funding method: cash, payable, equity
            $table->enum('funding_method', ['cash', 'payable', 'equity'])->default('payable')->after('purchase_date');

            // Supplier information for payable method
            $table->string('supplier_name')->nullable()->after('funding_method');
            $table->ulid('supplier_id')->nullable()->after('supplier_name');

            // Partner information for equity method
            $table->ulid('partner_id')->nullable()->after('supplier_id');

            // Status to track if the asset is active or draft
            $table->enum('status', ['draft', 'active'])->default('draft')->after('partner_id');

            // Foreign keys
            $table->foreign('supplier_id')->references('id')->on('partners')->onDelete('restrict');
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('restrict');

            // Index for performance
            $table->index('funding_method');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['partner_id']);

            // Drop indexes
            $table->dropIndex(['funding_method']);
            $table->dropIndex(['status']);

            // Drop columns
            $table->dropColumn([
                'funding_method',
                'supplier_name',
                'supplier_id',
                'partner_id',
                'status',
            ]);
        });
    }
};
