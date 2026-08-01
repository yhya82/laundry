<?php

namespace App\Models;

use App\Models\Concerns\ForbidsDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageReport extends Model
{
    use ForbidsDelete, HasFactory;

    protected $fillable = [
        'laundry_order_id',
        'customer_id',
        'status',
        'description',
        'resolution_type',
        'cash_compensation_amount',
        'store_credit_compensation_amount',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'cash_compensation_amount' => 'decimal:2',
            'store_credit_compensation_amount' => 'decimal:2',
        ];
    }

    public function laundryOrder(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DamageItem::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DamageEvidence::class);
    }
}
