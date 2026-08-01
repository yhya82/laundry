<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'branch_id',
        'discount_type',
        'value',
        'requires_approval',
        'max_cashier_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_cashier_value' => 'decimal:2',
            'requires_approval' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderDiscounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }
}
