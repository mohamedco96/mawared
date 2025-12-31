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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUlid('category_id')->nullable()->after('id')->constrained('product_categories')->nullOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->string('image')->nullable()->after('description');
            $table->json('images')->nullable()->after('image');
            $table->boolean('is_active')->default(true)->after('avg_cost');
            $table->boolean('is_public')->default(true)->after('is_active');

            // Indexes
            $table->index('category_id');
            $table->index(['category_id', 'is_active', 'is_public']);
            $table->index('is_active');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['category_id', 'is_active', 'is_public']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_public']);

            $table->dropColumn([
                'category_id',
                'description',
                'image',
                'images',
                'is_active',
                'is_public',
            ]);
        });
    }
};
