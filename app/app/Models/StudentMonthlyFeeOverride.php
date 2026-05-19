<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMonthlyFeeOverride extends Model
{
    protected $fillable = ['student_id', 'period_year', 'period_month', 'amount', 'reason'];
    protected $casts = ['amount' => 'decimal:2'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
