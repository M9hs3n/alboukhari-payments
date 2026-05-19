<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMonthlyMarker extends Model
{
    protected $fillable = ['student_id', 'period_year', 'period_month', 'type'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
