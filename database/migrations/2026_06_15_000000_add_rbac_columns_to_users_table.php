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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->after('email')->nullable();
            $table->enum('role', ['admin', 'seller', 'buyer'])->default('buyer')->after('password');
            $table->boolean('is_verified')->default(false)->after('role');
            $table->enum('seller_request', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'is_verified', 'seller_request']);
        });
    }
};
