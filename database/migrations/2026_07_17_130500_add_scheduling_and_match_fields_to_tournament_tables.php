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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('whatsapp_group_jid')->nullable()->after('type');
            $table->dateTime('start_time')->nullable()->after('whatsapp_group_jid');
            $table->integer('session_interval')->default(60)->after('start_time');
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dateTime('scheduled_time')->nullable()->after('status');
            $table->string('room_id')->nullable()->after('scheduled_time');
            $table->string('room_password')->nullable()->after('room_id');
            $table->unsignedBigInteger('reported_winner_id')->nullable()->after('room_password');
            $table->string('screenshot_1')->nullable()->after('reported_winner_id');
            $table->string('screenshot_2')->nullable()->after('screenshot_1');
            $table->string('screenshot_3')->nullable()->after('screenshot_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_group_jid', 'start_time', 'session_interval']);
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_time',
                'room_id',
                'room_password',
                'reported_winner_id',
                'screenshot_1',
                'screenshot_2',
                'screenshot_3'
            ]);
        });
    }
};
