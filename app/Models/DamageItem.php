<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'damage_report_id',
        'laundry_order_item_id',
        'damage_type_id',
        'severity',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LaundryOrderItem::class, 'laundry_order_item_id');
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }
}
