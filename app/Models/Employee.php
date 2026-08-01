<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'branch_id',
        'name',
        'phone',
        'address',
        'position',
        'department_id',
        'status',
        'joined_date',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'joined_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(LaundryOrder::class, 'assigned_employee_id');
    }

    public function assignedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'assigned_staff_id');
    }
}
