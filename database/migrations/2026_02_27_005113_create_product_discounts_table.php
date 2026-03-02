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
       Schema::create('product_discounts', function (Blueprint $table) {
       $table->id();
        $table->foreignId('product_id')->unique()->constrained()->onDelete('cascade'); // كل منتج له خصم واحد نشط
    
        $table->enum('type', ['percentage', 'fixed']); // نوع الخصم
       $table->decimal('value', 10, 2); // قيمة الخصم
    
    // تحديد المدة
        $table->dateTime('starts_at')->nullable(); // تاريخ بداية الخصم
        $table->dateTime('ends_at')->nullable();   // تاريخ نهاية الخصم
    
       $table->boolean('is_active')->default(true); // هل الخصم مفعل يدوياً؟
       $table->timestamps();
       });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};
