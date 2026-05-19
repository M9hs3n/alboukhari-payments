<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'period_year',
        'period_month',
        'amount',
        'paid_at',
        'method',
        'note',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'decimal:2',
        'period_year' => 'integer',
        'period_month' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function methodIcon(): string
    {
        return match ($this->method) {
            'cash' => '💵',
            'bank' => '🏦',
            'legacy_zero' => '🔵',
            default => '',
        };
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'cash' => 'نقد',
            'bank' => 'بنكي',
            'legacy_zero' => 'مستورد من الشيت',
            default => $this->method,
        };
    }
}
