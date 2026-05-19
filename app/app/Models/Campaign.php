<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'type', 'status', 'period_year', 'period_month', 'threshold_amount',
        'template_id', 'body_template', 'tag', 'group_by_family',
        'total_recipients', 'sent_count', 'failed_count', 'skipped_count',
        'estimated_cost', 'actual_cost',
        'started_at', 'finished_at', 'created_by',
    ];

    protected $casts = [
        'group_by_family' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'threshold_amount' => 'decimal:2',
        'estimated_cost' => 'decimal:4',
        'actual_cost' => 'decimal:4',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function typeLabel(): string
    {
        $key = "campaigns.type.{$this->type}";
        $translated = __($key);

        return $translated === $key ? $this->type : $translated;
    }
}
