<h2>Potvrzení o přijetí hlášení – VKV provozní aktiv</h2>
<p>
  <strong>Kolo, kategorie:</strong> {{ $koloNazev }}, {{ $kategorieNazev }}<br>
  <strong>Volací znak:</strong> {{ $hlaseni->callsign }}<br>
  <strong>QRP:</strong> {{ $hlaseni->qrp ? 'ano' : 'ne' }}<br>
  <strong>Počet spojení:</strong> {{ $hlaseni->qso_count }}<br>
  <strong>Počet bodů za spojení:</strong> {{ $hlaseni->qso_points }}<br>
  <strong>Počet násobičů:</strong> {{ $hlaseni->multiplier }}<br>
  <strong>Počet bodů:</strong> {{ $hlaseni->points }}<br>
  <strong>Jméno:</strong> {{ $hlaseni->name }}<br>
  <strong>Kontakt:</strong> {{ $hlaseni->email }} {{ $hlaseni->phone }}<br>
  <strong>Poznámka:</strong> {{ $hlaseni->note }}<br>
  <strong>Soapbox:</strong> {{ $hlaseni->soapbox }}
</p>
<p>Po kontrole a převzetí hlášení vyhodnocovatelem budeš informován(a) e-mailem.</p>
<p>73 {{ config('vkvpa.contact_name') }}</p>
