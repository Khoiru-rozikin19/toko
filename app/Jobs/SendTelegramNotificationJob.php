<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;
    protected $amount;
    protected $customerName;

    /**
     * Create a new job instance.
     *
     * @param string $orderId
     * @param int $amount
     * @param string $customerName
     */
    public function __construct($orderId, $amount, $customerName)
    {
        $this->orderId = $orderId;
        $this->amount = $amount;
        $this->customerName = $customerName;
    }

    /**
     * Execute the job.
     *
     * @param TelegramService $telegramService
     * @return void
     */
    public function handle(TelegramService $telegramService): void
    {
        $telegramService->sendOrderNotification($this->orderId, $this->amount, $this->customerName);
    }
}
