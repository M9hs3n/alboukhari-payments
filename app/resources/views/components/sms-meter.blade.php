@props(['counter' => null])
@php
    $counter = $counter ?? ['length' => 0, 'segments' => 0, 'encoding' => 'gsm', 'max_per_segment' => 160];

    $length = (int) ($counter['length'] ?? 0);
    $segments = (int) ($counter['segments'] ?? 0);
    $encoding = $counter['encoding'] ?? 'gsm';
    $isUnicode = $encoding === 'unicode';

    $singleMax = $isUnicode ? 70 : 160;
    $multiMax = $isUnicode ? 67 : 153;

    if ($segments <= 1) {
        $segCapacity = $singleMax;
        $segPosition = $length;
    } else {
        $segCapacity = $multiMax;
        $segPosition = $length - ($segments - 1) * $multiMax;
    }
    $segRemaining = max(0, $segCapacity - $segPosition);
    $fillPct = $segCapacity > 0 ? min(100, (int) round($segPosition * 100 / $segCapacity)) : 0;
    $totalCap = $segments <= 1 ? $singleMax : $segments * $multiMax;

    if ($segments === 0) {
        $level = 'muted';
    } elseif ($segments === 1) {
        $level = $segRemaining <= 20 ? 'warning' : 'success';
    } elseif ($segments === 2) {
        $level = 'warning';
    } else {
        $level = 'danger';
    }

    $encodingLabel = $isUnicode ? __('sms_meter.encoding_unicode') : __('sms_meter.encoding_gsm');
    $encodingTitle = $isUnicode ? __('sms_meter.encoding_unicode_hint') : __('sms_meter.encoding_gsm_hint');
@endphp

<div class="sms-meter sms-meter--{{ $level }}" role="status" aria-live="polite">
    <div class="sms-meter__head">
        <div class="sms-meter__count" aria-label="{{ __('sms_meter.aria_chars', ['length' => $length, 'total' => $totalCap]) }}">
            <strong>{{ $length }}</strong><span class="sms-meter__sep">/</span><span class="sms-meter__cap">{{ $totalCap }}</span>
        </div>
        <span class="sms-meter__pill sms-meter__pill--{{ $level }}" aria-label="{{ __('sms_meter.aria_segments', ['count' => $segments]) }}">
            <span class="sms-meter__pill-num">{{ $segments }}</span>
            <span class="sms-meter__pill-label">{{ __('sms_meter.sms_short') }}</span>
        </span>
        <span class="sms-meter__encoding" title="{{ $encodingTitle }}">{{ $encodingLabel }}</span>
    </div>

    <div class="sms-meter__bar" aria-hidden="true">
        <div class="sms-meter__fill" style="inline-size: {{ $fillPct }}%"></div>
    </div>

    <div class="sms-meter__hint">
        @if ($length === 0)
            {{ __('sms_meter.hint_empty') }}
        @elseif ($segments === 1 && $segRemaining > 20)
            {{ __('sms_meter.hint_room', ['remaining' => $segRemaining]) }}
        @elseif ($segments === 1)
            <span class="sms-meter__hint-warn">⚠ {{ __('sms_meter.hint_close', ['remaining' => $segRemaining]) }}</span>
        @else
            @php $charsToSave = max(0, $length - ($segments === 2 ? $singleMax : ($segments - 1) * $multiMax)); @endphp
            <span class="sms-meter__hint-warn">{{ __('sms_meter.hint_overflow', ['segments' => $segments, 'reduce' => $segments - 1, 'chars' => $charsToSave]) }}</span>
        @endif
    </div>
</div>
