<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SimulateTournament extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournament:simulate {slots=8 : Jumlah slot tim (2, 4, 8, 16, 32)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a fully populated, approved, and ongoing tournament for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $slots = (int) $this->argument('slots');
        if (!in_array($slots, [2, 4, 8, 16, 32])) {
            $this->error("Jumlah slot harus salah satu dari: 2, 4, 8, 16, 32.");
            return 1;
        }

        $this->info("Memulai pembuatan simulasi turnamen Clash Squad dengan {$slots} slot...");

        // 1. Buat Turnamen Dummy
        $tournament = Tournament::create([
            'name' => 'Simulasi Turnamen RZK - ' . Str::upper(Str::random(5)),
            'description' => 'Turnamen simulasi otomatis untuk pengujian fitur.',
            'type' => 'clash_squad',
            'status' => 'registration',
            'registration_fee' => 0,
            'prize_pool' => 'Rp 1.000.000 + Tropi',
            'max_slots' => $slots,
            'start_date' => Carbon::now()->addDay(),
            'start_time' => Carbon::now()->addHours(2), // 2 jam dari sekarang
            'session_interval' => 60,
        ]);

        $this->info("Turnamen dibuat: {$tournament->name} (ID: {$tournament->id})");

        // 2. Buat User & Registrasi Tim
        for ($i = 1; $i <= $slots; $i++) {
            $teamName = "Tim Elite " . $i;

            // Buat Kapten
            $captain = User::create([
                'name' => "Kapten " . $teamName,
                'email' => "kapten_tim" . $i . "_" . strtolower(Str::random(3)) . "@rzk.com",
                'phone' => "6289912345" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'password' => bcrypt('password123'),
                'role' => 'buyer',
                'balance' => 100000, // beri sedikit saldo
            ]);

            // Buat Registrasi
            $registration = TournamentRegistration::create([
                'tournament_id' => $tournament->id,
                'team_name' => $teamName,
                'captain_id' => $captain->id,
                'status' => 'approved', // Langsung approved!
            ]);

            // Buat Roster Nickname & Game ID
            // Player 1 (Kapten)
            TournamentParticipant::create([
                'registration_id' => $registration->id,
                'user_id' => $captain->id,
                'nickname' => 'KPT_' . Str::upper(Str::random(5)),
                'game_id' => '1111222' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'role' => 'captain',
            ]);

            // Player 2, 3, 4 (Anggota)
            for ($j = 2; $j <= 4; $j++) {
                $member = User::create([
                    'name' => "Anggota {$j} Tim {$i}",
                    'email' => "member_" . $i . "_" . $j . "_" . strtolower(Str::random(3)) . "@rzk.com",
                    'phone' => "628995555" . $i . $j,
                    'password' => bcrypt('password123'),
                    'role' => 'buyer',
                    'balance' => 0,
                ]);

                TournamentParticipant::create([
                    'registration_id' => $registration->id,
                    'user_id' => $member->id,
                    'nickname' => 'MBR_' . $i . '_' . $j . '_' . Str::upper(Str::random(3)),
                    'game_id' => '2222333' . $i . $j,
                    'role' => 'member',
                ]);
            }

            $this->info("- Tim {$i} terdaftar & disetujui: {$teamName} (Kapten: {$captain->email})");
        }

        // 3. Ubah Status Turnamen menjadi 'ongoing' untuk memicu pembuatan bagan & sesi tanding otomatis!
        $adminController = app(\App\Http\Controllers\AdminController::class);
        $request = new \Illuminate\Http\Request();
        $request->replace(['status' => 'ongoing']);
        
        $adminController->updateTournamentStatus($request, $tournament->id);

        $this->info("==================================================");
        $this->info("Simulasi Sukses!");
        $this->info("Nama Turnamen: {$tournament->name}");
        $this->info("Turnamen ID  : {$tournament->id} (Status: ONGOING)");
        $this->info("Bagan & Sesi waktu telah dibentuk.");
        $this->info("--------------------------------------------------");
        $this->info("Akun Kapten untuk Login Pengujian (Password: password123):");
        
        $regs = TournamentRegistration::where('tournament_id', $tournament->id)->with('captain')->get();
        foreach ($regs as $index => $r) {
            $this->info("🛡️ Tim " . ($index + 1) . ": {$r->team_name} -> Captain: {$r->captain->email}");
        }
        $this->info("==================================================");

        return 0;
    }
}
