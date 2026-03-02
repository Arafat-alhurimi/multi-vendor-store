<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->timestamp('otp_verified_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('otp_verified_at');
        });
    }
};
