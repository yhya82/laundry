<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'laundry_order_id',
        'discount_template_id',
        'discount_type',
        'value',
        'amount',
        'reason',
        'status',
        'applied_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function laundryOrder(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DiscountTemplate::class, 'discount_template_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
