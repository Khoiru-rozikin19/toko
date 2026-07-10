<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use App\Models\Setting;

class ImportOkeconnectProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $markup;
    protected $updatePrices;

    /**
     * Create a new job instance.
     *
     * @param int|null $markup
     * @param bool $updatePrices
     */
    public function __construct($markup = null, $updatePrices = false)
    {
        $this->markup = $markup ?? (int) Setting::get('okeconnect_markup_price', 1000);
        $this->updatePrices = $updatePrices;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $params = [
            '--markup' => $this->markup
        ];

        if ($this->updatePrices) {
            $params['--update-prices'] = true;
        }

        Artisan::call('okeconnect:import-products', $params);
    }
}
