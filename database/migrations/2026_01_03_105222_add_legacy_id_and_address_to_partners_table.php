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
        Schema::table('partners', function (Blueprint $table) {
            $table->integer('legacy_id')->nullable()->after('id')->comment('Legacy system ID for data migration');
            $table->text('address')->nullable()->after('region');

            $table->index('legacy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex(['legacy_id']);
            $table->dropColumn(['legacy_id', 'address']);
        });
    }
};
