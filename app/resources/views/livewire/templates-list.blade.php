<div style="max-width:1200px;margin:0 auto;display:grid;grid-template-columns:{{ $editing ? '1fr 1fr' : '1fr' }};gap:14px">
    <div class="page-card" style="padding:0">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--color-border)">
            <h2 style="margin:0">📝 {{ __('nav.templates') }}</h2>
            <button class="btn btn-primary" wire:click="newTemplate">+ {{ __('New template') }}</button>
        </div>

        <table class="students-grid" style="font-size:13px">
            <thead>
                <tr>
                    <th style="text-align:start;padding:8px 16px">{{ __('Name') }}</th>
                    <th>{{ __('Language') }}</th>
                    <th>{{ __('Default for') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $t)
                    <tr>
                        <td style="text-align:start;padding:10px 16px">
                            <strong>{{ $t->name }}</strong>
                            <div class="fs-xs text-muted" style="font-family:ui-monospace,monospace">{{ $t->code }}</div>
                            <div class="fs-xs text-muted mt-2" style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->body }}</div>
                        </td>
                        <td><span class="pill pill-info">{{ strtoupper($t->language) }}</span></td>
                        <td>
                            @if ($t->default_for !== 'none')
                                <span class="pill pill-warning">{{ $t->default_for }}</span>
                            @else
                                <span class="text-soft">—</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-soft-primary" wire:click="edit({{ $t->id }})">✏️</button>
                            <button class="btn btn-sm" wire:click="duplicate({{ $t->id }})">📋</button>
                            <button class="btn btn-sm btn-soft-danger" wire:click="delete({{ $t->id }})" wire:confirm="{{ __('common.confirm') }}">🗑️</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($editing)
        <div class="page-card">
            <h3 style="margin-top:0">{{ $editId ? __('common.edit') : __('New template') }}</h3>
            <div class="form-group">
                <label>Code <span class="text-muted">(unique key)</span></label>
                <input type="text" class="form-input" wire:model="code" placeholder="e.g. nl_my_template">
                @error('code') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group">
                <label>{{ __('Display name') }}</label>
                <input type="text" class="form-input" wire:model="name">
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label>{{ __('Language') }}</label>
                    <select class="form-select" wire:model="language">
                        <option value="nl">Nederlands</option>
                        <option value="ar">العربية</option>
                        <option value="en">English</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ __('Default for') }}</label>
                    <select class="form-select" wire:model="default_for">
                        <option value="none">— None —</option>
                        <option value="first_friday">First Friday</option>
                        <option value="mid_month">Mid Month</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>
                    {{ __('send.body') }}
                    @if ($counter)
                        <span style="float:right;font-size:11px;color:{{ $counter['segments'] > 1 ? 'var(--color-danger)' : 'var(--color-success)' }};font-weight:700">
                            📊 {{ $counter['length'] }}/{{ $counter['max_per_segment'] }} — <strong>{{ $counter['segments'] }} SMS</strong>
                        </span>
                    @endif
                </label>
                <textarea class="form-textarea" wire:model.live.debounce.300ms="body" rows="7"></textarea>
                <small class="text-muted">
                    <code>@{{Naam}}</code> <code>@{{month}}</code> <code>@{{المستحق}}</code> <code>@{{المتبقي}}</code> <code>@{{أسماء_الأبناء}}</code> <code>@{{المبلغ_العائلي}}</code>
                </small>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button class="btn" wire:click="$set('editing', false)">{{ __('common.cancel') }}</button>
                <button class="btn btn-primary" wire:click="save">💾 {{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
