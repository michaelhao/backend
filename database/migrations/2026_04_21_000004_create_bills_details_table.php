<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->tinyInteger('type');
            $table->tinyInteger('payment_type')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_price');
            $table->integer('total_price');
            $table->string('name', 100);
            $table->dateTime('start_at');
            $table->dateTime('expired_at');
            $table->integer('total_months');
            $table->tinyInteger('is_effective')->default(1);
            $table->dateTime('canceled_at')->nullable();
            $table->unsignedBigInteger('canceled_by')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->string('memo', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('bill_id')->references('id')->on('bills');
            $table->foreign('canceled_by')->references('id')->on('users');

            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills_details');
    }
};
