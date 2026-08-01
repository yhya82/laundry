<?php

namespace App\Models;

use App\Models\Concerns\ForbidsDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use ForbidsDelete, HasFactory;

    protected $fillable = [
        'receipt_number',
        'laundry_order_id',
        'payment_id',
        'customer_id',
        'status',
        'subtotal_snapshot',
        'discount_snapshot',
        'delivery_fee_snapshot',
        'store_credit_used_snapshot',
        'total_snapshot',
        'business_name_snapshot',
        'business_logo_snapshot',
        'generated_by',
        'generated_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_snapshot' => 'decimal:2',
            'discount_snapshot' => 'decimal:2',
            'delivery_fee_snapshot' => 'decimal:2',
            'store_credit_used_snapshot' => 'decimal:2',
            'total_snapshot' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function laundryOrder(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
