<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_details', function (Blueprint $table) {
            $table->unsignedInteger('grade_id')->nullable()->after('bill_id');
            $table->unsignedInteger('addon_id')->nullable()->after('grade_id');
        });
    }

    public function down(): void
    {
        Schema::table('bills_details', function (Blueprint $table) {
            $table->dropColumn(['grade_id', 'addon_id']);
        });
    }
};
