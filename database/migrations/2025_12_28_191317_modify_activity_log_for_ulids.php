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
        Schema::table('activity_log', function (Blueprint $table) {
            // Drop the existing indexes first
            $table->dropIndex('subject');
            $table->dropIndex('causer');

            // Drop the columns
            $table->dropColumn(['subject_type', 'subject_id', 'causer_type', 'causer_id']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            // Re-add them as ULID morph columns
            $table->nullableUlidMorphs('subject');
            $table->nullableUlidMorphs('causer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Drop ULID morph indexes and columns
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['causer_type', 'causer_id']);
            $table->dropColumn(['subject_type', 'subject_id', 'causer_type', 'causer_id']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            // Restore original bigInteger morph columns
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
        });
    }
};
