<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TournamentMatch;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SendTournamentMatchReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournament:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp reminders to tournament groups 30 minutes before match scheduled time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        // Cek range tanding (antara 25 menit s/d 35 menit mendatang)
        $targetTimeStart = (clone $now)->addMinutes(25);
        $targetTimeEnd = (clone $now)->addMinutes(35);

        // Ambil match babak 1 yang berstatus pending dan dijadwalkan bertanding dalam target waktu tersebut
        $matches = TournamentMatch::where('status', 'pending')
            ->whereBetween('scheduled_time', [$targetTimeStart, $targetTimeEnd])
            ->with(['tournament', 'team1', 'team2'])
            ->get();

        if ($matches->isEmpty()) {
            $this->info("Tidak ada pertandingan yang bertanding dalam 30 menit ke depan.");
            return 0;
        }

        $waService = app(WhatsappService::class);
        if (!$waService->isEnabled()) {
            $this->warn("WhatsApp Bot service is disabled.");
            return 0;
        }

        foreach ($matches as $match) {
            $tournament = $match->tournament;
            if (!$tournament || !$tournament->whatsapp_group_jid) {
                continue;
            }

            // Pastikan pengingat belum pernah dikirim menggunakan cache key
            $cacheKey = 'match_reminder_sent_' . $match->id;
            if (Cache::has($cacheKey)) {
                continue;
            }

            $team1Name = $match->team1 ? $match->team1->team_name : 'TBD';
            $team2Name = $match->team2 ? $match->team2->team_name : 'TBD';
            $timeString = $match->scheduled_time->translatedFormat('H:i');

            $message = "🔔 *PENGINGAT: MATCH SESI BERIKUTNYA (Mulai pukul {$timeString} WIB)*\n\n"
                     . "Halo Kapten! Pertandingan Anda akan dimulai dalam 30 menit. Berikut daftar tim yang akan bertanding:\n\n"
                     . "⚔️ *Match {$match->match_number}*: {$team1Name} vs {$team2Name}\n\n"
                     . "*Silakan kapten masing-masing tim segera berkoordinasi untuk membuat Custom Room Free Fire!*";

            $waService->sendGenericMessage($tournament->whatsapp_group_jid, $message);
            Cache::put($cacheKey, true, 3600); // cache selama 1 jam

            $this->info("Reminder sent for Match {$match->id} to group {$tournament->whatsapp_group_jid}");
        }

        return 0;
    }
}
