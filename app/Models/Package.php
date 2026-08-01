<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'branch_id',
        'description',
        'price',
        'maximum_clothes',
        'package_type',
        'priority',
        'status',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'package_services');
    }

    public function subscriptionPackages(): HasMany
    {
        return $this->hasMany(SubscriptionPackage::class);
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(LaundryOrderPackage::class);
    }
}
