<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OkeconnectCallbackController extends Controller
{
    /**
     * Handle H2H callback notification from OKEConnect (Orderkuota).
     *
     * Expected parameters in incoming request:
     * - ref_id / refid / ref: The unique Order ID we passed (e.g. ORD-XXXXXXXX)
     * - status: SUCCESS / FAILED / PENDING / GAGAL / SUKSES
     * - sn / serial_number / note: Serial Number or Token returned by supplier
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        Log::info("OKEConnect Callback Received: " . json_encode($request->all()));

        $refId = $request->input('ref_id') ?? $request->input('refid') ?? $request->input('ref');
        $status = $request->input('status');
        $sn = $request->input('sn') ?? $request->input('serial_number') ?? $request->input('note');

        if (empty($refId)) {
            Log::warning("OKEConnect Callback Warning: Missing reference ID in request payload.");
            return response()->json([
                'success' => false,
                'message' => 'Missing transaction reference ID (ref_id/refid).'
            ], 400);
        }

        $order = Order::find($refId);
        if (!$order) {
            Log::warning("OKEConnect Callback Warning: Order ID {$refId} not found in database.");
            return response()->json([
                'success' => false,
                'message' => "Order ID {$refId} not found."
            ], 404);
        }

        $statusUpper = strtoupper($status);

        if ($statusUpper === 'SUCCESS' || $statusUpper === 'SUKSES') {
            $order->update([
                'status' => 'success',
                'sn' => $sn,
            ]);

            Log::info("OKEConnect Callback Success: Order {$refId} marked as success with SN: {$sn}");

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully. Order status updated to success.'
            ]);
        }

        if ($statusUpper === 'FAILED' || $statusUpper === 'GAGAL') {
            $order->update([
                'status' => 'failed',
            ]);

            Log::info("OKEConnect Callback Failed: Order {$refId} marked as failed.");

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully. Order status updated to failed.'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Callback received with unhandled status: {$status}."
        ]);
    }
}
