#!/usr/bin/env bash
# Zad Saudi — canonical / noindex check over URLs with curl (read-only). Works on the live server or from any machine.
#   bash zad-canonical-check.sh                 → takes the URLs from WordPress (run in the WP root; needs wp-cli)
#   bash zad-canonical-check.sh urls.txt        → one URL per line (no WordPress needed)
# Types (WP mode): TYPES="page,pest_control,cleaning,moving,zad_hood,guide"   Parallel requests: JOBS=6
set -uo pipefail
TYPES="${TYPES:-page,pest_control,cleaning,moving,zad_hood,guide}"
JOBS="${JOBS:-6}"
OUT="${OUT:-zad-canonical-$(date +%Y%m%d-%H%M).csv}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
if [ "${1:-}" != "" ]; then cp "$1" "$TMP/urls.txt"; else
  : > "$TMP/urls.txt"
  for t in ${TYPES//,/ }; do
    wp post-type get "$t" >/dev/null 2>&1 || { echo "skip type: $t (not registered)" >&2; continue; }
    wp post list --post_type="$t" --post_status=publish --field=url >> "$TMP/urls.txt"
  done
fi
sort -u "$TMP/urls.txt" -o "$TMP/urls.txt"
echo "URLs: $(wc -l < "$TMP/urls.txt")" >&2

check() {
  url="$1"
  hdr="$(mktemp)"; body="$(mktemp)"
  code="$(curl -sS -m 30 -A 'ZadCanonicalCheck/1.0' -H 'Cache-Control: no-cache' -D "$hdr" -o "$body" -w '%{http_code}' "$url" 2>/dev/null || echo 000)"
  head="$(sed -e 's/<body.*//I' "$body" | tr '\n' ' ')"
  can_all="$(printf '%s' "$head" | grep -oiE '<link[^>]+rel=["'"'"']canonical["'"'"'][^>]*>' || true)"
  n=0; [ -n "$can_all" ] && n="$(printf '%s\n' "$can_all" | wc -l | tr -d ' ')"
  can="$(printf '%s\n' "$can_all" | head -1 | sed -E 's/.*href=["'"'"']([^"'"'"']*)["'"'"'].*/\1/')"
  rob="$(printf '%s' "$head" | grep -oiE '<meta[^>]+name=["'"'"']robots["'"'"'][^>]*>' | head -1 | sed -E 's/.*content=["'"'"']([^"'"'"']*)["'"'"'].*/\1/')"
  xr="$(grep -i '^x-robots-tag' "$hdr" | tr -d '\r' | head -1)"
  strip() { printf '%s' "$1" | sed -E 's#^https?://##; s#/$##' | tr 'A-Z' 'a-z'; }
  prob=""
  [ "$code" != "200" ] && prob="$prob HTTP$code;"
  [ "$n" != "1" ] && prob="$prob canonical_count=$n;"
  [ -n "$can" ] && [ "$(strip "$can")" != "$(strip "$url")" ] && prob="$prob canonical!=url;"
  printf '%s' "$rob$xr" | grep -qi noindex && prob="$prob noindex;"
  printf '%s%s' "$can" "$url" | grep -qi '\.local' && prob="$prob local-domain;"
  st=OK; [ -n "$prob" ] && st=PROBLEM
  printf '"%s","%s","%s","%s","%s","%s","%s"\n' "$st" "$url" "$code" "$n" "$can" "$rob" "$prob"
  rm -f "$hdr" "$body"
}
export -f check
echo '"status","url","http","canonical_count","canonical","robots","problems"' > "$OUT"
xargs -a "$TMP/urls.txt" -P "$JOBS" -I{} bash -c 'check "$@"' _ {} >> "$OUT"
echo "OK:      $(grep -c '^"OK"' "$OUT")"
echo "PROBLEM: $(grep -c '^"PROBLEM"' "$OUT")"
grep '^"PROBLEM"' "$OUT" | head -40
echo "CSV: $OUT"
