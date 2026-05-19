@extends('layouts.app')

@section('content')
<div style="max-width:1200px;margin:0 auto">
    <div class="page-header">
        <h1>📋 {{ __('nav.campaigns') }}</h1>
        <a href="{{ route('send.form') }}" class="btn btn-primary">+ {{ __('New campaign') }}</a>
    </div>

    <div class="page-card" style="padding:0">
        <table class="students-grid" style="font-size:13px">
            <thead>
                <tr>
                    <th style="text-align:start;padding:12px 16px">#</th>
                    <th style="text-align:start">{{ __('Type') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('send.recipients') }}</th>
                    <th>✓ {{ __('Sent') }}</th>
                    <th>✗ {{ __('Failed') }}</th>
                    <th>💵 {{ __('Cost') }}</th>
                    <th>{{ __('columns.status') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($campaigns as $c)
                    <tr>
                        <td style="text-align:start;padding:10px 16px;color:var(--color-text-muted);font-size:11px">#{{ $c->id }}</td>
                        <td style="text-align:start;font-weight:600">{{ $c->typeLabel() }}</td>
                        <td style="font-family:ui-monospace,monospace;font-size:11px">
                            {{ $c->period_year }}/{{ str_pad($c->period_month, 2, '0', STR_PAD_LEFT) }}
                        </td>
                        <td>{{ $c->total_recipients }}</td>
                        <td><span class="pill pill-success">{{ $c->sent_count }}</span></td>
                        <td>@if($c->failed_count > 0)<span class="pill pill-danger">{{ $c->failed_count }}</span>@else<span class="text-soft">0</span>@endif</td>
                        <td class="fw-700">{{ number_format($c->actual_cost, 2) }}€</td>
                        <td>
                            @php
                                $cls = match($c->status) {
                                    'completed' => 'pill-success',
                                    'running' => 'pill-info',
                                    'paused' => 'pill-warning',
                                    'failed' => 'pill-danger',
                                    default => 'pill-muted',
                                };
                            @endphp
                            <span class="pill {{ $cls }}">{{ $c->status }}</span>
                        </td>
                        <td class="text-muted fs-xs">{{ $c->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <a href="{{ route('campaigns.show', $c) }}" class="btn btn-sm btn-soft-primary">👁️ {{ __('Open') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:60px;text-align:center;color:var(--color-text-soft)">
                        <div style="font-size:48px">📭</div>
                        <div class="mt-2">{{ __('No campaigns yet') }}</div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $campaigns->links() }}</div>
</div>
@endsection
