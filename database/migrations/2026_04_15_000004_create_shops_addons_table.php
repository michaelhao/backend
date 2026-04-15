<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops');
            $table->foreignId('addon_id')->constrained('addons');
            $table->tinyInteger('source');
            $table->tinyInteger('status')->default(1);
            $table->dateTime('expired_at')->nullable();
            $table->unique(['shop_id', 'addon_id']);
            $table->index(['shop_id', 'source']);
            $table->index('addon_id');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops_addons');
    }
};
