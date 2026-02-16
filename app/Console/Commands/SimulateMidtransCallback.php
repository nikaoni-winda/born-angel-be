<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PaymentController;
use App\Models\Booking;

class SimulateMidtransCallback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'midtrans:simulate {booking_code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a successful Midtrans payment callback locally';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookingCode = $this->argument('booking_code');

        if ($bookingCode) {
            $booking = Booking::where('booking_code', $bookingCode)->first();
        } else {
            // Find the latest pending booking
            $booking = Booking::where('status', 'pending')
                ->whereHas('payment', function ($query) {
                    $query->where('transaction_status', 'pending');
                })
                ->latest()
                ->first();
        }

        if (!$booking) {
            $this->error('No pending booking found to simulate!');
            return;
        }

        $this->info("Simulating payment for Booking Code: {$booking->booking_code}");
        $this->info("Amount: {$booking->total_price}");

        // Prepare Data for Simulation
        // Note: We use the same format as Midtrans sends
        $orderId = $booking->booking_code . '-' . time();
        $statusCode = '200';
        $grossAmount = number_format($booking->total_price, 2, '.', ''); // Midtrans uses strict format usually, e.g. "250000.00"
        $serverKey = config('midtrans.server_key');

        // Generate Signature exactly as Midtrans would
        $stringToHash = $orderId . $statusCode . $grossAmount . $serverKey;
        $signatureKey = hash('sha512', $stringToHash);

        $this->info("Generated Signature: " . $signatureKey);

        // Create a Mock Request
        // We need to inject this request into the controller
        $request = Request::create('/api/payments/callback', 'POST', [
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'status_code' => $statusCode,
            'signature_key' => $signatureKey,
            'fraud_status' => 'accept',
            'transaction_id' => 'sim-txn-' . time()
        ]);

        // Instantiate Controller
        $controller = new PaymentController();

        try {
            $this->info("Sending callback to controller...");
            $response = $controller->callback($request);

            $this->info("Controller Response: " . $response->getContent());
            $this->info("Done! Please check your database or dashboard.");

        } catch (\Exception $e) {
            $this->error("Error simulating callback: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
