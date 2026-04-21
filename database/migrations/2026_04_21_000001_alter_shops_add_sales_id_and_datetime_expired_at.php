<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_id')->nullable()->after('grade_id');
            $table->foreign('sales_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('expired_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['sales_id']);
            $table->dropColumn('sales_id');
            $table->date('expired_at')->nullable()->change();
        });
    }
};
