<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_ad_subscription_id')->nullable()->constrained('vendor_ad_subscriptions')->nullOnDelete();
            $table->enum('media_type', ['image', 'video']);
            $table->string('media_path');
            $table->enum('click_action', ['store', 'product', 'promotion', 'url']);
            $table->string('action_id')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index(['vendor_id']);
            $table->index(['vendor_ad_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
