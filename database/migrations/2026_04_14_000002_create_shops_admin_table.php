<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops_admin', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('shop_id');
            $table->string('name', 20);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('business_number')->nullable();
            $table->string('company_name')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops_admin');
    }
};
