<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops_admin', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->text('email')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shops_admin', function (Blueprint $table) {
            $table->string('email')->change();
            $table->unique('email');
        });
    }
};
