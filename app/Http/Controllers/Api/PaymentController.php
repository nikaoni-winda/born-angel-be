<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Payment;
use Midtrans\Config;
use Midtrans\Snap;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    /**
     * Get Snap Token for a booking
     */
    public function getSnapToken($booking_id)
    {
        $booking = Booking::with(['schedule.service', 'user', 'payment'])->findOrFail($booking_id);

        // Authorization: Check if user owns the booking
        if (auth()->user()->id !== $booking->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // If already paid, don't allow re-payment
        if ($booking->status === 'confirmed') {
            return response()->json(['message' => 'Booking already paid.'], 400);
        }

        // Check if payment record exists
        if (!$booking->payment) {
            return response()->json(['message' => 'Payment record not found. Please contact support.'], 500);
        }

        \Illuminate\Support\Facades\Log::info('Payment Request Start', ['booking_id' => $booking_id, 'server_key_prefix' => substr(\Midtrans\Config::$serverKey, 0, 5)]);

        $params = [
            'transaction_details' => [
                'order_id' => $booking->booking_code . '-' . time(),
                'gross_amount' => (int) $booking->total_price,
            ],
            'customer_details' => [
                'first_name' => $booking->user->name,
                'email' => $booking->user->email,
                'phone' => $booking->user->phone_number,
            ],
            'item_details' => [
                [
                    'id' => $booking->schedule->service->id,
                    'price' => (int) $booking->total_price,
                    'quantity' => 1,
                    'name' => 'Makeup Class: ' . $booking->schedule->service->name,
                ]
            ],
            'enabled_payments' => config('midtrans.enabled_payments'),
        ];

        try {
            \Illuminate\Support\Facades\Log::info('Calling Snap::getSnapToken...', ['order_id' => $params['transaction_details']['order_id']]);

            $startTime = microtime(true);
            $snapToken = Snap::getSnapToken($params);
            $duration = microtime(true) - $startTime;

            \Illuminate\Support\Facades\Log::info('Snap Token Received', ['token' => $snapToken, 'duration' => $duration . 's']);

            // Update Snap Token in Payment record
            $booking->payment->update([
                'snap_token' => $snapToken
            ]);

            return response()->json([
                'snap_token' => $snapToken,
                'client_key' => config('midtrans.client_key')
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Midtrans Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Midtrans Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Midtrans Notification Callback
     */
    public function callback(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Midtrans Callback Hit', [
            'payload' => $request->all(),
        ]);

        // Verify signature using config() (NOT env() — env() returns null when config is cached)
        $serverKey = config('midtrans.server_key');
        $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if ($hashed !== $request->signature_key) {
            \Illuminate\Support\Facades\Log::warning('Midtrans Callback: Invalid signature', [
                'order_id' => $request->order_id,
            ]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transactionStatus = $request->transaction_status;
        $type = $request->payment_type;
        $orderId = $request->order_id;
        $fraud = $request->fraud_status;

        \Illuminate\Support\Facades\Log::info('Midtrans Callback Verified', [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'payment_type' => $type,
            'fraud_status' => $fraud,
        ]);

        // order_id format: "{BOOKING_CODE}-{TIMESTAMP}" e.g. "BA-A1B2C3D4-1708012345"
        $orderIdParts = explode('-', $orderId);
        $bookingCode = $orderIdParts[0] . '-' . $orderIdParts[1];

        $booking = Booking::where('booking_code', $bookingCode)->first();

        if (!$booking) {
            \Illuminate\Support\Facades\Log::warning('Midtrans Callback: Booking not found', [
                'booking_code' => $bookingCode,
                'order_id' => $orderId,
            ]);
            return response()->json(['message' => 'Booking not found'], 404);
        }

        DB::transaction(function () use ($booking, $transactionStatus, $type, $fraud, $request) {
            $payment = $booking->payment;

            if ($transactionStatus == 'capture') {
                if ($fraud == 'challenge') {
                    $payment->update(['transaction_status' => 'challenge']);
                } else if ($fraud == 'accept') {
                    $payment->update(['transaction_status' => 'settlement']);
                    $booking->update(['status' => 'confirmed']);
                }
            } else if ($transactionStatus == 'settlement') {
                $payment->update(['transaction_status' => 'settlement']);
                $booking->update(['status' => 'confirmed']);
            } else if ($transactionStatus == 'pending') {
                $payment->update(['transaction_status' => 'pending']);
            } else if (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
                $payment->update(['transaction_status' => $transactionStatus]);
                if ($transactionStatus == 'cancel' || $transactionStatus == 'expire') {
                    $booking->update(['status' => 'cancelled']);
                }
            }

            $payment->update([
                'payment_type' => $type,
                'transaction_id' => $request->transaction_id,
                'fraud_status' => $fraud,
            ]);
        });

        \Illuminate\Support\Facades\Log::info('Midtrans Callback Processed', [
            'booking_code' => $bookingCode,
            'new_status' => $transactionStatus,
        ]);

        return response()->json(['message' => 'Callback processed']);
    }
}
