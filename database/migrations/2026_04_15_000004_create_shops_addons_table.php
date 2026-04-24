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
            $table->unsignedInteger('shop_id');
            $table->unsignedInteger('addon_id');
            $table->tinyInteger('source');
            $table->tinyInteger('status')->default(1);
            $table->dateTime('expired_at')->nullable();
            $table->unique(['shop_id', 'addon_id']);
            $table->index(['shop_id', 'source']);
            $table->index('addon_id');
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops_addons');
    }
};
