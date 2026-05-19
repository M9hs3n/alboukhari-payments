@extends('layouts.app')

@section('content')
<div style="max-width:1200px;margin:0 auto">
    <div class="page-header">
        <h1>📨 #{{ $campaign->id }} — {{ $campaign->typeLabel() }}</h1>
        <a href="{{ route('campaigns.index') }}" class="btn">← {{ __('campaigns.back_to_log') }}</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi info">
            <div class="label">👥 {{ __('send.recipients') }}</div>
            <div class="value">{{ $campaign->total_recipients }}</div>
        </div>
        <div class="kpi success">
            <div class="label">✓ {{ __('campaigns.sent') }}</div>
            <div class="value">{{ $campaign->sent_count }}</div>
        </div>
        <div class="kpi danger">
            <div class="label">✗ {{ __('campaigns.failed') }}</div>
            <div class="value">{{ $campaign->failed_count }}</div>
        </div>
        <div class="kpi warning">
            <div class="label">💵 {{ __('common.cost') }}</div>
            <div class="value">{{ number_format($campaign->actual_cost, 2) }}€</div>
        </div>
    </div>

    <div class="page-card">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:12px">
            <div><span class="text-muted">{{ __('common.period') }}:</span> <strong>{{ $campaign->period_year }}/{{ str_pad($campaign->period_month, 2, '0', STR_PAD_LEFT) }}</strong></div>
            <div><span class="text-muted">Tag:</span> <code>{{ $campaign->tag ?: '—' }}</code></div>
            <div><span class="text-muted">{{ __('campaigns.started') }}:</span> {{ $campaign->started_at?->format('Y-m-d H:i') ?? '—' }}</div>
            <div><span class="text-muted">{{ __('campaigns.finished') }}:</span> {{ $campaign->finished_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>

        <h3 class="mt-4">{{ __('campaigns.original_message') }}</h3>
        <div style="background:var(--color-warning-soft);padding:12px;border-radius:var(--radius);white-space:pre-wrap;font-size:13px;line-height:1.6">{{ $campaign->body_template }}</div>
    </div>

    <div class="page-card" style="padding:0">
        <h3 style="margin:0;padding:16px 20px;border-bottom:1px solid var(--color-border)">{{ __('campaigns.recipients') }}</h3>
        <table class="students-grid" style="font-size:12px">
            <thead>
                <tr>
                    <th style="padding:8px">#</th>
                    <th style="text-align:start">{{ __('campaigns.recipient') }}</th>
                    <th>{{ __('columns.phone') }}</th>
                    <th>📲 SMS</th>
                    <th>{{ __('columns.status') }}</th>
                    <th>{{ __('common.cost') }}</th>
                    <th style="text-align:start">{{ __('campaigns.last_error') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($campaign->recipients as $r)
                    @php
                        $cls = match($r->status) {
                            'sent' => 'pill-success',
                            'failed' => 'pill-danger',
                            'sending' => 'pill-info',
                            default => 'pill-muted',
                        };
                        $rKey = "campaigns.recipient_status.{$r->status}";
                        $rLabel = __($rKey);
                        if ($rLabel === $rKey) {
                            $rLabel = $r->status;
                        }
                    @endphp
                    <tr>
                        <td style="color:var(--color-text-muted)">{{ $r->id }}</td>
                        <td style="text-align:start;font-weight:600">{{ $r->student?->name ?? '—' }}</td>
                        <td style="font-family:ui-monospace,monospace;font-size:11px">{{ $r->phone_e164 }}</td>
                        <td>{{ $r->segments }}</td>
                        <td><span class="pill {{ $cls }}">{{ $rLabel }}</span></td>
                        <td>{{ number_format($r->cost, 4) }}€</td>
                        <td style="text-align:start;font-size:11px;color:var(--color-danger)">{{ \Illuminate\Support\Str::limit($r->last_error, 50) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
