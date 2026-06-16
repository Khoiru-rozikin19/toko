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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vps_server_id')->nullable()->after('category_id')->constrained('vps_servers')->onDelete('set null');
            $table->string('vps_command_template')->nullable()->after('vps_server_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['vps_server_id']);
            $table->dropColumn(['vps_server_id', 'vps_command_template']);
        });
    }
};
