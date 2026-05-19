<div style="max-width:760px;margin:0 auto">
    <div class="page-header">
        <h1>⚡ {{ __('actions.quick_entry') }}</h1>
        <a href="{{ route('home') }}" class="btn">← {{ __('Back to grid') }}</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi info">
            <div class="label">✅ {{ __('Saved this session') }}</div>
            <div class="value">{{ $sessionCount }}</div>
        </div>
        <div class="kpi success">
            <div class="label">💵 {{ __('Session total') }}</div>
            <div class="value">{{ number_format($sessionTotal, 2) }} €</div>
        </div>
        <div class="kpi warning">
            <div class="label">📅 {{ __('Period') }}</div>
            <div class="value" style="font-size:20px">{{ $months[$month] }} {{ $year }}</div>
        </div>
    </div>

    <div class="page-card">
        <div class="form-row cols-2">
            <div class="form-group">
                <label>{{ __('filters.year') }}</label>
                <select class="form-select" wire:model.live="year">
                    @for ($y = date('Y') + 1; $y >= 2020; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('filters.month') }}</label>
                <select class="form-select" wire:model.live="month">
                    @foreach ($months as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (!$selectedStudent)
            <div
                x-data
                x-init="$nextTick(() => $refs.search.focus())"
                @focus-search.window="$nextTick(() => $refs.search.focus())"
            >
                <label>🔍 {{ __('Search student (name, phone, or ID)') }}</label>
                <input
                    x-ref="search"
                    type="text"
                    class="form-input"
                    wire:model.live.debounce.150ms="search"
                    placeholder="{{ __('Start typing...') }}"
                    style="font-size:18px;padding:14px"
                    autofocus
                >

                @if (count($candidates) > 0)
                    <div style="margin-top:12px;border:1px solid var(--color-border);border-radius:var(--radius);overflow:hidden">
                        @foreach ($candidates as $c)
                            @php
                                $bal = \App\Services\FeeResolver::balance($c, $year, $month);
                                $status = \App\Services\MonthStatusResolver::resolve($c, $year, $month);
                            @endphp
                            <div
                                wire:click="selectStudent({{ $c->id }})"
                                style="padding:12px 14px;border-bottom:1px solid var(--color-border);cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:background 0.15s"
                                onmouseover="this.style.background='var(--color-surface-alt)'"
                                onmouseout="this.style.background='white'"
                            >
                                <div>
                                    <strong>{{ $c->name }}</strong>
                                    <div class="fs-xs text-muted">{{ $c->phone_primary_e164 }} • ID: {{ $c->external_id ?? $c->id }}</div>
                                </div>
                                <div style="text-align:end">
                                    <span class="pill pill-muted">{{ \App\Services\MonthStatusResolver::label($status) }}</span>
                                    @if ($bal > 0)
                                        <div class="fw-700 text-danger mt-2">{{ number_format($bal, 0) }} €</div>
                                    @else
                                        <div class="text-success mt-2">✓ paid</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif (strlen($search) >= 2)
                    <p class="text-soft mt-3" style="text-align:center;padding:20px">{{ __('common.no_results') }}</p>
                @endif
            </div>
        @else
            <div
                x-data
                x-init="$nextTick(() => document.getElementById('quick-amount').focus())"
                @keydown.window.escape="$wire.set('selectedStudentId', null)"
                @keydown.window.n.prevent="$wire.setMethod('cash')"
                @keydown.window.b.prevent="$wire.setMethod('bank')"
                @keydown.window.ctrl.enter.prevent="$wire.save()"
            >
                <div style="background:var(--color-warning-soft);padding:14px;border-radius:var(--radius);margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <strong style="font-size:18px">{{ $selectedStudent->name }}</strong>
                        <div class="fs-xs text-muted">{{ $selectedStudent->phone_primary_e164 }}</div>
                    </div>
                    <button class="btn btn-sm" wire:click="$set('selectedStudentId', null)">↶ Change</button>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="label">{{ __('payment.due') }}</div>
                        <div class="value">{{ number_format($dueAmount, 2) }} €</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">{{ __('Paid') }}</div>
                        <div class="value">{{ number_format($paidSoFar, 2) }} €</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>{{ __('payment.amount') }}</label>
                    <input
                        id="quick-amount"
                        type="number"
                        step="0.01"
                        class="form-input"
                        wire:model="amount"
                        style="font-size:26px;font-weight:700;text-align:center;padding:14px"
                    >
                </div>

                <div class="form-group">
                    <label>{{ __('payment.method') }} <small class="text-muted">(N / B)</small></label>
                    <div class="method-toggle">
                        <button type="button" class="cash {{ $method === 'cash' ? 'active' : '' }}" wire:click="setMethod('cash')">💵 {{ __('payment.method_cash') }}</button>
                        <button type="button" class="bank {{ $method === 'bank' ? 'active' : '' }}" wire:click="setMethod('bank')">🏦 {{ __('payment.method_bank') }}</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>{{ __('payment.note') }}</label>
                    <input type="text" class="form-input" wire:model="note">
                </div>

                <button class="btn btn-primary btn-lg" wire:click="save" style="width:100%">
                    💾 {{ __('payment.save_and_next') }} <span class="text-soft" style="margin-inline-start:10px;font-size:11px">Ctrl+Enter</span>
                </button>
            </div>
        @endif
    </div>

    @if (count($sessionLog) > 0)
        <div class="page-card" style="padding:0">
            <h3 style="margin:0;padding:14px 18px;border-bottom:1px solid var(--color-border)">📋 {{ __('Last payments in this session') }}</h3>
            <table class="students-grid" style="font-size:13px">
                <thead>
                    <tr>
                        <th style="text-align:start;padding:8px 16px">⏰</th>
                        <th style="text-align:start">{{ __('Student') }}</th>
                        <th>{{ __('payment.method') }}</th>
                        <th style="text-align:end">{{ __('payment.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessionLog as $entry)
                        <tr>
                            <td style="padding:6px 16px;color:var(--color-text-muted);font-family:ui-monospace,monospace;font-size:11px">{{ $entry['time'] }}</td>
                            <td style="text-align:start;padding:6px 16px;font-weight:600">{{ $entry['student'] }}</td>
                            <td>{{ $entry['icon'] }}</td>
                            <td style="text-align:end;padding:6px 16px;font-weight:700">{{ number_format($entry['amount'], 2) }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
