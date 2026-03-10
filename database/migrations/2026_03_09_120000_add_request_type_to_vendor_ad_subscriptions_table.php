<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_ad_subscriptions', function (Blueprint $table): void {
            $table->enum('request_type', ['new', 'renewal'])
                ->default('new')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ad_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('request_type');
        });
    }
};
