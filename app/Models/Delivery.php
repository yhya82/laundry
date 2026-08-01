<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'laundry_order_id',
        'customer_id',
        'delivery_type',
        'address_id',
        'address_snapshot',
        'delivery_fee',
        'assigned_staff_id',
        'status',
        'scheduled_date',
        'completed_date',
        'failure_reason',
        'delivery_instructions',
    ];

    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'scheduled_date' => 'date',
            'completed_date' => 'date',
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

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_staff_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class);
    }
}
