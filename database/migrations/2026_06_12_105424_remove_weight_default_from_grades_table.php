<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 移除 weight 的 default(0)：與 unique index 矛盾（兩筆走 default 即撞 constraint）。
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->integer('weight')->change();
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->integer('weight')->default(0)->change();
        });
    }
};
