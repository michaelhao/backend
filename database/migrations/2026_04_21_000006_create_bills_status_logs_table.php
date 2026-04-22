<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bill_id');
            $table->tinyInteger('from_status')->nullable();
            $table->tinyInteger('to_status');
            $table->unsignedInteger('operator_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills_status_logs');
    }
};
