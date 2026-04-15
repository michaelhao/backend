<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained('addons');
            $table->unique('addon_id');
            $table->string('image_url', 255);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons_image');
    }
};
