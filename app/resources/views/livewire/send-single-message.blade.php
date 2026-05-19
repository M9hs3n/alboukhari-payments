<div>
    @if ($isOpen)
        <div class="modal-backdrop" wire:click.self="close" @keydown.window.escape="$wire.close()">
            <div class="modal-box" style="width:560px" @click.stop>
                <div class="modal-header">
                    <h3>📲 {{ __('Message to') }} {{ $studentName }}</h3>
                    <button class="btn btn-sm btn-ghost" wire:click="close">✕</button>
                </div>
                <div class="modal-body">
                    <div class="summary-item" style="margin-bottom:14px">
                        <div class="label">📞 {{ __('columns.phone') }}</div>
                        <div class="value" style="font-family:ui-monospace,monospace">{{ $studentPhone }}</div>
                    </div>

                    <div class="form-group">
                        <label>{{ __('send.template') }}</label>
                        <select class="form-select" wire:model.live="templateId">
                            <option value="">{{ __('send.free_text') }}</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                            @endforeach
                        </select>
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
                        <textarea class="form-textarea" wire:model.live.debounce.300ms="body" rows="6" placeholder="Type your message..."></textarea>
                    </div>

                    @if ($resultMessage)
                        <div class="pill {{ str_starts_with($resultMessage,'✓') ? 'pill-success' : 'pill-danger' }}" style="display:block;padding:10px;margin-bottom:8px">
                            {{ $resultMessage }}
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button class="btn" wire:click="close">{{ __('common.cancel') }}</button>
                    <button class="btn btn-primary" wire:click="send" wire:loading.attr="disabled">📨 {{ __('Send now') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
