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
        // Insert quotation_prefix setting if it doesn't exist
        \Illuminate\Support\Facades\DB::table('settings')->insert([
            'group' => 'company',
            'name' => 'quotation_prefix',
            'locked' => false,
            'payload' => json_encode('QT'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove quotation_prefix setting
        \Illuminate\Support\Facades\DB::table('settings')
            ->where('group', 'company')
            ->where('name', 'quotation_prefix')
            ->delete();
    }
};
