{{--
    Potvrzovací modal pro mazání záznamu hlášení. Spouští se tlačítky
    s atributem data-del-znacka (viz partials/zaznam-akce). Skript se
    registruje přes @push('scripts') – include patří dovnitř @section.
--}}
<div id="del-overlay" role="dialog" aria-modal="true" aria-labelledby="del-modal-title"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/45 p-4">
    <div class="card w-full max-w-sm p-5">
        <h2 id="del-modal-title" class="mb-2 text-base font-bold text-danger">{{ __('pages.vysledky.delete_title') }}</h2>
        <p id="del-modal-msg" class="mb-4 text-sm text-ink"></p>
        <div class="flex justify-end gap-2">
            <button type="button" id="del-cancel" class="btn btn-ghost btn-sm">{{ __('pages.vysledky.btn_cancel') }}</button>
            <button type="button" id="del-confirm" class="btn btn-danger btn-sm">{{ __('pages.vysledky.btn_delete') }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script @cspNonce>
(function () {
    var overlay    = document.getElementById('del-overlay');
    var msgEl      = document.getElementById('del-modal-msg');
    var confirmBtn = document.getElementById('del-confirm');
    var cancelBtn  = document.getElementById('del-cancel');
    var pending    = null;
    var confirmTpl = @js(__('pages.vysledky.delete_confirm', ['callsign' => ':callsign']));

    function openDelModal(btn, znacka) {
        pending = btn.closest('form');
        msgEl.textContent = confirmTpl.replace(':callsign', znacka);
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        confirmBtn.focus();
    }

    // Tlačítka mazání – dřív inline onclick, ten CSP s nonce neumožňuje.
    document.querySelectorAll('button[data-del-znacka]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openDelModal(btn, btn.getAttribute('data-del-znacka'));
        });
    });

    confirmBtn.addEventListener('click', function () {
        close();
        if (pending) { pending.submit(); }
    });

    function close() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        pending = null;
    }

    cancelBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ! overlay.classList.contains('hidden')) close();
    });
}());
</script>
@endpush
