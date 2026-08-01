<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundryOrderPackage extends Model
{
    use HasFactory;

    protected $table = 'laundry_order_packages';

    protected $fillable = [
        'laundry_order_id',
        'package_id',
        'quantity',
        'unit_price_snapshot',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function laundryOrder(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LaundryOrderItem::class, 'laundry_order_package_id');
    }
}
