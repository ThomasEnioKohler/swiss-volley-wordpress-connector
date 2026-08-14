# WordPress.org-Einreichung – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Das Plugin so umbauen, dass es im offiziellen WordPress.org-Plugin-Verzeichnis eingereicht, freigegeben und von dort aus aktualisiert werden kann.

**Architecture:** Vier voneinander unabhängige Umbauten auf einer bestehenden, funktionierenden Codebasis: (1) eine repo-weite mechanische Umbenennung wegen Guideline 17, (2) ein Wechsel der Quellsprache von Deutsch auf Englisch mit mitgelieferten deutschen Katalogen, (3) verzeichnis-konforme Metadaten und Assets, (4) ein SVN-Deploy-Schritt in der bestehenden Release-Pipeline. Es entsteht keine neue Funktionalität; die vorhandene Testsuite ist in allen Aufgaben das Sicherheitsnetz.

**Tech Stack:** PHP 8.1+, WordPress 6.2+, Bash, Python 3, GitHub Actions, SVN (über `10up/action-wordpress-plugin-deploy`), gettext (`msgfmt`), WP-CLI + `wp-env` für den Plugin Check.

## Global Constraints

* Plugin Name: `Volleyball Schedules for Swiss Volley`
* Slug / Ordner / Hauptdatei / Text Domain: `volleyball-schedules-for-swiss-volley`
* Prefixe: `VSSV_` (Klassen, Konstanten), `vssv_` (Funktionen, Options, Transients, Filter), `vssv-` (CSS-Klassen, Script-Handles, Dateinamen)
* Shortcode-Namen bleiben unverändert: `swissvolley_games`, `swissvolley_results`, `swissvolley_ranking`, `swissvolley_team`, `swissvolley_club_games`, `swissvolley_club_results`
* Version bleibt `1.0.0` an allen vier Fundstellen (Plugin-Header, Versionskonstante, `Stable tag`, erster CHANGELOG-Abschnitt)
* Quellsprache aller `__()`-Strings: Englisch, ASCII-only
* readme.txt: `Requires at least: 6.2`, `Tested up to: 7.0`, `Requires PHP: 8.1`, `Stable tag: 1.0.0`, maximal 5 Tags, Kurzbeschreibung ≤ 150 Zeichen
* Keine Logos oder Bildmarken von Swiss Volley in irgendeinem Asset
* `docs/superpowers/**` sind historische Dokumente und werden von keiner Umbenennung angefasst

---

### Task 1: Repo-weite Umbenennung

**Files:**
- Rename: `swiss-volley-connector/` → `volleyball-schedules-for-swiss-volley/`
- Rename: `volleyball-schedules-for-swiss-volley/swiss-volley-connector.php` → `volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php`
- Rename: `volleyball-schedules-for-swiss-volley/includes/class-svc-*.php` → `class-vssv-*.php` (10 Dateien)
- Rename: `volleyball-schedules-for-swiss-volley/languages/swiss-volley-connector.pot` → `volleyball-schedules-for-swiss-volley.pot`
- Modify: alle Dateien ausser `.git/`, `dist/`, `docs/superpowers/`
- Test: `tests/harness.php`, `tests/test-build.sh`, `tests/test-version-tools.sh` (bestehend, dienen als Gate)

**Interfaces:**
- Consumes: nichts
- Produces: die in den Global Constraints genannten Namen. Alle Folgeaufgaben referenzieren ausschliesslich die neuen Pfade und Prefixe.

Es entsteht kein neues Verhalten, deshalb kein neuer Test — die vorhandene Suite muss vor und nach der Umbenennung identisch grün sein.

- [ ] **Step 1: Ausgangszustand als grün belegen**

```bash
bash bin/build.sh
bash tests/test-version-tools.sh
bash tests/test-build.sh
```

Erwartet: alle drei mit Exit 0. Wenn nicht, hier stoppen — die Umbenennung darf nicht auf einem roten Baum starten.

- [ ] **Step 2: Verzeichnis und Dateien umbenennen**

```bash
git mv swiss-volley-connector volleyball-schedules-for-swiss-volley
git mv volleyball-schedules-for-swiss-volley/swiss-volley-connector.php \
       volleyball-schedules-for-swiss-volley/volleyball-schedules-for-swiss-volley.php
git mv volleyball-schedules-for-swiss-volley/languages/swiss-volley-connector.pot \
       volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley.pot
for f in volleyball-schedules-for-swiss-volley/includes/class-svc-*.php; do
  git mv "$f" "${f/class-svc-/class-vssv-}"
done
```

- [ ] **Step 3: Inhalte ersetzen**

Die Reihenfolge ist bindend: `Swiss Volley Connector` vor `swiss-volley-connector`, und die Prefixe zuletzt. Die Ausschlüsse verhindern, dass Git-Interna, gebaute ZIPs und historische Planungsdokumente angefasst werden.

```bash
files=$(git ls-files \
  | grep -v '^dist/' \
  | grep -v '^docs/superpowers/')

for f in $files; do
  sed -e 's/Swiss Volley Connector/Volleyball Schedules for Swiss Volley/g' \
      -e 's/swiss-volley-connector/volleyball-schedules-for-swiss-volley/g' \
      -e 's/SwissVolleyConnector/VolleyballSchedulesForSwissVolley/g' \
      -e 's/SVC_/VSSV_/g' \
      -e 's/svc_/vssv_/g' \
      -e 's/svc-/vssv-/g' \
      "$f" > "$f.tmp" && mv "$f.tmp" "$f"
done
```

`sed -i` wird bewusst nicht verwendet: BSD- und GNU-sed erwarten unterschiedliche Argumente, und das Projekt hält es in `bin/bump-version.sh` bereits so.

- [ ] **Step 4: Shortcodes gegenprüfen**

Die Shortcode-Namen dürfen die Umbenennung nicht mitgemacht haben.

```bash
grep -rn 'swissvolley_' volleyball-schedules-for-swiss-volley --include='*.php' | head
```

Erwartet: `swissvolley_games`, `swissvolley_results`, `swissvolley_ranking`, `swissvolley_team`, `swissvolley_club_games`, `swissvolley_club_results` unverändert vorhanden.

- [ ] **Step 5: Auf Rückstände prüfen**

```bash
grep -rniI 'svc\|swiss-volley-connector' . \
  --exclude-dir=.git --exclude-dir=dist --exclude-dir=superpowers
```

Erwartet: keine Ausgabe. Jeder Treffer ist ein übersehener Ort und muss von Hand korrigiert werden, bevor es weitergeht.

- [ ] **Step 6: Suite laufen lassen**

```bash
bash bin/build.sh
bash tests/test-version-tools.sh
bash tests/test-build.sh
```

Erwartet: alle drei Exit 0, und `bin/build.sh` legt `dist/volleyball-schedules-for-swiss-volley-1.0.0.zip` an.

- [ ] **Step 7: Altes ZIP-Verzeichnis aufräumen**

```bash
git rm --cached dist/swiss-volley-connector-*.zip 2>/dev/null || true
rm -f dist/swiss-volley-connector-*.zip
```

Falls die ZIPs nie eingecheckt waren (`.gitignore` prüfen), genügt das `rm`.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor: rename plugin to Volleyball Schedules for Swiss Volley

WordPress.org guideline 17 forbids a trademarked term as the first
part of a plugin slug, and the previous slug started with the Swiss
Volley trademark. The rename covers the slug, the folder, the main
file, the text domain, all class and function prefixes, the option
keys, the CSS classes and the whole build toolchain. Shortcode names
stay as they are: the guideline only governs the slug, and renaming
them would break every page using the plugin without any gain."
```

---

### Task 2: Quellsprache auf Englisch umstellen

**Files:**
- Modify: `volleyball-schedules-for-swiss-volley/includes/*.php` (alle `__()`-Aufrufe)
- Modify: `volleyball-schedules-for-swiss-volley/assets/js/blocks.js`
- Modify: `volleyball-schedules-for-swiss-volley/includes/class-vssv-plugin.php` (Textdomain-Zeitpunkt)
- Modify: `volleyball-schedules-for-swiss-volley/includes/class-vssv-blocks.php` (`wp_set_script_translations`)
- Create: `tests/test-i18n.sh`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: die Namen aus Task 1
- Produces: `languages/volleyball-schedules-for-swiss-volley.pot` mit ausschliesslich englischen, ASCII-only `msgid`-Einträgen. Task 3 baut die deutschen Kataloge gegen genau diese POT.

Zwei Fehler müssen dabei mitbehoben werden, weil sie beide vom Plugin Check gemeldet werden:

1. `load_plugin_textdomain()` läuft heute im Konstruktor, der an `plugins_loaded` hängt. Seit WordPress 6.7 gilt das als zu früh und löst `_doing_it_wrong` aus. Der Aufruf gehört an `init`.
2. Die Block-Editor-Strings in `blocks.js` werden nie übersetzt, weil `wp_set_script_translations()` fehlt. Solange die Quelle deutsch war, fiel das nicht auf; mit englischer Quelle sähe ein deutscher Redakteur englische Labels.

- [ ] **Step 1: Test schreiben, der die Quellsprache prüft**

Datei `tests/test-i18n.sh`:

```bash
#!/usr/bin/env bash
#
# Prüft die Quellsprache und die Vollständigkeit der Übersetzungen.
#
# Aufruf: bash tests/test-i18n.sh
# Voraussetzungen: bash, python3, msgfmt (gettext)

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/volleyball-schedules-for-swiss-volley"
POT="$PLUGIN/languages/volleyball-schedules-for-swiss-volley.pot"
PASS=0
FAIL=0

pass() { printf 'PASS  %s\n' "$1"; PASS=$((PASS + 1)); }
fail() { printf 'FAIL  %s\n      %s\n' "$1" "$2"; FAIL=$((FAIL + 1)); }

python3 "$ROOT/bin/make-pot.py" "$PLUGIN" > /dev/null

# Deutsche Quellstrings fallen über Umlaute und Eszett auf. Reine
# ASCII-Prüfung, damit auch typografische Zeichen auffliegen, die in
# msgid nichts zu suchen haben. Die Prüfung läuft über python3 statt
# grep -P: BSD-grep auf macOS kennt -P nicht.
test_pot_ist_ascii() {
	local treffer
	treffer="$(python3 - "$POT" <<'PY'
import sys
pfad = sys.argv[1]
for nr, zeile in enumerate(open(pfad, encoding='utf-8'), 1):
    if zeile.startswith('msgid ') and not zeile.isascii():
        print(f'{nr}: {zeile.rstrip()}')
PY
)"
	if [ -z "$treffer" ]; then
		pass "POT enthält ausschliesslich ASCII-msgid-Einträge"
	else
		fail "POT enthält ausschliesslich ASCII-msgid-Einträge" \
			"Nicht-ASCII in: $treffer"
	fi
}

# Ein falscher Textdomain-String faellt im Betrieb nicht auf: die
# Uebersetzung greift stumm nicht, der englische Quelltext erscheint.
# Geprueft wird deshalb, dass KEIN Uebersetzungsaufruf eine andere
# Domain verwendet als den Slug.
test_textdomain_konsistent() {
	local falsch
	falsch="$(grep -rnoE "(__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_html_x|esc_attr_x)\( *'[^']*' *(, *'[^']*' *)*, *'[^']+'" \
		"$PLUGIN" --include='*.php' --include='*.js' \
		| grep -v "'volleyball-schedules-for-swiss-volley'" || true)"
	if [ -z "$falsch" ]; then
		pass "alle Übersetzungsaufrufe verwenden die Text Domain des Slugs"
	else
		fail "alle Übersetzungsaufrufe verwenden die Text Domain des Slugs" "$falsch"
	fi
}

test_pot_ist_ascii
test_textdomain_konsistent

printf '\n%d bestanden, %d fehlgeschlagen\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
```

```bash
chmod +x tests/test-i18n.sh
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

```bash
bash tests/test-i18n.sh
```

Erwartet: FAIL bei „POT enthält ausschliesslich ASCII-msgid-Einträge", weil die msgids noch deutsch sind (`Spiele gruppieren`, `Nach Liga`, `– Team wählen –`, …).

- [ ] **Step 3: Übersetzungstabelle anlegen**

Die aktuelle POT ist die vollständige Liste der zu ersetzenden Strings. Erzeuge daraus eine TSV-Datei im Scratchpad (nicht im Repo — der dauerhafte Träger dieser Information wird in Task 3 die `de_CH.po`):

```bash
python3 - <<'PY' > /tmp/i18n-map.tsv
import re
pot = open('volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley.pot', encoding='utf-8').read()
for m in re.finditer(r'^msgid "(.+)"$', pot, re.M):
    print(m.group(1) + '\t')
PY
wc -l /tmp/i18n-map.tsv
```

Erwartet: rund 134 Zeilen. Fülle die zweite Spalte jeder Zeile mit der englischen Entsprechung. Regeln für die englischen Texte:

* ASCII-only. Kein `–`, kein `«»`, keine typografischen Anführungszeichen. `– Team wählen –` wird `- Select team -`.
* Platzhalter (`%s`, `%d`, `%1$s`) in Anzahl und Reihenfolge unverändert übernehmen.
* Fachbegriffe konsistent: `Spiel` → `game`, `Resultat` → `result`, `Rangliste` → `standings`, `Verein` → `club`, `Liga` → `league`, `Spielhalle` → `venue`, `Cache leeren` → `Clear cache`.
* Grossschreibung wie in der WordPress-Oberfläche üblich: Buttons und Überschriften in Sentence case.

- [ ] **Step 4: Tabelle auf die Quellen anwenden**

```bash
python3 - <<'PY'
import pathlib, csv, sys

mapping = {}
with open('/tmp/i18n-map.tsv', encoding='utf-8') as fh:
    for line in fh:
        de, _, en = line.rstrip('\n').partition('\t')
        if not en.strip():
            sys.exit(f'Unuebersetzt: {de}')
        mapping[de] = en

root = pathlib.Path('volleyball-schedules-for-swiss-volley')
targets = list(root.rglob('*.php')) + list(root.rglob('*.js'))
for path in targets:
    src = path.read_text(encoding='utf-8')
    out = src
    # Laengste zuerst: verhindert, dass ein kurzer String innerhalb
    # eines laengeren ersetzt wird.
    for de in sorted(mapping, key=len, reverse=True):
        out = out.replace(f"'{de}'", "'" + mapping[de] + "'")
    if out != src:
        path.write_text(out, encoding='utf-8')
        print(path)
PY
```

Das Skript bricht ab, sobald eine Zeile der Tabelle keine Übersetzung hat — eine halb übersetzte Oberfläche entsteht damit nicht.

- [ ] **Step 5: Textdomain-Ladezeitpunkt korrigieren**

In `includes/class-vssv-plugin.php` die Zeile aus dem Konstruktor entfernen:

```php
		load_plugin_textdomain( 'volleyball-schedules-for-swiss-volley', false, dirname( plugin_basename( VSSV_PLUGIN_FILE ) ) . '/languages' );
```

und stattdessen im Konstruktor registrieren:

```php
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
```

Neue Methode in derselben Klasse:

```php
	/**
	 * Übersetzungen laden.
	 *
	 * Muss an 'init' hängen: Seit WordPress 6.7 gilt ein früherer Aufruf
	 * als zu früh und löst _doing_it_wrong aus.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'volleyball-schedules-for-swiss-volley',
			false,
			dirname( plugin_basename( VSSV_PLUGIN_FILE ) ) . '/languages'
		);
	}
```

- [ ] **Step 6: Übersetzungen für das Block-Script registrieren**

In `includes/class-vssv-blocks.php` direkt nach dem `wp_register_script( 'vssv-blocks', … )`-Aufruf ergänzen:

```php
		wp_set_script_translations(
			'vssv-blocks',
			'volleyball-schedules-for-swiss-volley',
			VSSV_PLUGIN_DIR . 'languages'
		);
```

- [ ] **Step 7: Mock für den Test-Harness ergänzen**

`tests/harness.php` kennt `wp_set_script_translations` nicht; ohne Mock bricht die Suite mit einem Fatal Error ab. Neben den anderen Enqueue-Mocks einfügen:

```php
function wp_set_script_translations( $h, $d = null, $p = null ) {}
```

- [ ] **Step 8: POT neu erzeugen und Tests laufen lassen**

```bash
python3 bin/make-pot.py volleyball-schedules-for-swiss-volley
bash tests/test-i18n.sh
php -d error_reporting=E_ALL -d display_errors=1 tests/harness.php
bash bin/build.sh
```

Erwartet: `tests/test-i18n.sh` PASS, Harness PASS, Build Exit 0.

- [ ] **Step 9: POT von Hand durchlesen**

```bash
grep '^msgid' volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley.pot
```

Die ASCII-Prüfung fängt Umlaute, aber kein umlautfreies Deutsch (`Spiele`, `Rangliste`). Die Liste einmal ganz lesen und übersehene deutsche Strings nachziehen.

- [ ] **Step 10: i18n-Test in die CI aufnehmen**

In `.github/workflows/ci.yml`, Job `version-tools`, nach dem Schritt „Tests der Build-Pipeline":

```yaml
      - name: i18n-Prüfungen
        run: bash tests/test-i18n.sh
```

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "i18n: switch source strings to English

WordPress.org treats source strings as en_US and translate.wordpress.org
translates out of English, so German source strings would ship a German
UI worldwide and leave the translation system unusable. This also fixes
two issues the Plugin Check reports: load_plugin_textdomain ran on
plugins_loaded, which counts as too early since WordPress 6.7, and the
block editor script had no wp_set_script_translations call at all."
```

---

### Task 3: Deutsche Kataloge de_CH und de_DE

**Files:**
- Create: `volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-de_CH.po` / `.mo`
- Create: `volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-de_DE.po` / `.mo`
- Create: `volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-de_CH-vssv-blocks.json`
- Create: `volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-de_DE-vssv-blocks.json`
- Modify: `tests/test-i18n.sh`
- Modify: `bin/build.sh`

**Interfaces:**
- Consumes: die POT aus Task 2 und `/tmp/i18n-map.tsv` (Deutsch ↔ Englisch)
- Produces: vollständige Kataloge, die von `bin/build.sh` bei jedem Lauf auf Vollständigkeit geprüft werden

- [ ] **Step 1: Test auf Katalog-Vollständigkeit schreiben**

In `tests/test-i18n.sh` vor den Aufrufen am Dateiende ergänzen:

```bash
# Ein Katalog, der die POT nicht vollstaendig abdeckt, zeigt im Betrieb
# eine gemischtsprachige Oberflaeche - das faellt oft erst Nutzern auf.
test_katalog_vollstaendig() { # <locale>
	local locale="$1"
	local po="$PLUGIN/languages/volleyball-schedules-for-swiss-volley-$locale.po"
	local mo="${po%.po}.mo"
	local fehlend

	if [ ! -f "$po" ]; then
		fail "$locale ist vollständig übersetzt" "$po fehlt"
		return
	fi
	if [ ! -f "$mo" ]; then
		fail "$locale ist vollständig übersetzt" "$mo fehlt (msgfmt vergessen?)"
		return
	fi

	# msgcmp meldet sowohl fehlende als auch leere Eintraege - genau die
	# beiden Faelle, die im Betrieb eine gemischtsprachige Oberflaeche
	# ergeben.
	fehlend="$(msgcmp "$po" "$POT" 2>&1 || true)"
	if [ -z "$fehlend" ]; then
		pass "$locale ist vollständig übersetzt"
	else
		fail "$locale ist vollständig übersetzt" "$fehlend"
	fi
}
```

und die Aufrufe ergänzen:

```bash
test_katalog_vollstaendig de_CH
test_katalog_vollstaendig de_DE
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

```bash
bash tests/test-i18n.sh
```

Erwartet: zweimal FAIL mit „…de_CH.po fehlt" bzw. „…de_DE.po fehlt".

- [ ] **Step 3: de_CH aus der Tabelle erzeugen**

```bash
python3 - <<'PY'
import pathlib

pairs = []
with open('/tmp/i18n-map.tsv', encoding='utf-8') as fh:
    for line in fh:
        de, _, en = line.rstrip('\n').partition('\t')
        pairs.append((en, de))

lines = [
    'msgid ""',
    'msgstr ""',
    '"Project-Id-Version: Volleyball Schedules for Swiss Volley 1.0.0\\n"',
    '"MIME-Version: 1.0\\n"',
    '"Content-Type: text/plain; charset=UTF-8\\n"',
    '"Content-Transfer-Encoding: 8bit\\n"',
    '"Language: de_CH\\n"',
    '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
    '"X-Domain: volleyball-schedules-for-swiss-volley\\n"',
    '',
]
for en, de in sorted(pairs):
    lines.append(f'msgid "{en}"')
    lines.append(f'msgstr "{de}"')
    lines.append('')

dest = pathlib.Path('volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-de_CH.po')
dest.write_text('\n'.join(lines), encoding='utf-8')
print(f'{len(pairs)} Eintraege -> {dest}')
PY
```

- [ ] **Step 4: de_DE aus de_CH ableiten**

Der einzige Unterschied ist die Schreibweise des Eszett. Die Ersetzung greift nur in `msgstr`, damit die englischen `msgid` unangetastet bleiben.

```bash
python3 - <<'PY'
import pathlib, re

src = pathlib.Path('volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-de_CH.po')
text = src.read_text(encoding='utf-8')
text = text.replace('"Language: de_CH\\n"', '"Language: de_DE\\n"')

def swap(match):
    body = match.group(1)
    for ch_form, de_form in (('gross', 'groß'), ('Gross', 'Groß'), ('ausser', 'außer'),
                             ('Ausser', 'Außer'), ('schliess', 'schließ'), ('Schliess', 'Schließ'),
                             ('Strasse', 'Straße'), ('heisst', 'heißt'), ('weiss', 'weiß')):
        body = body.replace(ch_form, de_form)
    return f'msgstr "{body}"'

text = re.sub(r'msgstr "(.*)"', swap, text)
dest = pathlib.Path('volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-de_DE.po')
dest.write_text(text, encoding='utf-8')
print(dest)
PY
```

Anschliessend die erzeugte `de_DE.po` einmal durchlesen: Die Ersetzungsliste deckt die im Projekt vorkommenden Fälle ab, aber `ss`/`ß` ist nicht mechanisch entscheidbar. Fehlende Fälle von Hand nachziehen.

- [ ] **Step 5: .mo-Dateien erzeugen**

```bash
cd volleyball-schedules-for-swiss-volley/languages
msgfmt -o volleyball-schedules-for-swiss-volley-de_CH.mo volleyball-schedules-for-swiss-volley-de_CH.po
msgfmt -o volleyball-schedules-for-swiss-volley-de_DE.mo volleyball-schedules-for-swiss-volley-de_DE.po
cd -
```

- [ ] **Step 6: JSON-Kataloge für das Block-Script erzeugen**

`wp_set_script_translations()` liest nicht die `.mo`, sondern eine JSON-Datei pro Handle. Der Dateiname folgt dem Muster `<domain>-<locale>-<handle>.json`.

```bash
python3 - <<'PY'
import json, pathlib, re

js = pathlib.Path('volleyball-schedules-for-swiss-volley/assets/js/blocks.js').read_text(encoding='utf-8')
used = set(re.findall(r"__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'volleyball-schedules-for-swiss-volley'", js))

for locale in ('de_CH', 'de_DE'):
    po = pathlib.Path(f'volleyball-schedules-for-swiss-volley/languages/volleyball-schedules-for-swiss-volley-{locale}.po')
    text = po.read_text(encoding='utf-8')
    pairs = dict(re.findall(r'msgid "(.+)"\nmsgstr "(.*)"', text))
    messages = {'': {'domain': 'messages', 'lang': locale}}
    for msgid in sorted(used):
        messages[msgid] = [pairs.get(msgid, msgid)]
    dest = pathlib.Path(
        f'volleyball-schedules-for-swiss-volley/languages/'
        f'volleyball-schedules-for-swiss-volley-{locale}-vssv-blocks.json'
    )
    dest.write_text(json.dumps({
        'translation-revision-date': '2026-08-13',
        'generator': 'manual',
        'domain': 'messages',
        'locale_data': {'messages': messages},
    }, ensure_ascii=False, indent=1), encoding='utf-8')
    print(f'{len(used)} Strings -> {dest}')
PY
```

- [ ] **Step 7: .mo-Erzeugung in den Build aufnehmen**

In `bin/build.sh` nach dem POT-Schritt (`== 3/5 …`) einen Schritt ergänzen und die Nummerierung der folgenden Schritte auf `/6` anpassen:

```bash
echo "== 4/6 Übersetzungskataloge =="
for po in "$PLUGIN_DIR"/languages/*.po; do
	msgfmt -o "${po%.po}.mo" "$po"
	echo "OK  $(basename "${po%.po}.mo")"
done
```

- [ ] **Step 8: Tests laufen lassen**

```bash
bash tests/test-i18n.sh
bash bin/build.sh
bash tests/test-build.sh
```

Erwartet: alle PASS. `tests/test-build.sh` kopiert den Plugin-Baum in ein Temporärverzeichnis und baut dort — der neue `msgfmt`-Schritt muss dort ebenfalls durchlaufen.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "i18n: ship German catalogs for de_CH and de_DE

The plugin's users are Swiss clubs, so the German UI has to keep
working after the switch to English source strings. de_CH carries the
original wording, de_DE the same text with the Eszett spelling. The
block editor needs a JSON catalog per script handle rather than the
.mo file, so both locales get one."
```

---

### Task 4: CHANGELOG auf einen 1.0.0-Eintrag zusammenfassen

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `volleyball-schedules-for-swiss-volley/readme.txt` (nur der generierte Changelog-Abschnitt)

**Interfaces:**
- Consumes: nichts
- Produces: einen einzigen CHANGELOG-Abschnitt `## [1.0.0]`, aus dem `bin/sync-readme-changelog.sh` die readme-Sektion erzeugt. Task 5 schreibt den Kopfteil derselben Datei neu.

`bin/version.sh` liest den ersten `## [X.Y.Z]`-Abschnitt und vergleicht ihn mit Plugin-Header, Versionskonstante und `Stable tag`. Solange 1.0.0 der erste Abschnitt bleibt, bleibt die Prüfung grün.

- [ ] **Step 1: CHANGELOG.md neu schreiben**

Der Kopfteil der Datei (alles vor dem ersten `## [`) bleibt unverändert. Alle Versionsabschnitte werden durch einen einzigen ersetzt:

```markdown
## [1.0.0] - 2026-08-13

- Initial release.
- Shows upcoming games, results and official standings from the Swiss Volley API.
- Automatic club and team detection; nothing is hard-coded.
- Six shortcodes and four block editor blocks with team selection.
- Team aliases, custom team names, custom league labels and per-team links.
- Optional grouping of game lists by league or team, with an optional visitor-facing switcher.
- Caching through WordPress transients, including a fallback to the last known data when the API is unreachable.
- Server-side API access only: the API key never reaches the front end, and visitors send no requests to Swiss Volley.
- Optional debug mode with an API log that never contains secrets.
```

Die Einträge 0.1.0–0.1.7 beschreiben Entwicklungsschritte einer nie veröffentlichten Testphase; im Verzeichnis wären sie für Nutzer irreführend.

- [ ] **Step 2: readme-Abschnitt regenerieren**

```bash
bash bin/sync-readme-changelog.sh
```

- [ ] **Step 3: Prüfungen laufen lassen**

```bash
bash bin/version.sh
bash bin/sync-readme-changelog.sh --check
bash tests/test-version-tools.sh
```

Erwartet: `bin/version.sh` gibt `1.0.0` aus, der Sync meldet „readme.txt ist synchron mit CHANGELOG.md", die Werkzeug-Tests laufen grün.

- [ ] **Step 4: Commit**

```bash
git add CHANGELOG.md volleyball-schedules-for-swiss-volley/readme.txt
git commit -m "docs: collapse the changelog into a single 1.0.0 entry

The 0.1.x entries document a test phase that was never published, so
in the directory they would describe changes no user ever saw. English
matches the readme, which the directory renders as-is."
```

---

### Task 5: readme.txt für das Verzeichnis

**Files:**
- Modify: `volleyball-schedules-for-swiss-volley/readme.txt` (Kopfteil bis vor `== Changelog ==`)

**Interfaces:**
- Consumes: den Changelog-Abschnitt aus Task 4
- Produces: den Titel in Zeile 1, aus dem WordPress.org bei der Einreichung den endgültigen Slug ableitet

`bin/sync-readme-changelog.sh` überschreibt alles ab `== Changelog ==`. Alles davor wird hier von Hand geschrieben.

- [ ] **Step 1: Kopfteil neu schreiben**

Ersetze in `readme.txt` alles von Zeile 1 bis zur Zeile vor `== Changelog ==` durch:

```
=== Volleyball Schedules for Swiss Volley ===
Contributors: volleypizol
Tags: volleyball, sports, schedule, results, standings
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show volleyball schedules, results and official standings from the Swiss Volley API on your club website.

== Description ==

This plugin connects WordPress to the official Swiss Volley API (Volley Manager) and displays:

* Upcoming games with date, time, home team, away team and venue
* Results including set scores
* Official standings, taken from Swiss Volley rather than calculated
* Club-wide overviews across all your teams

When a fixture moves or a result is entered at Swiss Volley, the change appears on your site automatically. No manual upkeep.

The API is called server-side only and the responses are cached in WordPress transients. Your API key never appears in the front end, in JavaScript or in logs, and visitors never send requests to Swiss Volley.

This plugin is an independent project. It is not affiliated with, operated by, or endorsed by Swiss Volley.

== Installation ==

1. Install the plugin through **Plugins > Add New** and activate it.
2. In Volley Manager (https://volleymanager.volleyball.ch), go to **Administration > Club > Webservice/API** and create an API key.
3. In WordPress, go to **Swiss Volley > Settings**, enter the API key and save.
4. Click **Test API connection**. Your club is detected automatically.
5. On the **Teams** tab, click **Load club and teams**. All teams are fetched from Swiss Volley.
6. Optionally give each team an alias (for example `men-1`) and choose which teams appear in club-wide views.
7. Add shortcodes or blocks to your team pages.

== Shortcodes ==

* `[swissvolley_games team="TEAM_ID" limit="5" scope="upcoming"]` - upcoming games (scope: upcoming, played, all)
* `[swissvolley_results team="TEAM_ID" limit="5"]` - results
* `[swissvolley_ranking team="TEAM_ID"]` - standings
* `[swissvolley_team team="TEAM_ID" limit="5"]` - combined view of next games, latest results and standings
* `[swissvolley_club_games limit="10"]` - next games across all selected teams
* `[swissvolley_club_results limit="10"]` - latest results across all selected teams

The `team` attribute accepts the Swiss Volley team ID or the alias you assigned, for example `[swissvolley_team team="men-1"]`.

Full reference: https://github.com/ThomasEnioKohler/swiss-volley-wordpress-connector/blob/main/volleyball-schedules-for-swiss-volley/docs/SHORTCODES.md

== External services ==

This plugin relies on the official Swiss Volley API to display game data.

* Service: Swiss Volley API (Volley Manager), https://api.volleyball.ch
* What is sent: your club's API key, plus the team and league identifiers required for the requested view.
* When: whenever a page containing one of this plugin's shortcodes or blocks is rendered and the cached data has expired (default 30 minutes, configurable between 5 minutes and 24 hours).
* By whom: your web server. Requests are made server-side through the WordPress HTTP API. Visitors never contact Swiss Volley, so no visitor IP addresses or other personal data are transmitted.
* API documentation: https://swissvolley.docs.apiary.io/#reference/indoor
* Swiss Volley terms of use and privacy policy: https://www.volleyball.ch/de/footer/impressum/ and https://www.volleyball.ch/de/footer/datenschutz/

The plugin uses no tracking services, no CDNs, no advertising, and sends no telemetry.

== Frequently Asked Questions ==

= Where do I get an API key? =
In Volley Manager, under Administration > Club > Webservice/API. The key is bound to your club.

= Why can I not choose an arbitrary club? =
The Swiss Volley API only returns data for the club that owns the API key, so the club is detected from the data itself.

= How often is the data refreshed? =
According to the configured cache duration (default 30 minutes, adjustable from 5 minutes to 24 hours). Use "Clear cache now" to refresh immediately.

= What happens when Swiss Volley is unreachable? =
The site keeps working. If data was loaded successfully before, the last known state is shown with a notice; otherwise a neutral message appears. Visitors never see technical error messages.

= Can I change the styling? =
Yes. Every element carries a distinct class (`vssv-games`, `vssv-game`, `vssv-team`, `vssv-result`, `vssv-ranking`, `vssv-own-team`, `vssv-date`, `vssv-location` and others). Small adjustments can be made in the "Custom CSS" field. The plugin uses no !important rules.

= Which languages are available? =
English, plus German for Switzerland (de_CH) and Germany (de_DE). Further translations are welcome at translate.wordpress.org.

== Screenshots ==

1. Game list with results and standings on a team page
2. Settings screen with API key and cache duration
3. Teams screen with aliases, custom names and league labels
4. Block editor with the team selector

== Requirements ==

* WordPress 6.2 or newer
* PHP 8.1 or newer
* Outbound HTTPS connections to api.volleyball.ch
* A Swiss Volley API key from Volley Manager

```

Die GitHub-URL zeigt auf `ThomasEnioKohler/swiss-volley-wordpress-connector` (aktueller `origin`). Wird das GitHub-Repository später umbenannt, muss dieser Link mitgezogen werden.

- [ ] **Step 2: Kurzbeschreibung messen**

```bash
sed -n '11p' volleyball-schedules-for-swiss-volley/readme.txt | wc -c
```

Erwartet: höchstens 150. Bei mehr kürzen — das Verzeichnis schneidet sonst ab.

- [ ] **Step 3: Tags zählen**

```bash
grep '^Tags:' volleyball-schedules-for-swiss-volley/readme.txt | tr ',' '\n' | wc -l
```

Erwartet: 5. Mehr Tags werden vom Verzeichnis ignoriert.

- [ ] **Step 4: Prüfungen laufen lassen**

```bash
bash bin/version.sh
bash bin/sync-readme-changelog.sh --check
bash bin/build.sh
```

Erwartet: `Stable tag: 1.0.0` wird gefunden, der Sync bleibt grün (der Changelog-Abschnitt wurde nicht angefasst), der Build läuft durch.

- [ ] **Step 5: Commit**

```bash
git add volleyball-schedules-for-swiss-volley/readme.txt
git commit -m "docs: rewrite readme.txt for the plugin directory

The directory renders readme.txt verbatim, so it has to be English and
carry the fields the directory reads. The External services section is
mandatory for plugins calling a third-party API and is one of the most
common reasons for rejection when missing. Links into docs/ became
absolute: the build excludes docs/ from the ZIP, so relative links
would be dead for anyone installing from the directory."
```

---

### Task 6: Plugin Check und PHPCS

**Files:**
- Create: `phpcs.xml.dist`
- Modify: `.github/workflows/ci.yml`
- Modify: Plugin-Dateien nach Befund

**Interfaces:**
- Consumes: das Build-ZIP aus `bin/build.sh`
- Produces: einen Baum, den das offizielle Plugin-Check-Plugin ohne Errors durchlässt

Das Plugin-Check-Plugin bildet die automatisierte Prüfung bei der Einreichung ab. Was es als Error meldet, blockiert den Upload.

- [ ] **Step 1: PHPCS-Konfiguration anlegen**

Datei `phpcs.xml.dist`:

```xml
<?xml version="1.0"?>
<ruleset name="Volleyball Schedules for Swiss Volley">
	<description>WordPress coding standards for the plugin sources.</description>

	<file>volleyball-schedules-for-swiss-volley</file>

	<arg name="extensions" value="php"/>
	<arg name="colors"/>
	<arg value="sp"/>

	<rule ref="WordPress"/>

	<config name="minimum_supported_wp_version" value="6.2"/>
	<config name="testVersion" value="8.1-"/>

	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array" value="volleyball-schedules-for-swiss-volley"/>
		</properties>
	</rule>
</ruleset>
```

- [ ] **Step 2: PHPCS lokal laufen lassen**

```bash
composer global require --dev wp-coding-standards/wpcs:^3 dealerdirect/phpcodesniffer-composer-installer
~/.composer/vendor/bin/phpcs --standard=phpcs.xml.dist
```

Erwartet: eine Liste an Befunden. Errors abarbeiten, Warnings einzeln bewerten. Häufigste Befunde in diesem Code: fehlende `wp_unslash()`-Aufrufe, Escaping direkt vor der Ausgabe, Yoda-Bedingungen.

- [ ] **Step 3: Array-Inputs im Admin prüfen**

In `includes/class-vssv-admin.php` die fünf Array-Inputs (`alias`, `in_club`, `league_label`, `name_label`, `page_url`) durchgehen. Für jeden gilt: Der `phpcs:ignore`-Kommentar behauptet, der Wert werde weiter unten pro Eintrag sanitisiert. Diese Behauptung für jeden der fünf verifizieren und, wo sie nicht stimmt, die Sanitisierung ergänzen statt den Kommentar anzupassen.

Erwartete Sanitisierung je Feld: `alias` → `sanitize_key()`, `in_club` → nur der Schlüssel wird ausgewertet, Wert verworfen, `league_label` und `name_label` → `sanitize_text_field()`, `page_url` → `esc_url_raw()`.

- [ ] **Step 4: Plugin Check ausführen**

Voraussetzung ist eine lokale WordPress-Instanz. Mit `wp-env` (Docker erforderlich):

```bash
npx @wordpress/env start
npx @wordpress/env run cli wp plugin install plugin-check --activate
npx @wordpress/env run cli wp plugin check volleyball-schedules-for-swiss-volley
```

Erwartet: keine Einträge mit Typ `ERROR`. Jeder Error muss behoben werden. Warnings dokumentieren, wenn sie bewusst bleiben.

- [ ] **Step 5: PHPCS in die CI aufnehmen**

In `.github/workflows/ci.yml` einen eigenen Job ergänzen:

```yaml
  coding-standards:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: PHP einrichten
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: cs2pr, composer

      - name: WordPress Coding Standards installieren
        run: composer global require --dev wp-coding-standards/wpcs:^3 dealerdirect/phpcodesniffer-composer-installer

      - name: PHPCS
        run: ~/.composer/vendor/bin/phpcs --standard=phpcs.xml.dist --report=full
```

- [ ] **Step 6: Alles laufen lassen**

```bash
~/.composer/vendor/bin/phpcs --standard=phpcs.xml.dist
php -d error_reporting=E_ALL -d display_errors=1 tests/harness.php
bash bin/build.sh
```

Erwartet: PHPCS ohne Errors, Harness grün, Build Exit 0.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "build: add WordPress coding standards and plugin check

The directory runs an automated plugin check on submission, and its
errors block the upload. Running PHPCS with the same ruleset in CI
catches those findings before they reach the review queue."
```

---

### Task 7: Verzeichnis-Assets

**Files:**
- Create: `.wordpress-org/icon-128x128.png`
- Create: `.wordpress-org/icon-256x256.png`
- Create: `.wordpress-org/banner-772x250.png`
- Create: `.wordpress-org/banner-1544x500.png`
- Create: `.wordpress-org/screenshot-1.png` … `screenshot-4.png`

**Interfaces:**
- Consumes: die Screenshot-Liste aus der readme.txt (Task 5) — vier Einträge, die Reihenfolge muss übereinstimmen
- Produces: das Verzeichnis, das Task 8 nach SVN `assets/` spiegelt

Die Dateinamen sind fix vorgegeben; ein abweichender Name wird vom Verzeichnis stillschweigend ignoriert. Keine Logos oder Bildmarken von Swiss Volley — die Nennung im Text ist zulässig, die Übernahme von Bildmarken nicht.

- [ ] **Step 1: Screenshots aufnehmen**

Vier Aufnahmen in genau der Reihenfolge der readme-Liste:

1. Spielliste mit Resultaten und Rangliste auf einer Teamseite (Frontend)
2. Einstellungsseite mit API-Key-Feld und Cache-Dauer
3. Teams-Seite mit Alias, eigenem Namen und Liga-Bezeichnung
4. Block-Editor mit dem Team-Auswahlfeld

Der API-Key muss in Screenshot 2 unkenntlich sein.

- [ ] **Step 2: Icon und Banner gestalten**

Vier Dateien in den exakten Massen. Inhaltlich genügen Volleyball-Motiv und Plugin-Name; das Icon wird im Verzeichnis auch sehr klein dargestellt, deshalb keine feine Typografie.

- [ ] **Step 3: Masse verifizieren**

```bash
for f in .wordpress-org/*.png; do
  printf '%s ' "$f"
  sips -g pixelWidth -g pixelHeight "$f" | tr -d '\n' | sed 's/.*pixelWidth: \([0-9]*\).*pixelHeight: \([0-9]*\)/\1x\2/'
  echo
done
```

Erwartet: `icon-128x128.png` = 128x128, `icon-256x256.png` = 256x256, `banner-772x250.png` = 772x250, `banner-1544x500.png` = 1544x500.

- [ ] **Step 4: Grössen prüfen**

```bash
du -h .wordpress-org/*
```

Erwartet: Icons unter 1 MB, Banner unter 4 MB, Screenshots unter 10 MB.

- [ ] **Step 5: Commit**

```bash
git add .wordpress-org
git commit -m "assets: add directory icon, banner and screenshots

The directory only picks up these exact filenames and dimensions.
Screenshot order matches the list in readme.txt. All artwork is
original; no Swiss Volley logos or marks are used."
```

---

### Task 8: Build-Verzeichnis und SVN-Deploy

**Files:**
- Modify: `bin/build.sh`
- Modify: `tests/test-build.sh`
- Modify: `.github/workflows/release.yml`

**Interfaces:**
- Consumes: `.wordpress-org/` aus Task 7
- Produces: `dist/build/volleyball-schedules-for-swiss-volley/` — das Verzeichnis, das die Deploy-Action als `BUILD_DIR` nach SVN `trunk/` und `tags/<version>/` spiegelt

- [ ] **Step 1: Test für das Build-Verzeichnis schreiben**

In `tests/test-build.sh` vor den Aufrufen am Dateiende ergänzen:

```bash
# Das Build-Verzeichnis geht 1:1 nach SVN. Entwickler-Dateien darin
# landen sonst in der oeffentlichen Installation.
test_build_verzeichnis_ist_sauber() {
	local dir build
	dir="$(fixture)"

	if ! bash "$dir/bin/build.sh" > "$dir/build.log" 2>&1; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"$(tail -20 "$dir/build.log")"
		return
	fi

	build="$dir/dist/build/volleyball-schedules-for-swiss-volley"
	if [ ! -f "$build/volleyball-schedules-for-swiss-volley.php" ]; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"Hauptdatei fehlt in $build"
		return
	fi
	if [ -d "$build/docs" ] || [ -f "$build/README.md" ]; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"docs/ oder README.md im Build-Verzeichnis"
		return
	fi
	if ! ls "$build"/languages/*.mo > /dev/null 2>&1; then
		fail "build.sh erzeugt ein sauberes Build-Verzeichnis" \
			"keine .mo-Dateien im Build-Verzeichnis"
		return
	fi

	pass "build.sh erzeugt ein sauberes Build-Verzeichnis"
}
```

und den Aufruf ergänzen:

```bash
test_build_verzeichnis_ist_sauber
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

```bash
bash tests/test-build.sh
```

Erwartet: FAIL mit „Hauptdatei fehlt in …/dist/build/volleyball-schedules-for-swiss-volley".

- [ ] **Step 3: Build-Verzeichnis in `bin/build.sh` erzeugen**

Vor dem ZIP-Schritt einfügen und die Schrittnummerierung anpassen:

```bash
echo "== 5/7 Build-Verzeichnis =="
BUILD_DIR="$DIST_DIR/build/volleyball-schedules-for-swiss-volley"
rm -rf "$DIST_DIR/build"
mkdir -p "$BUILD_DIR"
( cd "$PLUGIN_DIR" && tar -cf - \
	--exclude='.DS_Store' \
	--exclude='README.md' \
	--exclude='docs' \
	--exclude='*.po' \
	. ) | ( cd "$BUILD_DIR" && tar -xf - )
echo "OK  $BUILD_DIR"
```

Die `.po`-Dateien bleiben draussen: WordPress liest zur Laufzeit nur die `.mo` und die JSON-Kataloge, die Quelldateien blähen die Installation nur auf. Im Repository bleiben sie natürlich.

- [ ] **Step 4: Tests laufen lassen**

```bash
bash bin/build.sh
bash tests/test-build.sh
```

Erwartet: beide grün, `dist/build/volleyball-schedules-for-swiss-volley/` existiert mit Hauptdatei und `.mo`-Dateien, ohne `docs/`, `README.md` und `.po`.

- [ ] **Step 5: Deploy-Schritt in `release.yml` ergänzen**

Nach dem Schritt „Release veröffentlichen":

```yaml
      # Laeuft nur, wenn die SVN-Zugangsdaten hinterlegt sind. Vor der
      # Freigabe durch das Plugin-Team existiert kein SVN-Repository,
      # der Schritt wird dann uebersprungen statt fehlzuschlagen.
      - name: WordPress.org Deploy
        if: ${{ env.SVN_USERNAME != '' }}
        uses: 10up/action-wordpress-plugin-deploy@stable
        env:
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          SLUG: volleyball-schedules-for-swiss-volley
          BUILD_DIR: dist/build/volleyball-schedules-for-swiss-volley
          ASSETS_DIR: .wordpress-org
          VERSION: ${{ env.VERSION }}
```

- [ ] **Step 6: Workflow-Syntax prüfen**

```bash
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/release.yml')); print('YAML OK')"
```

Erwartet: `YAML OK`.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "build: produce a deployable build directory and wire up SVN

The deploy action mirrors a directory into SVN trunk and tags, so the
build now emits one that contains only what belongs in an installed
plugin. The step is skipped while no SVN credentials are configured,
which is the case until the plugin team approves the submission."
```

---

### Task 9: Einreichung

**Files:** keine — dieser Schritt läuft ausserhalb des Repositories.

**Interfaces:**
- Consumes: das ZIP aus `bin/build.sh` sowie den freigegebenen SVN-Zugang
- Produces: das öffentliche Verzeichnis-Listing

- [ ] **Step 1: WordPress.org-Account sicherstellen**

Account unter https://login.wordpress.org/register anlegen oder bestätigen. Der Name muss exakt dem Wert in `Contributors:` der readme.txt entsprechen, sonst erscheint das Plugin nicht im Profil.

```bash
grep '^Contributors:' volleyball-schedules-for-swiss-volley/readme.txt
```

Prüfen, dass `https://profiles.wordpress.org/<username>/` existiert.

- [ ] **Step 2: Finales ZIP bauen**

```bash
bash bin/build.sh
ls -lh dist/volleyball-schedules-for-swiss-volley-1.0.0.zip
```

Erwartet: Datei vorhanden, deutlich unter 10 MB.

- [ ] **Step 3: ZIP-Inhalt sichten**

```bash
unzip -l dist/volleyball-schedules-for-swiss-volley-1.0.0.zip
```

Erwartet: ausschliesslich Dateien unterhalb von `volleyball-schedules-for-swiss-volley/`, darin die Hauptdatei, `readme.txt`, `uninstall.php`, `includes/`, `assets/`, `languages/` mit `.pot`, `.mo` und den JSON-Katalogen. Keine `docs/`, kein `README.md`, keine `.po`, keine `.DS_Store`.

- [ ] **Step 4: Einreichen**

Upload auf https://wordpress.org/plugins/developers/add/. Der automatisierte Plugin Check läuft sofort; gemeldete Fehler blockieren die Einreichung und müssen in Task 6 nachgezogen werden.

- [ ] **Step 5: Review begleiten**

Rückfragen kommen per E-Mail von `plugins@wordpress.org` an die Adresse des Accounts. Sie müssen beantwortet werden; unbeantwortete Rückfragen schliessen die Einreichung. Erfahrungsgemäss vergehen mehrere Wochen. Beim Thema Markennutzung ist die Argumentation: Der Slug beginnt mit dem eigenen beschreibenden Namen, „Swiss Volley" steht als Suffix und bezeichnet den angebundenen Dienst; die readme.txt weist ausdrücklich auf die fehlende Verbindung zu Swiss Volley hin.

- [ ] **Step 6: Erstes Deployment**

Nach der Freigabe stellt WordPress.org ein SVN-Repository bereit. Die Zugangsdaten als GitHub-Secrets hinterlegen:

```bash
gh secret set SVN_USERNAME
gh secret set SVN_PASSWORD
```

Dann den Tag setzen; ab hier übernimmt der Schritt aus Task 8:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Das Plugin wird erst durch diesen ersten SVN-Commit öffentlich sichtbar.

- [ ] **Step 7: Ergebnis prüfen**

```bash
open https://wordpress.org/plugins/volleyball-schedules-for-swiss-volley/
```

Erwartet: Listing mit Banner, Icon, Screenshots und der Beschreibung aus der readme.txt. Assets werden über ein CDN ausgeliefert und können bis zu sechs Stunden brauchen, bis sie erscheinen.
