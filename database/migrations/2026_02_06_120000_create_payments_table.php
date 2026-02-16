<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');

            // Midtrans Logic: 'transaction_id' is nullable because it's only received after payment completion via Webhook.
            $table->string('transaction_id')->unique()->nullable();

            $table->string('payment_type', 50); 
            $table->decimal('gross_amount', 10, 2);
            $table->string('transaction_status', 50); 
            $table->string('fraud_status', 50)->nullable(); 
            $table->string('snap_token', 255)->nullable(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
