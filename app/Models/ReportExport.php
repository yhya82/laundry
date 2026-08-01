<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'report_type',
        'export_format',
        'date_range_start',
        'date_range_end',
        'filters',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'date_range_start' => 'date',
            'date_range_end' => 'date',
            'filters' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
