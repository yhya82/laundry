<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClothingType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'branch_id',
        'image_path',
        'description',
        'status',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(LaundryOrderItem::class);
    }
}
