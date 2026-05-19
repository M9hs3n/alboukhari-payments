<div style="max-width:1200px;margin:0 auto;display:grid;grid-template-columns:{{ $editing ? '1fr 1fr' : '1fr' }};gap:14px">
    <div class="page-card" style="padding:0">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--color-border)">
            <h2 style="margin:0">📝 {{ __('nav.templates') }}</h2>
            <button class="btn btn-primary" wire:click="newTemplate">+ {{ __('templates.new') }}</button>
        </div>

        @php
            $defaultForLabel = function (string $key): string {
                return match ($key) {
                    'first_friday' => __('templates.default_first_friday'),
                    'mid_month'    => __('templates.default_mid_month'),
                    default        => $key,
                };
            };
        @endphp

        <table class="students-grid" style="font-size:13px">
            <thead>
                <tr>
                    <th style="text-align:start;padding:8px 16px">{{ __('templates.col_name') }}</th>
                    <th>{{ __('templates.col_language') }}</th>
                    <th>{{ __('templates.col_default_for') }}</th>
                    <th aria-label="{{ __('actions.view_details') }}"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $t)
                    <tr>
                        <td style="text-align:start;padding:10px 16px">
                            <strong>{{ $t->name }}</strong>
                            <div class="fs-xs text-muted" style="font-family:ui-monospace,monospace">{{ $t->code }}</div>
                            <div class="fs-xs text-muted mt-2" style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->body }}</div>
                        </td>
                        <td><span class="pill pill-info">{{ strtoupper($t->language) }}</span></td>
                        <td>
                            @if ($t->default_for !== 'none')
                                <span class="pill pill-warning">{{ $defaultForLabel($t->default_for) }}</span>
                            @else
                                <span class="text-soft">—</span>
                            @endif
                        </td>
                        <td style="white-space:nowrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-soft-primary"
                                wire:click="edit({{ $t->id }})"
                                title="{{ __('templates.action_edit') }}"
                                aria-label="{{ __('templates.action_edit') }}"
                            >✏️</button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                wire:click="duplicate({{ $t->id }})"
                                title="{{ __('templates.action_duplicate') }}"
                                aria-label="{{ __('templates.action_duplicate') }}"
                            >📋</button>
                            <button
                                type="button"
                                class="btn btn-sm btn-soft-danger"
                                wire:click="delete({{ $t->id }})"
                                wire:confirm="{{ __('common.confirm') }}"
                                title="{{ __('templates.action_delete') }}"
                                aria-label="{{ __('templates.action_delete') }}"
                            >🗑️</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding:56px 24px;text-align:center;color:var(--color-text-soft)">
                            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;margin-bottom:10px" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <path d="M14 2v6h6"/>
                                <path d="M9 13h6M9 17h4"/>
                            </svg>
                            <div style="font-size:15px;font-weight:600;color:var(--color-text)">{{ __('templates.empty_title') }}</div>
                            <div class="mt-2" style="max-width:380px;margin-inline:auto;line-height:1.6">{{ __('templates.empty_body') }}</div>
                            <button class="btn btn-primary mt-3" wire:click="newTemplate">+ {{ __('templates.new') }}</button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($editing)
        <div
            class="page-card"
            x-data
            @keydown.window.escape="$wire.set('editing', false)"
        >
            <h3 style="margin-top:0">{{ $editId ? __('common.edit') : __('templates.new') }}</h3>
            <div class="form-group">
                <label>{{ __('templates.code') }} <span class="text-muted">({{ __('templates.code_hint') }})</span></label>
                <input type="text" class="form-input" wire:model="code" placeholder="{{ __('templates.code_placeholder') }}">
                @error('code') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group">
                <label>{{ __('templates.display_name') }}</label>
                <input type="text" class="form-input" wire:model="name">
                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label>{{ __('templates.language') }}</label>
                    <select class="form-select" wire:model="language">
                        <option value="nl">Nederlands</option>
                        <option value="ar">العربية</option>
                        <option value="en">English</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ __('templates.default_for') }}</label>
                    <select class="form-select" wire:model="default_for">
                        <option value="none">{{ __('templates.default_none') }}</option>
                        <option value="first_friday">{{ __('templates.default_first_friday') }}</option>
                        <option value="mid_month">{{ __('templates.default_mid_month') }}</option>
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
