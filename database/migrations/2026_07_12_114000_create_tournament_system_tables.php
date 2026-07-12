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
        // 1. Tournaments Table
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // clash_squad, battle_royale
            $table->string('status')->default('draft'); // draft, registration, ongoing, completed
            $table->decimal('registration_fee', 15, 2)->default(0.00);
            $table->string('prize_pool');
            $table->integer('max_slots')->nullable(); // e.g. 8, 16, 32
            $table->dateTime('start_date')->nullable();
            $table->timestamps();
        });

        // 2. Tournament Registrations (Teams)
        Schema::create('tournament_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->string('team_name');
            $table->foreignId('captain_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->string('telegram_message_id')->nullable();
            $table->timestamps();
        });

        // 3. Tournament Participants
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('tournament_registrations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('nickname');
            $table->string('game_id');
            $table->string('role')->default('member'); // captain, member
            $table->timestamps();
        });

        // 4. Tournament Matches (For CS Brackets / Schedules)
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->integer('round_number'); // 1, 2, 3, etc.
            $table->integer('match_number'); // match position in the round bracket
            $table->foreignId('team1_id')->nullable()->constrained('tournament_registrations')->onDelete('set null');
            $table->foreignId('team2_id')->nullable()->constrained('tournament_registrations')->onDelete('set null');
            $table->integer('team1_score')->nullable();
            $table->integer('team2_score')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('tournament_registrations')->onDelete('set null');
            $table->string('status')->default('pending'); // pending, ongoing, completed
            $table->timestamps();
        });

        // 5. Tournament Points History (For Global Leaderboard)
        Schema::create('tournament_points_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->onDelete('cascade');
            $table->integer('points');
            $table->string('reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_points_history');
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournament_registrations');
        Schema::dropIfExists('tournaments');
    }
};
