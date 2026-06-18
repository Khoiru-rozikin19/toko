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
        Schema::create('xl_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->unique();
            $table->string('label')->nullable();
            $table->string('subscriber_id')->nullable();
            $table->string('subscription_type')->nullable();
            $table->text('access_token')->nullable();
            $table->text('id_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xl_sessions');
    }
};
