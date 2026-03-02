<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reportable');
            $table->text('reason');
            $table->timestamps();

            $table->unique(['user_id', 'reportable_id', 'reportable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
