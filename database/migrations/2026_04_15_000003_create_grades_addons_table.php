<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades_addons', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('grade_id');
            $table->unsignedInteger('addon_id');
            $table->unique(['grade_id', 'addon_id']);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades_addons');
    }
};
