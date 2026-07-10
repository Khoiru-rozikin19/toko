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

            // Load categories and products into memory to avoid N+1 queries
            $existingCategories = Category::all()->keyBy('name');
            $existingProducts = Product::whereNotNull('orderkuota_product_code')
                                       ->where('orderkuota_product_code', '!=', '')
                                       ->get()
                                       ->keyBy('orderkuota_product_code');

            $updatePrices = $this->option('update-prices');

            // Wrap all operations in a single Database Transaction to prevent SQLite disk locking issues
            \Illuminate\Support\Facades\DB::transaction(function () use ($items, $limit, $existingCategories, $existingProducts, $adminId, $markup, $updatePrices, &$processed, &$imported, &$updated) {
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

                    // Get category from memory, create if not present
                    if (!$existingCategories->has($categoryName)) {
                        $category = Category::create([
                            'name' => $categoryName,
                            'sort_order' => 50
                        ]);
                        $existingCategories->put($categoryName, $category);
                    } else {
                        $category = $existingCategories->get($categoryName);
                    }

                    $sellingPrice = $supplierPrice + $markup;
                    $stock = ($status === '1') ? 9999 : 0;

                    // Get product from memory
                    if ($existingProducts->has($code)) {
                        $product = $existingProducts->get($code);
                        
                        $changed = false;
                        if ($product->harga_modal != $supplierPrice) {
                            $product->harga_modal = $supplierPrice;
                            $changed = true;
                        }
                        if ($updatePrices && $product->price != $sellingPrice) {
                            $product->price = $sellingPrice;
                            $changed = true;
                        }
                        if ($product->stock != $stock) {
                            $product->stock = $stock;
                            $changed = true;
                        }
                        if ($product->category_id != $category->id) {
                            $product->category_id = $category->id;
                            $changed = true;
                        }

                        if ($changed) {
                            $product->save();
                            $updated++;
                        }
                    } else {
                        // Create new product
                        $newProduct = Product::create([
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
                        $existingProducts->put($code, $newProduct);
                        $imported++;
                    }

                    $processed++;
                }
            });

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
