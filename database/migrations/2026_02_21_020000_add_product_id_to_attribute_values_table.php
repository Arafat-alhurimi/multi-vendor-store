<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('attribute_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['product_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'attribute_id']);
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
