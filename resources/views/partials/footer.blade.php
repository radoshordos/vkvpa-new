<footer class="mt-8 border-t border-line bg-surface">
  <div class="mx-auto max-w-6xl px-4 py-6 text-xs text-muted">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

      {{-- Sloupec 1: Kontakt a autorská práva --}}
      <div>
        <p class="flex flex-wrap items-center gap-1">
          {{ __('app.contact_evaluator') }}
          <img class="inline-block align-middle" src="{{ route('mail.image', ['text' => base64_encode(config('vkvpa.contact_mail'))]) }}" alt="">
        </p>
        <p class="mt-2 flex flex-wrap items-center gap-1">
          &copy; 2006 <a class="underline" href="http://polopate.jakpsatweb.cz/index.php?page=autor" title="Blanka Barvířová">OK1DMX</a>
          &copy; 2016 Petr OK1MAB
          <img class="inline-block align-middle" src="{{ route('mail.image', ['text' => base64_encode('ok1mab@hamradio.cz')]) }}" alt="">
        </p>
        <p class="mt-1">
          {{ __('app.hosted_by') }} <a class="underline" href="http://www.hamradio.cz">www.hamradio.cz</a>.
        </p>
      </div>

      {{-- Sloupec 2: Externí odkazy --}}
      <div>
        <p class="mb-2 font-medium text-ink">{{ __('app.useful_links') }}</p>
        <ul class="space-y-1">
          @foreach(config('navigation.footer') as $item)
            <li>
              <a class="underline hover:text-ink" href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}" rel="noopener noreferrer">
                {{ __($item['trans']) }}
              </a>
            </li>
          @endforeach
        </ul>
      </div>

    </div>
  </div>
</footer>
