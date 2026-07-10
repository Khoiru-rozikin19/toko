<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncOkeconnectProductStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'okeconnect:sync-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize product open/close status from Okeconnect price list page.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $priceListId = Setting::get('okeconnect_price_list_id') ?: '905ccd028329b0a';
        $url = "https://okeconnect.com/harga/list?id=" . $priceListId;

        $this->info("Fetching price list from: " . $url);
        Log::info("OkeconnectSync: Fetching price list from: " . $url);

        try {
            $response = Http::timeout(15)->withoutVerifying()->get($url);
            if ($response->failed()) {
                $this->error("HTTP request failed with status: " . $response->status());
                Log::error("OkeconnectSync Error: HTTP request failed with status: " . $response->status());
                return 1;
            }

            $html = $response->body();
            if (empty($html)) {
                $this->error("Received empty response body.");
                return 1;
            }

            // Clean HTML comments to avoid matching commented-out elements
            $html = preg_replace('/<!--.*?-->/s', '', $html);

            // Parse HTML rows using regex
            preg_match_all('/<tr>(.*?)<\/tr>/s', $html, $matches);
            
            $statuses = [];
            foreach ($matches[1] as $rowHtml) {
                // Find code in the first <td> and status in the last <td>
                if (preg_match_all('/<td.*?>(.*?)<\/td>/s', $rowHtml, $tdMatches)) {
                    if (count($tdMatches[1]) >= 4) {
                        $code = trim(strip_tags($tdMatches[1][0]));
                        $statusHtml = end($tdMatches[1]);
                        $status = strtolower(trim(strip_tags($statusHtml)));
                        if (!empty($code)) {
                            $statuses[$code] = $status;
                        }
                    }
                }
            }

            if (empty($statuses)) {
                $this->error("Could not parse any product codes and statuses from the HTML. Check if the page layout has changed.");
                Log::warning("OkeconnectSync Warning: Parse result is empty.");
                return 1;
            }

            $this->info("Parsed " . count($statuses) . " products from Okeconnect.");

            // Get all products that have an orderkuota_product_code set
            $products = Product::whereNotNull('orderkuota_product_code')
                               ->where('orderkuota_product_code', '!=', '')
                               ->get();

            $updatedCount = 0;
            foreach ($products as $product) {
                $code = $product->orderkuota_product_code;
                if (isset($statuses[$code])) {
                    $supplierStatus = $statuses[$code]; // 'open' or 'close'
                    $newStock = ($supplierStatus === 'open') ? 9999 : 0;
                    
                    if ($product->stock != $newStock) {
                        $product->stock = $newStock;
                        $product->save();
                        $this->line("Product [{$product->name}] (Code: {$code}) updated: " . strtoupper($supplierStatus));
                        Log::info("OkeconnectSync: Product [{$product->name}] (Code: {$code}) status changed to " . strtoupper($supplierStatus));
                        $updatedCount++;
                    }
                } else {
                    $this->warn("Product code [{$code}] not found in Okeconnect price list.");
                }
            }

            $this->info("Synchronization finished. Updated {$updatedCount} products.");
            Log::info("OkeconnectSync: Synchronization finished. Updated {$updatedCount} products.");

            return 0;
        } catch (\Exception $e) {
            $this->error("An exception occurred: " . $e->getMessage());
            Log::error("OkeconnectSync Exception: " . $e->getMessage());
            return 1;
        }
    }
}
