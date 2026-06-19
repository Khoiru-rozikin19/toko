<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('escrow_status')->default('none')->index()->after('status');
            $table->decimal('escrow_amount', 15, 2)->default(0.00)->after('escrow_status');
            $table->timestamp('escrow_released_at')->nullable()->after('escrow_amount');
            $table->timestamp('paid_at')->nullable()->after('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['escrow_status', 'escrow_amount', 'escrow_released_at', 'paid_at']);
        });
    }
};
