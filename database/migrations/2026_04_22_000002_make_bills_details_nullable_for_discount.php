<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_details', function (Blueprint $table) {
            $table->dateTime('start_at')->nullable()->change();
            $table->dateTime('expired_at')->nullable()->change();
            $table->integer('total_months')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bills_details', function (Blueprint $table) {
            $table->dateTime('start_at')->nullable(false)->change();
            $table->dateTime('expired_at')->nullable(false)->change();
            $table->integer('total_months')->nullable(false)->change();
        });
    }
};
