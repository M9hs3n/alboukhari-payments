<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    protected $fillable = [
        'guardian_name',
        'phone_primary_e164',
        'phone_secondary_e164',
        'preferred_language',
        'is_blocked_messages',
        'notes',
    ];

    protected $casts = [
        'is_blocked_messages' => 'boolean',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function displayName(): string
    {
        return $this->guardian_name ?: ('عائلة ' . optional($this->students->first())->name);
    }
}
