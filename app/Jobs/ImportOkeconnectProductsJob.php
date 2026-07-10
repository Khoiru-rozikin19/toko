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

    /**
     * Create a new job instance.
     *
     * @param int|null $markup
     */
    public function __construct($markup = null)
    {
        $this->markup = $markup ?? (int) Setting::get('okeconnect_markup_price', 1000);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Artisan::call('okeconnect:import-products', [
            '--markup' => $this->markup
        ]);
    }
}
