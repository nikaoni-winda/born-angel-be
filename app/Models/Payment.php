<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CAPTURE = 'capture';
    public const STATUS_SETTLEMENT = 'settlement';
    public const STATUS_DENY = 'deny';
    public const STATUS_EXPIRE = 'expire';
    public const STATUS_CANCEL = 'cancel';

    protected $fillable = [
        'booking_id',
        'transaction_id',
        'payment_type',
        'gross_amount',
        'transaction_status',
        'fraud_status',
        'snap_token',
    ];

    /**
     * Get the booking associated with the payment.
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
