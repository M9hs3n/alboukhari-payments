@extends('layouts.app')

@section('content')
<div style="max-width:640px;margin:0 auto">
    <div class="page-header">
        <h1>📥 {{ __('import.title') }}</h1>
    </div>

    <div class="page-card">
        <p class="text-muted fs-sm" style="margin-top:0">{{ __('import.subtitle') }}</p>

        <details class="import-help">
            <summary>{{ __('import.requirements.title') }}</summary>
            <ul>
                <li>{{ __('import.requirements.columns') }}</li>
                <li>{{ __('import.requirements.numeric') }}</li>
                <li>{{ __('import.requirements.zero') }}</li>
                <li>{{ __('import.requirements.x') }}</li>
            </ul>
        </details>

        @if (session('flash') && session('flash_type') === 'error')
            <div class="pill pill-danger" style="margin-bottom:12px;display:block">{{ session('flash') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('import.run') }}"
            enctype="multipart/form-data"
            x-data="importDropzone()"
            @submit="submitting = !!file"
        >
            @csrf

            <div class="form-group">
                <label for="import-file">{{ __('import.field.file') }}</label>

                <label
                    for="import-file"
                    class="dropzone"
                    :class="{ 'is-dragging': dragging, 'has-file': !!file }"
                    @dragover.prevent="dragging = true"
                    @dragenter.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop($event)"
                    role="button"
                    tabindex="0"
                    @keydown.enter.prevent="$refs.input.click()"
                    @keydown.space.prevent="$refs.input.click()"
                    aria-describedby="import-file-supported"
                >
                    <input
                        id="import-file"
                        type="file"
                        name="file"
                        x-ref="input"
                        required
                        accept=".xlsx,.xls,.csv"
                        @change="setFile($event.target.files)"
                        class="dropzone-input"
                    >

                    <template x-if="!file">
                        <div class="dropzone-empty">
                            <svg class="dropzone-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <strong>{{ __('import.field.file_hint') }}</strong>
                            <span id="import-file-supported" class="text-muted fs-sm">{{ __('import.field.file_supported') }}</span>
                        </div>
                    </template>

                    <template x-if="file">
                        <div class="dropzone-file">
                            <svg class="dropzone-file-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="8" y1="13" x2="16" y2="13"/>
                                <line x1="8" y1="17" x2="13" y2="17"/>
                            </svg>
                            <div class="dropzone-file-meta">
                                <strong x-text="file.name"></strong>
                                <small class="text-muted" x-text="prettySize(file.size)"></small>
                            </div>
                            <button
                                type="button"
                                class="btn btn-ghost btn-icon dropzone-remove"
                                @click.prevent.stop="reset()"
                                :aria-label="@js(__('common.remove'))"
                                :title="@js(__('common.remove'))"
                            >
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </label>
            </div>

            <div class="form-group">
                <label for="import-year">{{ __('import.field.year') }}</label>
                <input
                    id="import-year"
                    type="number"
                    name="year"
                    class="form-input"
                    value="{{ old('year', date('Y')) }}"
                    min="2020"
                    max="2099"
                    style="max-width:160px"
                    aria-describedby="import-year-hint"
                >
                <small id="import-year-hint" class="text-muted fs-sm" style="display:block;margin-top:4px">{{ __('import.field.year_hint') }}</small>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px">
                <a href="{{ route('home') }}" class="btn">{{ __('common.cancel') }}</a>
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="!file || submitting"
                >
                    <span x-show="!submitting">🚀 {{ __('import.submit') }}</span>
                    <span x-show="submitting" class="dropzone-submitting">
                        <span class="dropzone-spinner" aria-hidden="true"></span>
                        {{ __('import.submitting') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function importDropzone() {
        return {
            file: null,
            dragging: false,
            submitting: false,
            setFile(files) {
                this.file = files && files.length ? files[0] : null;
            },
            onDrop(e) {
                this.dragging = false;
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length) {
                    this.$refs.input.files = dt.files;
                    this.setFile(dt.files);
                }
            },
            reset() {
                this.file = null;
                this.$refs.input.value = '';
            },
            prettySize(bytes) {
                if (!bytes && bytes !== 0) return '';
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1024 / 1024).toFixed(2) + ' MB';
            },
        }
    }
</script>
@endsection
