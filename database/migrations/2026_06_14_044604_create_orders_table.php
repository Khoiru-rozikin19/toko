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
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary(); // we will use UUID or clean order code
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('email_or_whatsapp');
            $table->integer('base_amount');
            $table->integer('unique_code');
            $table->integer('total_amount')->index();
            $table->string('status')->default('pending')->index(); // pending, success, expired
            $table->text('qris_payload')->nullable();
            $table->text('vpn_config')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
