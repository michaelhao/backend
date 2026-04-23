<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_details', function (Blueprint $table) {
            $table->index(['bill_id', 'is_effective', 'type'], 'bills_details_bill_effective_type_idx');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->index('payment_status');
            $table->index('shop_sales_id');
        });
    }

    public function down(): void
    {
        Schema::table('bills_details', function (Blueprint $table) {
            $table->dropIndex('bills_details_bill_effective_type_idx');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['shop_sales_id']);
        });
    }
};
