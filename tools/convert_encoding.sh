#!/usr/bin/env bash
#
# convert_encoding.sh — převede legacy soubory z Windows-1250 na UTF-8.
#
# Fáze 2 migrace (bod S6). Spusť z kořene repozitáře:
#   bash tools/convert_encoding.sh
#
# - Vytvoří zálohu *.bak vedle každého převedeného souboru.
# - Je idempotentní: soubory, které už jsou validní UTF-8, přeskočí.
# - Po kontrole zálohy smaž:  find . -name '*.bak' -delete
#
set -euo pipefail

# Soubory detekované jako ne-UTF-8 (Windows-1250). connect.php se NEpřevádí –
# je nahrazen novou env-based verzí.
FILES=(
  "edit_import.php"
  "export.php"
  "import.php"
  "login.php"
)

converted=0
for f in "${FILES[@]}"; do
  if [[ ! -f "$f" ]]; then
    echo "PŘESKOČENO (chybí): $f"
    continue
  fi
  if iconv -f UTF-8 -t UTF-8 "$f" >/dev/null 2>&1; then
    echo "OK (už UTF-8):     $f"
    continue
  fi
  cp -p "$f" "$f.bak"
  iconv -f WINDOWS-1250 -t UTF-8 "$f.bak" > "$f"
  echo "PŘEVEDENO:         $f  (záloha: $f.bak)"
  converted=$((converted + 1))
done

echo "----"
echo "Převedeno souborů: $converted"
echo "Ověř: for f in ${FILES[*]}; do iconv -f UTF-8 -t UTF-8 \"\$f\" >/dev/null && echo \"\$f OK\"; done"
