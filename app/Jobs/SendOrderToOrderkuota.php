<?php

namespace App\Jobs;

use App\Services\OrderkuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderToOrderkuota implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;

    /**
     * Create a new job instance.
     *
     * @param string $orderId
     */
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     *
     * @param OrderkuotaService $orderkuotaService
     * @return void
     */
    public function handle(OrderkuotaService $orderkuotaService): void
    {
        $orderkuotaService->kirimPesananKeOrderkuota($this->orderId);
    }
}
