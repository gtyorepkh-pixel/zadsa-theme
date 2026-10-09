#!/usr/bin/env bash
# Zad Saudi — verifies the /blog/ migration over HTTP (read-only).
#   bash zad-redirect-check.sh blog-plan-YYYYmmdd-HHMM.csv [https://zadksa.com]
# For every PUBLISHED post of the plan: the OLD url must answer 301 with Location = the new url, and the NEW url must answer 200.
# Also tests the three old archives (→ /blog/). Base url: 2nd argument, else `wp option get home`.
set -uo pipefail
PLAN="${1:?usage: bash zad-redirect-check.sh blog-plan-….csv [base-url]}"
BASE="${2:-$(wp option get home 2>/dev/null || true)}"; BASE="${BASE%/}"
[ -z "$BASE" ] && { echo "pass the site url as the 2nd argument (e.g. https://zadksa.com)"; exit 1; }
JOBS="${JOBS:-6}"; OUT="${OUT:-zad-redirect-check-$(date +%Y%m%d-%H%M).csv}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
# old<TAB>new for the rows that carry a 301 (the plan CSV: id,old_type,status,title,old_path,new_path,slug_renamed,redirect)
php -r '$f=fopen($argv[1],"r"); fgets($f,4); fseek($f,3); fgetcsv($f); while(($r=fgetcsv($f))!==false){ if(($r[7]??"")==="301") echo $r[4],"\t",$r[5],"\n"; }' "$PLAN" > "$TMP/pairs.tsv"
printf '/guide/\t/blog/\n/sections/\t/blog/\n/pests-library/\t/blog/\n' >> "$TMP/pairs.tsv"
echo "pairs: $(wc -l < "$TMP/pairs.tsv")  base: $BASE" >&2
enc() { php -r 'echo implode("/",array_map("rawurlencode",explode("/",$argv[1])));' "$1"; }
export -f enc; export BASE
check() {
  old="$1"; new="$2"
  ou="$BASE$(enc "$old")"; nu="$BASE$(enc "$new")"
  hdr="$(curl -sS -m 30 -o /dev/null -D - -A 'ZadRedirectCheck/1.0' "$ou" 2>/dev/null | tr -d '\r')"
  code="$(printf '%s' "$hdr" | head -1 | awk '{print $2}')"
  loc="$(printf '%s' "$hdr" | grep -i '^location:' | head -1 | sed 's/^[Ll]ocation: *//')"
  ncode="$(curl -sS -m 30 -o /dev/null -w '%{http_code}' -A 'ZadRedirectCheck/1.0' "$nu" 2>/dev/null || echo 000)"
  locn="$(php -r 'echo rtrim(rawurldecode(preg_replace("#^https?://[^/]+#","",$argv[1])),"/");' "$loc")"; want="$(printf '%s' "$new" | sed 's#/$##')"
  prob=""
  [ "$code" != "301" ] && prob="$prob old=HTTP$code;"
  [ "$code" = "301" ] && [ "$locn" != "$want" ] && prob="$prob location=$locn;"
  [ "$ncode" != "200" ] && { case "$new" in /blog/) [ "$ncode" = "200" ] || prob="$prob new=HTTP$ncode;";; *) prob="$prob new=HTTP$ncode;";; esac; }
  st=OK; [ -n "$prob" ] && st=PROBLEM
  printf '"%s","%s","%s","%s","%s","%s"\n' "$st" "$old" "$new" "$code" "$ncode" "$prob"
}
export -f check
echo '"status","old","new","old_http","new_http","problems"' > "$OUT"
while IFS=$'\t' read -r o n; do printf '%s\0%s\0' "$o" "$n"; done < "$TMP/pairs.tsv" | xargs -0 -n2 -P "$JOBS" bash -c 'check "$1" "$2"' _ >> "$OUT"
echo "OK:      $(grep -c '^"OK"' "$OUT")"; echo "PROBLEM: $(grep -c '^"PROBLEM"' "$OUT")"
grep '^"PROBLEM"' "$OUT" | head -30; echo "CSV: $OUT"
