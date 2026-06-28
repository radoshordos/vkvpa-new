<h2>Oznámení o přijetí hlášení:</h2>
<p style="font-weight: bold">
  {{ $hlaseni->callsign }} {{ $hlaseni->qso_count }} {{ $hlaseni->qso_points }}
  {{ $hlaseni->multiplier }} {{ $hlaseni->points }} {{ $kategorieNazev }} {{ $hlaseni->note }}<br>
  {{ $hlaseni->soapbox }}
</p>
<p>
  <a href="{{ $prevzitUrl }}">převzít tento záznam</a><br>
  <em>(po kliknutí se zobrazí administrace přihlášená na jméno administrátora s formulářem
  pro převzetí tohoto hlášení; odkaz lze použít jen jednou a to do
  {{ config('vkvpa.token_ttl_days') }} dnů od obdržení – pak je třeba převzít záznam ručně)</em>
</p>
<p>73 de GSVZ (Geniální Systém Vyhodnocování Závodů)</p>
