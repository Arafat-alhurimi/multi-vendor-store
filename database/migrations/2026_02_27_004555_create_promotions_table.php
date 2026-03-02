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
       Schema::create('promotions', function (Blueprint $table) {
        $table->id();
        $table->string('title'); // عنوان العرض (مثلاً: تخفيضات الشتاء)
        $table->enum('level', ['app', 'store']); // مستوى التطبيق أو مستوى المتجر
        $table->foreignId('store_id')->nullable()->constrained(); // يكون null إذا كان العرض مستوى التطبيق
    
    // تفاصيل الخصم
       $table->enum('discount_type', ['percentage', 'fixed']); // نسبة مئوية أو مبلغ ثابت
       $table->decimal('discount_value', 10, 2); 
    
    // التوقيت
    $table->dateTime('starts_at');
    $table->dateTime('ends_at');
    
       $table->boolean('is_active')->default(false); // للتفعيل بعد موافقة الأدمن أو حسب الجدولة
       $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
