<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if using SQLite (for testing) or MySQL (for production)
        $driver = Schema::connection($this->getConnection())->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so we need to recreate the table
            // For testing purposes, we'll just ensure the column accepts the new values
            // Note: SQLite stores ENUM as TEXT, so it already accepts 'shareholder'
            Schema::table('partners', function (Blueprint $table) {
                // No actual change needed for SQLite - it's already flexible
            });
        } else {
            // MySQL/MariaDB - use raw SQL to modify ENUM
            DB::statement("ALTER TABLE partners MODIFY COLUMN type ENUM('customer', 'supplier', 'shareholder') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::connection($this->getConnection())->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // No change needed for SQLite
            Schema::table('partners', function (Blueprint $table) {
                // No actual change needed
            });
        } else {
            DB::statement("ALTER TABLE partners MODIFY COLUMN type ENUM('customer', 'supplier') NOT NULL");
        }
    }
};
