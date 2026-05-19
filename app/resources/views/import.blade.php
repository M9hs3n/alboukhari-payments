@extends('layouts.app')

@section('content')
<div style="max-width:600px;margin:0 auto">
    <div class="page-header">
        <h1>📥 {{ __('actions.import_excel') }}</h1>
    </div>

    <div class="page-card">
        <p class="text-muted fs-sm">{{ __('The file must match the original sheet structure:') }}</p>
        <ul style="font-size:12px;color:var(--color-text-muted);background:var(--color-surface-alt);padding:14px;border-radius:var(--radius);list-style-position:inside">
            <li>{{ __('Columns:') }} <code>id, Naam, Telefoon, sms, whatsapp, send_all, Tweede telefoonnummer, January..December</code></li>
            <li>{{ __('Numeric month values = payments (default method: bank)') }}</li>
            <li>{{ __('Value 0 = recorded via bank previously (legacy_zero)') }}</li>
            <li>{{ __('Value X = manually marked late') }}</li>
        </ul>

        @if (session('flash'))
            <div class="pill pill-danger mb-3">{{ session('flash') }}</div>
        @endif

        @if ($errors->any())
            <div style="padding:12px;background:var(--color-danger-soft);border-radius:var(--radius);margin-bottom:12px;color:#7f1d1d">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('import.run') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>{{ __('File') }} (xlsx, xls, csv)</label>
                <input type="file" name="file" class="form-input" required accept=".xlsx,.xls,.csv">
            </div>
            <div class="form-group">
                <label>{{ __('Year (used to interpret month columns)') }}</label>
                <input type="number" name="year" class="form-input" value="{{ date('Y') }}" min="2020" max="2099">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <a href="{{ route('home') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn-primary">🚀 {{ __('Start import') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
