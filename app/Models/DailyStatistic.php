<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'stat_date',
        'metric_key',
        'metric_value',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'metric_value' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
