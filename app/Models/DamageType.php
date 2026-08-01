<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'branch_id',
        'status',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function damageItems(): HasMany
    {
        return $this->hasMany(DamageItem::class);
    }
}
