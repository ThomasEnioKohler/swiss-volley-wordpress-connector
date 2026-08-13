---
description: Version anheben, Changelog pflegen und einen Release veröffentlichen
allowed-tools: Bash, Read, Edit, AskUserQuestion, TodoWrite
---

Führe den Release-Ablauf für dieses Plugin durch. Die gesamte Prüflogik liegt in
den Skripten unter `bin/` — rufe sie auf, statt ihre Arbeit nachzubauen.

## 1. Vorprüfung

Führe aus und werte aus:

```bash
git status --porcelain
git rev-parse --abbrev-ref HEAD
git fetch --tags --quiet && git tag --list --sort=-v:refname
bash bin/version.sh
```

`--sort=-v:refname` sortiert nach Versionsnummer, neueste zuerst. Ohne das
sortiert git alphabetisch und stellt `v0.1.10` vor `v0.1.9`.

Brich mit einer Begründung ab (keine Rückfrage), wenn:

- das Arbeitsverzeichnis nicht sauber ist
- der Branch nicht `main` ist
- `bin/version.sh` einen Fehler meldet

Melde die aktuelle Version und den letzten vorhandenen Tag.

## 2. Frage: Was soll releast werden?

Stelle mit AskUserQuestion genau eine Frage mit diesen Optionen, jeweils mit der
konkret resultierenden Nummer im Label:

- `patch` — X.Y.(Z+1)
- `minor` — X.(Y+1).0
- `major` — (X+1).0.0
- `kein Bump` — die aktuelle Version releasen (nur sinnvoll, wenn für sie noch
  kein Tag existiert)

Bei einer expliziten Wunschversion nimmt der Nutzer «Other».

Ist für die Zielversion bereits ein Tag vorhanden, brich ab.

## 3. Changelog (entfällt bei «kein Bump»)

Lies die Commits seit dem letzten Tag:

```bash
git log --oneline "$(git describe --tags --abbrev=0 2>/dev/null || echo HEAD)"..HEAD
```

Schlage daraus Changelog-Einträge in ganzen Sätzen vor — beschreibe, was der
Nutzer merkt, nicht welche Datei sich geändert hat. Ordne jeden Eintrag einer
Kategorie zu: `Neu`, `Geändert`, `Behoben`, `Entfernt` oder `Sicherheit`.

Zeige den Vorschlag und lass ihn bestätigen oder korrigieren, bevor du schreibst.

## 4. Schreiben (entfällt bei «kein Bump»)

```bash
bash bin/bump-version.sh <ZIELVERSION>
```

Ersetze danach in `CHANGELOG.md` die Zeile `- TODO: Änderungen beschreiben`
durch die bestätigten Einträge, mit `### <Kategorie>`-Überschriften darüber.
Dann:

```bash
bash bin/sync-readme-changelog.sh
```

## 5. Prüfen

```bash
bash bin/build.sh
```

Bricht der Build ab, melde die Ursache und beende den Ablauf. Lass die
geänderten Dateien im Arbeitsverzeichnis liegen, damit der Nutzer korrigieren
kann — mache nichts rückgängig.

**Hinweis zum `.pot`:** `bin/build.sh` erneuert in seinem POT-Schritt bei
jedem Lauf das Feld `POT-Creation-Date` in
`swiss-volley-connector/languages/swiss-volley-connector.pot`. Das
Arbeitsverzeichnis ist danach also fast immer schmutzig, auch wenn inhaltlich
sonst nichts geändert wurde. Das ist erwartet und richtig, kein Fehler: Das
ausgelieferte ZIP enthält genau dieses `.pot`, daher gehört die Änderung mit
in den Release-Commit — Schritt 6 nimmt sie über `git add -A` automatisch mit.
Setze diese Datei nicht mit `git checkout --` zurück, weder hier noch später.

## 6. Rückfrage: committen und taggen

Zeige `git diff --stat` und den erzeugten Changelog-Abschnitt. Für Letzteren
das gleiche Skript verwenden, das später auch der Release-Workflow für den
Text des GitHub-Release benutzt:

```bash
bash bin/release-notes.sh <ZIELVERSION>
```

Bricht dieses Skript ab (Abschnitt fehlt, ist leer oder enthält noch die
TODO-Zeile), fehlt in `CHANGELOG.md` ein vollständiger Abschnitt
`## [<ZIELVERSION>] - YYYY-MM-DD` für die Zielversion. Im Bump-Pfad heisst
das, Schritt 3/4 war unvollständig; im Pfad «kein Bump» fehlt der Abschnitt
vermutlich ganz oder enthält noch die TODO-Zeile aus einem früheren Bump.
Ergänze `CHANGELOG.md` entsprechend, bevor du fortfährst.

Prüfe danach mit `git status --porcelain`, ob nach dem Build Änderungen im
Arbeitsverzeichnis vorliegen — wegen des `.pot`-Hinweises aus Schritt 5 ist
das praktisch immer der Fall, auch im Pfad «kein Bump».

Frage mit AskUserQuestion, ob committet und lokal getaggt werden soll. Bei
Zustimmung:

- **Liegen Änderungen vor** (der Normalfall — auch bei «kein Bump», wegen des
  neu datierten `.pot`): committen und dann taggen.

  ```bash
  git add -A
  git commit -m "chore(release): <ZIELVERSION>"
  git tag "v<ZIELVERSION>"
  ```

- **Liegen wirklich keine Änderungen vor** (`git status --porcelain` ist
  leer): nur taggen, kein Commit nötig.

  ```bash
  git tag "v<ZIELVERSION>"
  ```

Der Tag muss in jedem Fall auf den Commit zeigen, der genau diesem Release
entspricht — tagge nie einen älteren Commit, auch nicht im Pfad «kein Bump».

## 7. Rückfrage: pushen

Frage mit AskUserQuestion ausdrücklich und benenne die Folge: **Der Push des
Tags löst den öffentlichen Release aus.** Bei Zustimmung:

```bash
git push origin main
git push origin "v<ZIELVERSION>"
```

## 8. Abschluss

Beobachte den Workflow und melde das Ergebnis:

```bash
gh run list --limit 3
gh release view "v<ZIELVERSION>" --json url,assets,isPrerelease
```

Melde die Release-URL, den Namen des angehängten ZIPs und ob es als
Pre-Release markiert ist. Ist der Workflow rot, hole mit
`gh run view --log-failed` die Ursache und berichte sie — veröffentliche
nichts nach.
