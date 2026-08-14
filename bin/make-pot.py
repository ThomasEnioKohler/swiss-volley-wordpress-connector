#!/usr/bin/env python3
"""Erzeugt languages/volleyball-schedules-for-swiss-volley.pot aus den PHP- und JS-Quellen.

Aufruf: bin/make-pot.py <plugin-verzeichnis>
"""
import datetime
import os
import re
import sys

PLUGIN_DIR = sys.argv[1] if len(sys.argv) > 1 else "volleyball-schedules-for-swiss-volley"

PAT = re.compile(
    r"(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*"
    r"'((?:[^'\\]|\\.)*)'\s*,\s*'volleyball-schedules-for-swiss-volley'"
)
PATX = re.compile(
    r"(?:esc_html_x|_x|esc_attr_x)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*"
    r"'((?:[^'\\]|\\.)*)'\s*,\s*'volleyball-schedules-for-swiss-volley'"
)


def esc(text: str) -> str:
    return text.replace("\\'", "'").replace('"', '\\"')


def main() -> None:
    strings: dict[tuple[str, str | None], bool] = {}
    version = "0.0.0"

    for root, _dirs, files in os.walk(PLUGIN_DIR):
        for name in files:
            if not (name.endswith(".php") or name.endswith(".js")):
                continue
            src = open(os.path.join(root, name), encoding="utf-8").read()
            if name == "volleyball-schedules-for-swiss-volley.php":
                m = re.search(r"define\( 'VSSV_VERSION', '([0-9.]+)'", src)
                if m:
                    version = m.group(1)
            for m in PATX.finditer(src):
                strings[(m.group(1), m.group(2))] = True
            for m in PAT.finditer(src):
                strings[(m.group(1), None)] = True

    out = [
        "# Volleyball Schedules for Swiss Volley.",
        'msgid ""',
        'msgstr ""',
        f'"Project-Id-Version: Volleyball Schedules for Swiss Volley {version}\\n"',
        f'"POT-Creation-Date: {datetime.date.today().isoformat()}\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"X-Domain: volleyball-schedules-for-swiss-volley\\n"',
        "",
    ]
    # Kontextloser Eintrag hat ctx = None; ohne key() vergleicht sorted()
    # bei gleichem msgid None mit str und wirft TypeError.
    for msg, ctx in sorted(strings, key=lambda key: (key[0], key[1] or "")):
        if ctx:
            out.append(f'msgctxt "{esc(ctx)}"')
        out.append(f'msgid "{esc(msg)}"')
        out.append('msgstr ""')
        out.append("")

    dest = os.path.join(PLUGIN_DIR, "languages", "volleyball-schedules-for-swiss-volley.pot")
    with open(dest, "w", encoding="utf-8") as handle:
        handle.write("\n".join(out))
    print(f"{len(strings)} Strings -> {dest}")


if __name__ == "__main__":
    main()
