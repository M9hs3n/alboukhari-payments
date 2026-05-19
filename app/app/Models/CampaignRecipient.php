<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id', 'student_id', 'family_id',
        'phone_e164', 'body_personalized', 'status',
        'skip_reason', 'provider_message_id', 'provider_status',
        'segments', 'cost', 'attempts', 'last_error', 'sent_at', 'idempotency_key',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'cost' => 'decimal:4',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
