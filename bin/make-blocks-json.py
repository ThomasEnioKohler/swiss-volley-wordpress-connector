#!/usr/bin/env python3
"""Erzeugt languages/<slug>-<locale>-vssv-blocks.json aus assets/js/blocks.js
und den vorhandenen .po-Katalogen.

wp_set_script_translations() erwartet für den Editor-Skript-Handle
'vssv-blocks' eine JSON-Datei nach diesem Namensschema. Ohne diesen
Generator wurden die Kataloge bislang per Hand gepflegt: eine neue,
übersetzte Zeichenkette in blocks.js landete nicht automatisch darin, und
kein Test hätte das gemerkt – der Block-Editor wäre für deutschsprachige
Redakteure stumm auf Englisch zurückgefallen.

Aufruf: bin/make-blocks-json.py <plugin-verzeichnis>
"""
import datetime
import glob
import json
import os
import re
import sys

DOMAIN = "volleyball-schedules-for-swiss-volley"
HANDLE = "vssv-blocks"

STRING_PAT = re.compile(
    r"__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'" + re.escape(DOMAIN) + r"'\s*\)"
)


def unescape(text: str) -> str:
    """Löst die in .po/.js üblichen Escapes (\\n, \\t, \\", \\\\) auf."""
    out = []
    i = 0
    while i < len(text):
        ch = text[i]
        if ch == "\\" and i + 1 < len(text):
            nxt = text[i + 1]
            out.append({"n": "\n", "t": "\t", '"': '"', "\\": "\\"}.get(nxt, nxt))
            i += 2
        else:
            out.append(ch)
            i += 1
    return "".join(out)


def quoted_value(fragment: str) -> str:
    fragment = fragment.strip()
    if len(fragment) >= 2 and fragment.startswith('"') and fragment.endswith('"'):
        return fragment[1:-1]
    return ""


def parse_po(path: str) -> dict:
    """Minimaler .po-Parser für einfache (kontextfreie, nicht-plurale) Einträge.

    blocks.js verwendet ausschliesslich wp.i18n.__() ohne Kontext, daher
    reicht ein msgid -> msgstr-Mapping; msgctxt- und Plural-Einträge werden
    bewusst übersprungen.
    """
    entries: dict = {}
    msgid_parts: list = []
    msgstr_parts: list = []
    mode = None  # None | 'msgid' | 'msgstr' | 'skip'

    def commit() -> None:
        if mode in ( 'msgid', 'msgstr' ) and msgid_parts:
            msgid = unescape("".join(msgid_parts))
            msgstr = unescape("".join(msgstr_parts))
            if msgid and msgstr:
                entries[msgid] = msgstr

    with open(path, encoding="utf-8") as handle:
        for raw in handle:
            line = raw.strip()
            if not line:
                commit()
                msgid_parts, msgstr_parts, mode = [], [], None
                continue
            if line.startswith("#"):
                continue
            if line.startswith("msgctxt ") or line.startswith("msgid_plural ") or line.startswith("msgstr["):
                mode = "skip"
                continue
            if line.startswith("msgid "):
                commit()
                msgid_parts = [quoted_value(line[len("msgid "):])]
                msgstr_parts = []
                mode = "msgid"
                continue
            if line.startswith("msgstr "):
                msgstr_parts = [quoted_value(line[len("msgstr "):])]
                mode = "msgstr"
                continue
            if line.startswith('"'):
                if mode == "msgid":
                    msgid_parts.append(quoted_value(line))
                elif mode == "msgstr":
                    msgstr_parts.append(quoted_value(line))
                continue
        commit()

    return entries


def main() -> None:
    plugin_dir = sys.argv[1] if len(sys.argv) > 1 else "volleyball-schedules-for-swiss-volley"
    slug = os.path.basename(plugin_dir.rstrip("/"))
    languages_dir = os.path.join(plugin_dir, "languages")

    js_path = os.path.join(plugin_dir, "assets", "js", "blocks.js")
    src = open(js_path, encoding="utf-8").read()
    strings = sorted({unescape(m.group(1)) for m in STRING_PAT.finditer(src)})

    po_files = sorted(glob.glob(os.path.join(languages_dir, f"{slug}-*.po")))
    if not po_files:
        print("Keine .po-Dateien gefunden, keine JSON-Sprachkataloge erzeugt.")
        return

    today = datetime.date.today().isoformat()

    for po_path in po_files:
        base = os.path.basename(po_path)[: -len(".po")]
        locale = base[len(slug) + 1 :]
        translations = parse_po(po_path)

        messages = {"": {"domain": "messages", "lang": locale}}
        for msgid in strings:
            msgstr = translations.get(msgid)
            if msgstr:
                messages[msgid] = [msgstr]

        data = {
            "translation-revision-date": today,
            "generator": "make-blocks-json.py",
            "domain": "messages",
            "locale_data": {"messages": messages},
        }

        dest = os.path.join(languages_dir, f"{slug}-{locale}-{HANDLE}.json")
        with open(dest, "w", encoding="utf-8") as out:
            json.dump(data, out, indent=1, ensure_ascii=False)
            out.write("\n")
        print(f"{len(messages) - 1} Strings -> {dest}")


if __name__ == "__main__":
    main()
