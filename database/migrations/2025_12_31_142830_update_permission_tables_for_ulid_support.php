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
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        // Update model_has_permissions table
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
            $table->string($columnNames['model_morph_key'], 26)->change();
        });

        // Update model_has_roles table
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
            $table->string($columnNames['model_morph_key'], 26)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        // Revert model_has_permissions table
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
            $table->unsignedBigInteger($columnNames['model_morph_key'])->change();
        });

        // Revert model_has_roles table
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
            $table->unsignedBigInteger($columnNames['model_morph_key'])->change();
        });
    }
};
