<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('no', 32)->charset('ascii')->unique();
            $table->unsignedBigInteger('creator_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('shop_sales_id');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('total_grade')->default(0);
            $table->unsignedInteger('total_addons')->default(0);
            $table->unsignedInteger('discount_amount')->nullable();
            $table->tinyInteger('payment_status');
            $table->tinyInteger('payment_method')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('invoice_no', 100)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('creator_id')->references('id')->on('users');
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->foreign('shop_sales_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
