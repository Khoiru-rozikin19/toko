<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportOkeconnectProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'okeconnect:import-products {--markup=1000} {--limit=} {--update-prices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import and sync all products from Okeconnect JSON endpoint.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $priceListId = Setting::get('okeconnect_price_list_id') ?: '905ccd028329b0a';
        $url = "https://okeconnect.com/harga/json?id=" . $priceListId;
        $markup = (int) $this->option('markup');
        $limit = $this->option('limit');

        $this->info("Fetching products from JSON: " . $url);
        Log::info("OkeconnectImport: Fetching products from JSON: " . $url);

        try {
            $response = Http::timeout(60)->withoutVerifying()->get($url);
            if ($response->failed()) {
                $this->error("HTTP request failed with status: " . $response->status());
                return 1;
            }

            $items = $response->json();
            if (empty($items) || !is_array($items)) {
                $this->error("Received empty or invalid JSON array.");
                return 1;
            }

            $totalItems = count($items);
            $this->info("Total products found in JSON: " . $totalItems);

            // Fetch admin user ID
            $admin = User::where('role', 'admin')->first();
            $adminId = $admin ? $admin->id : 1;

            $imported = 0;
            $updated = 0;
            $processed = 0;

            foreach ($items as $item) {
                if ($limit && $processed >= (int)$limit) {
                    break;
                }

                $code = trim($item['kode'] ?? '');
                $categoryName = trim($item['kategori'] ?? 'LAIN-LAIN');
                $name = trim($item['keterangan'] ?? '');
                $desc = trim($item['produk'] ?? '');
                $supplierPrice = (int) ($item['harga'] ?? 0);
                $status = trim($item['status'] ?? '0'); // "1" for open

                if (empty($code) || empty($name)) {
                    continue;
                }

                // 1. Get or create Category
                $category = Category::firstOrCreate(
                    ['name' => $categoryName],
                    ['sort_order' => 50]
                );

                // 2. Calculate selling price
                $sellingPrice = $supplierPrice + $markup;
                $stock = ($status === '1') ? 9999 : 0;

                // 3. Find existing product
                $product = Product::where('orderkuota_product_code', $code)->first();

                if ($product) {
                    // Update existing product
                    $product->harga_modal = $supplierPrice;
                    if ($this->option('update-prices')) {
                        $product->price = $sellingPrice;
                    }
                    $product->stock = $stock;
                    $product->category_id = $category->id;
                    $product->save();
                    $updated++;
                } else {
                    // Create new product
                    Product::create([
                        'user_id' => $adminId,
                        'category_id' => $category->id,
                        'name' => $name,
                        'description' => $desc,
                        'price' => $sellingPrice,
                        'harga_modal' => $supplierPrice,
                        'stock' => $stock,
                        'orderkuota_product_code' => $code,
                        'visibility' => 'all',
                    ]);
                    $imported++;
                }

                $processed++;
                if ($processed % 100 === 0) {
                    $this->line("Processed {$processed} / {$totalItems}...");
                }
            }

            $this->info("Import finished. Processed: {$processed}, Imported New: {$imported}, Updated: {$updated}");
            Log::info("OkeconnectImport Success: Processed: {$processed}, Imported: {$imported}, Updated: {$updated}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("OkeconnectImport Exception: " . $e->getMessage());
            return 1;
        }
    }
}
