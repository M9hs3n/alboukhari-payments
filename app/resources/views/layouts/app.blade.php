@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app_name') }}</title>

    {{-- Alpine.js (يدعم Livewire 4) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- CSS مخصّص --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=2.1">

    @livewireStyles
</head>
<body>

{{-- ====== Topbar ====== --}}
<nav class="topbar">
    <a href="{{ route('home') }}" class="brand" style="text-decoration:none;">
        <span class="logo-mark">AB</span>
        <span class="d-none-mobile">{{ __('school_name') }}</span>
    </a>

    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
        {{ __('nav.home') }}
    </a>
    <a href="{{ route('quick-entry') }}" class="nav-link {{ request()->routeIs('quick-entry') ? 'active' : '' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        {{ __('nav.quick_entry') }}
    </a>
    <a href="{{ route('send.form') }}" class="nav-link {{ request()->routeIs('send.*') ? 'active' : '' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
        {{ __('nav.send') }}
    </a>
    <a href="{{ route('campaigns.index') }}" class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
        {{ __('nav.campaigns') }}
    </a>
    <a href="{{ route('templates.index') }}" class="nav-link {{ request()->routeIs('templates.*') ? 'active' : '' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        {{ __('nav.templates') }}
    </a>
    <a href="{{ route('reports') }}" class="nav-link {{ request()->routeIs('reports') ? 'active' : '' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
        {{ __('nav.reports') }}
    </a>
    <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        {{ __('nav.settings') }}
    </a>

    <span class="topbar-spacer"></span>

    {{-- Keyboard shortcuts helper --}}
    <button
        type="button"
        class="kbd-help-btn"
        title="{{ __('shortcuts.button_label') }}"
        aria-label="{{ __('shortcuts.button_label') }}"
        @click.prevent="$dispatch('open-shortcuts')"
        x-data
    >?</button>

    {{-- Language switcher --}}
    <div class="lang-switcher" title="{{ __('topbar.language') }}">
        <a href="{{ route('locale.switch', 'en') }}" class="{{ $locale === 'en' ? 'active' : '' }}">EN</a>
        <a href="{{ route('locale.switch', 'nl') }}" class="{{ $locale === 'nl' ? 'active' : '' }}">NL</a>
        <a href="{{ route('locale.switch', 'ar') }}" class="{{ $locale === 'ar' ? 'active' : '' }}">عر</a>
    </div>

    @php $halted = \App\Services\HaltService::isHalted(); @endphp
    <form method="POST" action="{{ route('halt') }}" style="display:inline;margin-inline-start:8px">
        @csrf
        <input type="hidden" name="action" value="{{ $halted ? 'resume' : 'halt' }}">
        <button type="submit" class="btn btn-sm {{ $halted ? 'btn-success' : 'btn-danger' }}">
            {{ $halted ? '▶ ' . __('topbar.resume') : '⛔ ' . __('topbar.halt') }}
        </button>
    </form>
</nav>

@if (session('flash'))
    <div
        class="toast-stack"
        x-data="{ open: true }"
        x-show="open"
        x-init="setTimeout(()=> open=false, 4000)"
    >
        <div class="toast {{ session('flash_type', 'success') }}">{{ session('flash') }}</div>
    </div>
@endif

<main class="main">
    {{ $slot ?? '' }}
    @yield('content')
</main>

{{-- Toast container — universal --}}
<div
    class="toast-stack"
    x-data="{ toasts: [] }"
    @toast.window="
        const id = Date.now() + Math.random();
        toasts.push({ id, msg: $event.detail.message || $event.detail, type: $event.detail.type || 'success' });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id); }, 3500);
    "
    @flash.window="
        const id = Date.now() + Math.random();
        toasts.push({ id, msg: $event.detail.message, type: 'success' });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id); }, 3500);
    "
>
    <template x-for="t in toasts" :key="t.id">
        <div class="toast" :class="t.type" x-text="t.msg"></div>
    </template>
</div>

{{-- Keyboard shortcuts dialog --}}
<div
    x-data="{ open: false }"
    x-cloak
    @open-shortcuts.window="open = true"
    @close-panels.window="open = false"
    @keydown.window.escape="open = false"
>
    <div
        class="modal-backdrop"
        x-show="open"
        x-transition.opacity.duration.150ms
        @click.self="open = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="shortcuts-dialog-title"
    >
        <div
            class="modal-box shortcuts-modal"
            x-show="open"
            @click.stop
        >
            <div class="modal-header">
                <h3 id="shortcuts-dialog-title">⌨️ {{ __('shortcuts.title') }}</h3>
                <button type="button" class="btn btn-sm btn-ghost" @click="open = false" aria-label="{{ __('common.close') }}">✕</button>
            </div>
            <div class="modal-body">
                <section class="shortcut-group">
                    <h4>{{ __('shortcuts.group_global') }}</h4>
                    <dl class="shortcut-list">
                        <dt><kbd>?</kbd></dt>
                        <dd>{{ __('shortcuts.show_dialog') }}</dd>
                        <dt><kbd>/</kbd></dt>
                        <dd>{{ __('shortcuts.focus_search') }}</dd>
                        <dt><kbd>Esc</kbd></dt>
                        <dd>{{ __('shortcuts.close_panels') }}</dd>
                    </dl>
                </section>
                <section class="shortcut-group">
                    <h4>{{ __('shortcuts.group_payment') }}</h4>
                    <dl class="shortcut-list">
                        <dt><kbd>N</kbd></dt>
                        <dd>{{ __('shortcuts.cash') }}</dd>
                        <dt><kbd>B</kbd></dt>
                        <dd>{{ __('shortcuts.bank') }}</dd>
                        <dt><kbd>Ctrl</kbd> <span class="kbd-plus">+</span> <kbd>Enter</kbd></dt>
                        <dd>{{ __('shortcuts.save_and_next') }}</dd>
                    </dl>
                </section>
                <p class="shortcut-tip">💡 {{ __('shortcuts.tip') }}</p>
            </div>
        </div>
    </div>
</div>

@livewireScripts

<script>
    // Global keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        const tag = document.activeElement?.tagName;
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || document.activeElement?.isContentEditable;

        if (e.key === 'Escape') {
            window.dispatchEvent(new CustomEvent('close-panels'));
            return;
        }
        if (typing) return;

        if (e.key === '/') {
            e.preventDefault();
            const search = document.querySelector('.search-global, input.search');
            if (search) search.focus();
        } else if (e.key === '?') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('open-shortcuts'));
        }
    });
</script>

</body>
</html>
