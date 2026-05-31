#!/usr/bin/env bash
#
# cleanup_legacy.sh — odstraní legacy PHP4 soubory nahrazené Laravelem (Fáze 10).
#
# Spusť z kořene repozitáře PO ověření, že Laravel verze funguje:
#   bash tools/cleanup_legacy.sh
#
# Používá `git rm`, takže změny jsou v indexu – zkontroluj `git status` a teprve commitni.
set -euo pipefail

# Legacy vstupní bod a infrastruktura (nahrazeno routes/web.php + Bootstrap Laravelu)
LEGACY=(
  index.php
  connect.php
  connect_mysqli.php
  head.php
  menu.php
  mernuback.php
  bottom.php
  login.php
  logout.php

  # Hlášení / administrace (→ Controllery + Blade, Fáze 6)
  edit_hlaseni.php
  edit_deniky.php
  edit_kategorie.php
  edit_import.php
  edit_kola.php
  nova_kola.php
  import.php
  export.php
  show_edi.php
  read_edi.php

  # Výsledky / vyhodnocení (→ ScoringService, Fáze 7)
  vysledkova_listina.php
  rocni_vysledky.php
  vyhodnoceni.php
  uzavreni.php
  vysledky.php

  # Maily (→ Laravel Mail, Fáze 8)
  mail.php
  mail_qrp.php
  mail_red.php

  # Mapy (→ MapController + Leaflet, Fáze 9)
  map.php
  map2.php
  mapb.php
  mapb2.php
  mapc2.php
  mapd.php
  mape.php

  # QTH statistiky / testovací zbytky
  qthstat.php
  qthstst.php
  test.php
  I_0005319956.php
)

LEGACY_DIRS=(
  phpmailer
  leaflet
  maptest
  maptest2
  t
)

# Tajemství, která nikdy neměla být v repu (a musí se rotovat – viz Fáze 2)
SECRETS=(
  .env
  .idea/private_key
  mail.inc
)

echo "== Mažu legacy soubory =="
for f in "${LEGACY[@]}"; do
  [ -e "$f" ] && git rm -q --ignore-unmatch "$f" && echo "  - $f"
done

echo "== Mažu legacy adresáře =="
for d in "${LEGACY_DIRS[@]}"; do
  [ -d "$d" ] && git rm -rq --ignore-unmatch "$d" && echo "  - $d/"
done

echo "== Odstraňuji tajemství z gitu (soubory zůstanou na disku) =="
for s in "${SECRETS[@]}"; do
  git rm -q --cached --ignore-unmatch "$s" 2>/dev/null && echo "  - $s (untracked)"
done

echo ""
echo "Hotovo. Zkontroluj 'git status', spusť testy a teprve commitni."
echo "NEZAPOMEŇ rotovat hesla a privátní klíč (byly v historii)."
