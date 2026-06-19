<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\UserBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseEscrowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'escrow:release';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release escrow balance to sellers for orders older than 90 minutes with no complaints.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoffTime = Carbon::now()->subMinutes(90);

        // Find held orders that are older than 90 minutes
        $orders = Order::where('escrow_status', 'held')
            ->where('paid_at', '<=', $cutoffTime)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No escrow balances to release.');
            return 0;
        }

        foreach ($orders as $order) {
            try {
                DB::transaction(function () use ($order) {
                    // Refetch order with lock
                    $order = Order::where('id', $order->id)->lockForUpdate()->first();
                    if (!$order || $order->escrow_status !== 'held') {
                        return;
                    }

                    // Verify if a complaint exists
                    $hasComplaints = $order->complaints()->whereIn('status', ['pending', 'resolved'])->exists();
                    if ($hasComplaints) {
                        $order->update(['escrow_status' => 'disputed']);
                        $this->warn("Order {$order->id} has complaints. Moved to disputed.");
                        return;
                    }

                    $sellerId = $order->product ? $order->product->user_id : null;
                    if ($sellerId) {
                        $sellerBalance = UserBalance::where('user_id', $sellerId)->lockForUpdate()->first();
                        if ($sellerBalance) {
                            $newHeld = max(0, $sellerBalance->held_balance - $order->escrow_amount);
                            $sellerBalance->update(['held_balance' => $newHeld]);
                        }
                    }

                    // Update escrow details
                    $order->escrow_status = 'released';
                    $order->escrow_released_at = Carbon::now();
                    
                    // If still marked as success/paid, change status to proses
                    if ($order->status === 'success' || $order->status === 'paid') {
                        $order->status = 'proses';
                    }

                    $order->save();

                    Log::info("Escrow released for order: {$order->id}. Amount: {$order->escrow_amount}");
                    $this->info("Released escrow for Order {$order->id}");
                });
            } catch (\Exception $e) {
                Log::error("Failed to release escrow for order {$order->id}: " . $e->getMessage());
                $this->error("Error releasing Order {$order->id}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
