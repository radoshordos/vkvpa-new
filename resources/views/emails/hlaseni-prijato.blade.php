<h2>Potvrzení o přijetí hlášení – VKV provozní aktiv</h2>
<p>
  <strong>Kolo, kategorie:</strong> {{ $koloNazev }}, {{ $kategorieNazev }}<br>
  <strong>Volací znak:</strong> {{ $hlaseni->znacka }}<br>
  <strong>QRP:</strong> {{ $hlaseni->qrp ? 'ano' : 'ne' }}<br>
  <strong>Počet spojení:</strong> {{ $hlaseni->pocet }}<br>
  <strong>Počet bodů za spojení:</strong> {{ $hlaseni->bodu_za_qso }}<br>
  <strong>Počet násobičů:</strong> {{ $hlaseni->nasobice }}<br>
  <strong>Počet bodů:</strong> {{ $hlaseni->body }}<br>
  <strong>Jméno:</strong> {{ $hlaseni->jmeno }}<br>
  <strong>Kontakt:</strong> {{ $hlaseni->mail }} {{ $hlaseni->telefon }}<br>
  <strong>Poznámka:</strong> {{ $hlaseni->poznamka }}<br>
  <strong>Soapbox:</strong> {{ $hlaseni->soapbox }}
</p>
<p>Po kontrole a převzetí hlášení vyhodnocovatelem budeš informován(a) e-mailem.</p>
<p>73 {{ config('vkvpa.contact_name') }}</p>
