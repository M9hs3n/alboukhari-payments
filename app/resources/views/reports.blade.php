@extends('layouts.app')

@section('content')
<div style="max-width:1200px;margin:0 auto">
    <div class="page-header">
        <h1>📊 {{ __('nav.reports') }}</h1>

        <form method="GET" style="display:flex;gap:8px">
            <select name="year" class="form-select" style="width:auto;padding:6px 10px">
                @for ($y = date('Y') + 1; $y >= 2020; $y--)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <select name="month" class="form-select" style="width:auto;padding:6px 10px">
                @foreach ($months as $num => $name)
                    <option value="{{ $num }}" {{ $month == $num ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary">{{ __('Update') }}</button>
        </form>
    </div>

    <div class="kpi-grid">
        <div class="kpi info">
            <div class="label">📲 {{ __("Today's messages") }}</div>
            <div class="value">{{ $todayMessages }}</div>
            <div class="meta">{{ number_format($todayMessagesCost, 2) }} €</div>
        </div>
        <div class="kpi success">
            <div class="label">💵 {{ __('Collected today') }}</div>
            <div class="value">{{ number_format($todayPayments, 0) }} €</div>
        </div>
        <div class="kpi warning">
            <div class="label">📅 {{ __('Collected') }} {{ $months[$month] }}</div>
            <div class="value">{{ number_format($monthTotal, 0) }} €</div>
            <div class="meta">💵 {{ number_format($monthPaidCash, 0) }} • 🏦 {{ number_format($monthPaidBank, 0) }}</div>
        </div>
        <div class="kpi danger">
            <div class="label">⚠️ {{ __('Overdue now') }}</div>
            <div class="value">{{ count($overdue) }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:14px;margin-bottom:14px">
        <div class="page-card">
            <h3 style="margin-top:0">💰 {{ __('Payment methods') }} — {{ $months[$month] }}</h3>
            <div style="display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;justify-content:space-between;padding:10px 12px;background:var(--color-warning-soft);border-radius:var(--radius)">
                    <span>💵 {{ __('payment.method_cash') }}</span>
                    <strong>{{ number_format($monthPaidCash, 2) }} €</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:10px 12px;background:var(--color-info-soft);border-radius:var(--radius)">
                    <span>🏦 {{ __('payment.method_bank') }}</span>
                    <strong>{{ number_format($monthPaidBank, 2) }} €</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--color-surface-alt);border-radius:var(--radius);margin-top:6px;border-top:2px solid var(--color-border-strong)">
                    <strong>{{ __('Total') }}</strong>
                    <strong class="text-success" style="font-size:18px">{{ number_format($monthTotal, 2) }} €</strong>
                </div>
                @if ($monthTotal > 0)
                    <div class="fs-xs text-muted mt-3">
                        💵 {{ number_format($monthPaidCash / $monthTotal * 100, 1) }}% • 🏦 {{ number_format($monthPaidBank / $monthTotal * 100, 1) }}%
                    </div>
                    <div style="height:8px;background:var(--color-surface-alt);border-radius:999px;overflow:hidden;display:flex">
                        <div style="background:var(--color-warning);width:{{ $monthPaidCash / $monthTotal * 100 }}%"></div>
                        <div style="background:var(--color-info);flex:1"></div>
                    </div>
                @endif
            </div>
        </div>

        <div class="page-card">
            <h3 style="margin-top:0">📈 {{ __('Monthly collection') }} — {{ $year }}</h3>
            <table class="students-grid" style="font-size:12px">
                <thead>
                    <tr>
                        <th style="text-align:start;padding:8px">{{ __('Month') }}</th>
                        <th>💵</th>
                        <th>🏦</th>
                        <th>{{ __('Total') }}</th>
                        <th>📲 {{ __('Cost') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($months as $num => $name)
                        <tr>
                            <td style="text-align:start;padding:6px;font-weight:600">{{ $name }}</td>
                            <td>{{ number_format($monthlyTotals[$num]['cash'], 0) }}</td>
                            <td>{{ number_format($monthlyTotals[$num]['bank'], 0) }}</td>
                            <td class="fw-700 text-success">{{ number_format($monthlyTotals[$num]['total'], 0) }}</td>
                            <td class="text-muted">{{ number_format($messagesCostByMonth[$num], 2) }}€</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="page-card" style="padding:0">
        <h3 style="margin:0;padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center">
            <span>⚠️ {{ __('Overdue in') }} {{ $months[$month] }}</span>
            <span class="pill pill-danger">{{ count($overdue) }}</span>
        </h3>
        <div style="max-height:400px;overflow:auto">
            <table class="students-grid" style="font-size:13px">
                <thead>
                    <tr>
                        <th style="text-align:start;padding:8px 16px">{{ __('columns.name') }}</th>
                        <th>{{ __('columns.phone') }}</th>
                        <th>{{ __('columns.status') }}</th>
                        <th>{{ __('payment.remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($overdue as $row)
                        <tr>
                            <td style="text-align:start;padding:6px 16px;font-weight:600">{{ $row['student']->name }}</td>
                            <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--color-text-muted)">{{ $row['student']->phone_primary_e164 }}</td>
                            <td>
                                @php $sc = $row['status'] === 'late' ? 'pill-danger' : ($row['status'] === 'partial' ? 'pill-warning' : 'pill-muted'); @endphp
                                <span class="pill {{ $sc }}">{{ \App\Services\MonthStatusResolver::label($row['status']) }}</span>
                            </td>
                            <td class="fw-700 text-danger">{{ number_format($row['balance'], 0) }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="page-card mt-4" style="padding:0">
        <h3 style="margin:0;padding:16px 20px;border-bottom:1px solid var(--color-border)">🔴 {{ __('Top debtors') }} — {{ $year }}</h3>
        <table class="students-grid" style="font-size:13px">
            <thead>
                <tr>
                    <th style="text-align:start;padding:8px 16px">{{ __('columns.name') }}</th>
                    <th>{{ __('columns.phone') }}</th>
                    <th>{{ __('Months behind') }}</th>
                    <th>{{ __('Total balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($topDebtors as $row)
                    <tr>
                        <td style="text-align:start;padding:6px 16px;font-weight:600">{{ $row['st']->name }}</td>
                        <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--color-text-muted)">{{ $row['st']->phone_primary_e164 }}</td>
                        <td><span class="pill pill-warning">{{ $row['monthsBehind'] }}</span></td>
                        <td class="fw-700 text-danger">{{ number_format($row['totalBal'], 0) }} €</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding:60px;text-align:center;color:var(--color-text-soft)">🎉 {{ __('No serious debtors') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
