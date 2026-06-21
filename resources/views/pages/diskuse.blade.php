@extends('layouts.app')
@section('title', __('pages.diskuse.title', ['round' => $kolo->nazev]))
@section('meta_description', __('pages.diskuse.meta', ['round' => $kolo->nazev]))

@section('jsonld')
    @include('partials.jsonld-kolo', ['kolo' => $kolo])
@endsection

@section('content')
@php $isAdmin = (bool) (auth()->user()?->is_admin); @endphp

<h1>{{ __('pages.diskuse.heading') }}</h1>

{{-- Výběr kola --}}
<div class="mb-5 flex flex-wrap items-end gap-3">
    <form method="get" action="{{ url('/diskuse') }}" class="field mb-0">
        <label class="label" for="kolo_sel">{{ __('pages.diskuse.filter_round') }}</label>
        <div class="flex items-center gap-2">
            <select id="kolo_sel" name="kolo" class="select w-auto" data-autosubmit>
                @foreach ($kola as $k)
                    <option value="{{ $k->id }}" @selected($k->id === $kolo->id)>
                        {{ $k->nazev }} ({{ $k->datum_konani?->format('j. n. Y') }})
                    </option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-ghost btn-sm">{{ __('pages.diskuse.btn_go') }}</button></noscript>
        </div>
    </form>
    @php
        $cnt = $prispevky->count();
        $locale = app()->getLocale();
        $postWord = $locale === 'cs'
            ? ($cnt === 1 ? __('pages.diskuse.post_count_1') : ($cnt < 5 ? __('pages.diskuse.post_count_few') : __('pages.diskuse.post_count_many')))
            : ($cnt === 1 ? __('pages.diskuse.post_count_1') : __('pages.diskuse.post_count_many'));
    @endphp
    <span class="pb-1 text-sm text-muted">{{ $cnt }} {{ $postWord }}</span>
</div>

{{-- success řeší centrální <x-flash /> v layoutu --}}

{{-- Příspěvky --}}
@if ($prispevky->isEmpty())
    <p class="text-muted mb-6">{{ __('pages.diskuse.no_posts') }}</p>
@else
    <div class="mb-8 flex flex-col gap-3">
        @foreach ($prispevky as $p)
            <div class="card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                        <span class="mono font-bold text-heading">{{ $p->znacka }}</span>
                        @if ($p->jmeno)
                            <span class="text-sm text-muted">{{ $p->jmeno }}</span>
                        @endif
                        <span class="text-xs text-muted">{{ $p->created_at?->format('j. n. Y H:i') }}</span>
                    </div>
                    @if ($isAdmin)
                        <form method="post" action="{{ route('diskuse.destroy', $p->id) }}" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger btn-sm"
                                    data-confirm-znacka="{{ $p->znacka }}"
                                    title="{{ __('pages.diskuse.btn_delete') }}">{{ __('pages.diskuse.btn_delete') }}</button>
                        </form>
                    @endif
                </div>
                <p class="mt-2 whitespace-pre-wrap break-words">{{ $p->text }}</p>
                @if ($p->fotky->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($p->fotky as $f)
                            <a href="{{ route('diskuse.foto', $f->id) }}"
                               class="block overflow-hidden rounded-lg shadow-sm"
                               data-lightbox
                               aria-label="{{ __('pages.diskuse.photo_open') }}">
                                <img src="{{ route('diskuse.foto.nahled', $f->id) }}"
                                     alt="Fotografie od {{ $p->znacka }}"
                                     loading="lazy"
                                     class="h-28 w-28 object-cover transition hover:opacity-90 sm:h-32 sm:w-32">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif

{{-- Formulář pro nový příspěvek --}}
<div class="card p-5">
    <h2>{{ __('pages.diskuse.add_post') }}</h2>
    <form method="post" action="{{ route('diskuse.store', $kolo->id) }}"
          enctype="multipart/form-data" class="mt-3 space-y-3">
        @csrf

        <div class="flex flex-wrap gap-3">
            <div class="field mb-0 min-w-36 flex-1">
                <label class="label" for="znacka">{{ __('pages.diskuse.field_callsign') }} *</label>
                <input id="znacka" name="znacka" type="text"
                       class="input @error('znacka') input-err @enderror"
                       value="{{ old('znacka') }}"
                       maxlength="20" required placeholder="{{ __('pages.diskuse.ph_callsign') }}"
                       style="text-transform:uppercase">
                @error('znacka')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="field mb-0 min-w-36 flex-1">
                <label class="label" for="jmeno">{{ __('pages.diskuse.field_name') }}</label>
                <input id="jmeno" name="jmeno" type="text"
                       class="input @error('jmeno') input-err @enderror"
                       value="{{ old('jmeno') }}"
                       maxlength="100" placeholder="{{ __('pages.diskuse.ph_name') }}">
                @error('jmeno')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="field mb-0">
            <label class="label" for="text">{{ __('pages.diskuse.field_text') }} *</label>
            <textarea id="text" name="text"
                      class="textarea @error('text') input-err @enderror"
                      style="min-height:5rem"
                      maxlength="2000" required
                      placeholder="{{ __('pages.diskuse.ph_text') }}">{{ old('text') }}</textarea>
            @error('text')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field mb-0">
            <label class="label" for="fotky">{{ __('pages.diskuse.field_photo') }}</label>
            <input id="fotky" name="fotky[]" type="file"
                   accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/heic,image/heif"
                   multiple
                   class="input @error('fotky') input-err @enderror @error('fotky.*') input-err @enderror"
                   data-foto-input data-max="{{ \App\Http\Requests\StorePrispevekRequest::MAX_FOTEK }}">
            <p class="mt-1 text-xs text-muted">{{ __('pages.diskuse.photo_hint') }}</p>
            <div id="foto-preview" class="mt-2 flex flex-wrap gap-2"></div>
            @error('fotky')
                <span class="field-error">{{ $message }}</span>
            @enderror
            @error('fotky.*')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('pages.diskuse.btn_submit') }}</button>
        </div>
    </form>
</div>

{{-- Delete confirmation modal --}}
@if ($isAdmin)
<div id="del-overlay" role="dialog" aria-modal="true" aria-labelledby="del-title"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/45 p-4">
    <div class="card w-full max-w-sm p-5">
        <h2 id="del-title" class="mb-2 text-base font-bold text-danger">{{ __('pages.diskuse.delete_title') }}</h2>
        <p id="del-msg" class="mb-4 text-sm text-ink"></p>
        <div class="flex justify-end gap-2">
            <button type="button" id="del-cancel" class="btn btn-ghost btn-sm">{{ __('pages.diskuse.btn_cancel') }}</button>
            <button type="button" id="del-confirm" class="btn btn-danger btn-sm">{{ __('pages.diskuse.btn_delete') }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script @cspNonce>
(function () {
    var overlay    = document.getElementById('del-overlay');
    var msgEl      = document.getElementById('del-msg');
    var confirmBtn = document.getElementById('del-confirm');
    var cancelBtn  = document.getElementById('del-cancel');
    var pending    = null;
    var confirmTpl = @js(__('pages.diskuse.delete_confirm', ['callsign' => ':callsign']));

    function confirmDelete(form, znacka) {
        pending = form;
        msgEl.textContent = confirmTpl.replace(':callsign', znacka);
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        confirmBtn.focus();
    }

    // Tlačítka mazání – dřív inline onclick, ten CSP s nonce neumožňuje.
    document.querySelectorAll('button[data-confirm-znacka]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            confirmDelete(btn.closest('form'), btn.getAttribute('data-confirm-znacka'));
        });
    });

    confirmBtn.addEventListener('click', function () {
        // Pozor: close() vynuluje `pending`, takže formulář si musíme uložit
        // do lokální proměnné ještě před zavřením modalu.
        var form = pending;
        close();
        if (form) { form.submit(); }
    });

    function close() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        pending = null;
    }

    cancelBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) close();
    });
}());
</script>
@endpush
@endif

{{-- Lightbox + náhled vybraných fotek (pro všechny návštěvníky) --}}
<div id="lb-overlay" role="dialog" aria-modal="true"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
    <img id="lb-img" src="" alt="" class="max-h-[90vh] max-w-full rounded-lg shadow-lg">
    <button type="button" id="lb-close" aria-label="{{ __('pages.diskuse.photo_close') }}"
            class="absolute right-4 top-4 rounded-full bg-white/90 px-3 py-1 text-lg font-bold text-ink">&times;</button>
</div>

@push('scripts')
<script @cspNonce>
(function () {
    // --- Lightbox ---
    var overlay = document.getElementById('lb-overlay');
    var lbImg   = document.getElementById('lb-img');
    var lbClose = document.getElementById('lb-close');

    function openLb(src) {
        lbImg.setAttribute('src', src);
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    }
    function closeLb() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        lbImg.setAttribute('src', '');
    }

    document.querySelectorAll('a[data-lightbox]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            openLb(a.getAttribute('href'));
        });
    });
    lbClose.addEventListener('click', closeLb);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeLb(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeLb();
    });

    // --- Náhled vybraných souborů ---
    var input   = document.querySelector('[data-foto-input]');
    var preview = document.getElementById('foto-preview');
    if (input && preview) {
        var max = parseInt(input.getAttribute('data-max'), 10) || 5;
        var tooMany = @js(__('pages.diskuse.photo_too_many', ['max' => ':max']));

        input.addEventListener('change', function () {
            preview.innerHTML = '';
            var files = Array.prototype.slice.call(input.files || []);

            if (files.length > max) {
                var warn = document.createElement('p');
                warn.className = 'field-error';
                warn.textContent = tooMany.replace(':max', String(max));
                preview.appendChild(warn);
                input.value = '';
                return;
            }

            files.forEach(function (file) {
                if (!file.type.indexOf || file.type.indexOf('image/') !== 0) { return; }
                var url = URL.createObjectURL(file);
                var img = document.createElement('img');
                img.src = url;
                img.className = 'h-20 w-20 rounded-lg object-cover shadow-sm';
                img.onload = function () { URL.revokeObjectURL(url); };
                preview.appendChild(img);
            });
        });
    }
}());
</script>
@endpush

@endsection
