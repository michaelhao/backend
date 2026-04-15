<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type');
            $table->string('name', 50);
            $table->unsignedInteger('price');
            $table->string('unit', 10)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('syncing')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
