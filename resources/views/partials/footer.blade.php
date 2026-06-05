<footer class="mt-8 border-t border-line bg-surface">
  <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-muted">
    <p class="flex flex-wrap items-center gap-1">
      Kontakt na vyhodnocovatele: Míla OK1VUM,
      <img class="inline-block align-middle" src="{{ route('mail.image', ['text' => base64_encode(config('vkvpa.contact_mail'))]) }}" alt="">
    </p>
    <p class="mt-2 flex flex-wrap items-center gap-1 sm:justify-end">
      &copy; 2006 <a class="underline" href="http://polopate.jakpsatweb.cz/index.php?page=autor" title="Blanka Barvířová">OK1DMX</a>
      &copy; 2016 Petr OK1MAB
      <img class="inline-block align-middle" src="{{ route('mail.image', ['text' => base64_encode('ok1mab@hamradio.cz')]) }}" alt="">
    </p>
    <p class="mt-1 sm:text-right">
      Tato aplikace je hostována na serveru <a class="underline" href="http://www.hamradio.cz">www.hamradio.cz</a>.
    </p>
  </div>
</footer>
