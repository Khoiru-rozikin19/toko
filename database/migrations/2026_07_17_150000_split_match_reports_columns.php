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
        Schema::table('tournament_matches', function (Blueprint $table) {
            // Hapus kolom laporan singular lama
            $table->dropColumn(['reported_winner_id', 'screenshot_1', 'screenshot_2', 'screenshot_3']);

            // Kolom Laporan Tim 1 (Home)
            $table->unsignedBigInteger('t1_reported_winner_id')->nullable()->after('room_password');
            $table->integer('t1_team1_score')->nullable()->after('t1_reported_winner_id');
            $table->integer('t1_team2_score')->nullable()->after('t1_team1_score');
            $table->string('t1_screenshot_1')->nullable()->after('t1_team2_score');
            $table->string('t1_screenshot_2')->nullable()->after('t1_screenshot_1');
            $table->string('t1_screenshot_3')->nullable()->after('t1_screenshot_2');

            // Kolom Laporan Tim 2 (Away)
            $table->unsignedBigInteger('t2_reported_winner_id')->nullable()->after('t1_screenshot_3');
            $table->integer('t2_team1_score')->nullable()->after('t2_reported_winner_id');
            $table->integer('t2_team2_score')->nullable()->after('t2_team1_score');
            $table->string('t2_screenshot_1')->nullable()->after('t2_team2_score');
            $table->string('t2_screenshot_2')->nullable()->after('t2_screenshot_1');
            $table->string('t2_screenshot_3')->nullable()->after('t2_screenshot_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->unsignedBigInteger('reported_winner_id')->nullable()->after('room_password');
            $table->string('screenshot_1')->nullable()->after('reported_winner_id');
            $table->string('screenshot_2')->nullable()->after('screenshot_1');
            $table->string('screenshot_3')->nullable()->after('screenshot_2');

            $table->dropColumn([
                't1_reported_winner_id', 't1_team1_score', 't1_team2_score', 't1_screenshot_1', 't1_screenshot_2', 't1_screenshot_3',
                't2_reported_winner_id', 't2_team1_score', 't2_team2_score', 't2_screenshot_1', 't2_screenshot_2', 't2_screenshot_3'
            ]);
        });
    }
};
