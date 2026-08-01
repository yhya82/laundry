<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundryOrderItem extends Model
{
    use HasFactory;

    protected $table = 'laundry_order_items';

    protected $fillable = [
        'laundry_order_package_id',
        'clothing_type_id',
        'quantity',
    ];

    public function orderPackage(): BelongsTo
    {
        return $this->belongsTo(LaundryOrderPackage::class, 'laundry_order_package_id');
    }

    public function clothingType(): BelongsTo
    {
        return $this->belongsTo(ClothingType::class);
    }

    public function damageItems(): HasMany
    {
        return $this->hasMany(DamageItem::class, 'laundry_order_item_id');
    }
}
