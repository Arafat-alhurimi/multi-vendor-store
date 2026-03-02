<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_ad_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ad_package_id')->constrained('ad_packages')->restrictOnDelete();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->enum('status', ['pending', 'active', 'expired'])->default('pending');
            $table->unsignedInteger('used_images')->default(0);
            $table->unsignedInteger('used_videos')->default(0);
            $table->unsignedInteger('used_promotions')->default(0);
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ad_subscriptions');
    }
};
