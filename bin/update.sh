#!/bin/sh
# Bandregie aktualisieren: erst sichern, dann holen.
#
# Für die Konsole und für cron gedacht. Die Anwendung selbst rührt ihren Code
# nicht an — dafür müsste der Webserver in sein eigenes Verzeichnis schreiben
# dürfen, und das ist ein zu hoher Preis für einen eingesparten Befehl.
#
# Aufruf:   sh bin/update.sh
# Per cron: 30 4 * * 1  sh /var/www/bandregie/bin/update.sh >> /var/log/bandregie-update.log 2>&1
#
# Ausführen sollte ihn, wem die Arbeitskopie gehört. Die Sicherung läuft als
# Webbenutzer, damit die Dateien dieselben Rechte bekommen wie alle anderen;
# geht das nicht, bricht das Skript ab, statt ungesichert weiterzumachen.
set -eu

DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$DIR"

WEBUSER=${BANDREGIE_WEBUSER:-www-data}
PHP=${PHP:-php}

echo "== Bandregie-Update in $DIR"

if [ ! -d .git ]; then
  echo "   Keine Git-Arbeitskopie. Läuft die Installation unter Plesk, geht es so:"
  echo "   plesk ext git --fetch -domain DEINE-DOMAIN -name bandregie"
  echo "   plesk ext git --deploy -domain DEINE-DOMAIN -name bandregie"
  exit 1
fi

VORHER=$(cat VERSION 2>/dev/null || echo unbekannt)
echo "   Fassung vorher: $VORHER"

echo "== Sicherung"
if [ "$(id -un)" = "$WEBUSER" ]; then
  RUN=""
elif command -v sudo >/dev/null 2>&1 && sudo -n -u "$WEBUSER" true 2>/dev/null; then
  RUN="sudo -u $WEBUSER"
else
  echo "   Kann die Sicherung nicht als $WEBUSER anstoßen." >&2
  echo "   Entweder das Skript als $WEBUSER laufen lassen, oder sudo dafür erlauben." >&2
  echo "   Ohne Sicherung wird nicht aktualisiert." >&2
  exit 1
fi
$RUN "$PHP" -r '
  require "'"$DIR"'/app/bootstrap.php";
  require "'"$DIR"'/app/backup.php";
  $r = backup_run("update");
  echo "   ", ($r["status"] ?? "?") === "ok"
    ? "gesichert: " . $r["filename"] . " (" . round(($r["size_bytes"] ?? 0) / 1048576, 1) . " MB)"
    : "FEHLGESCHLAGEN: " . ($r["message"] ?? ""), "\n";
  exit(($r["status"] ?? "") === "ok" ? 0 : 1);
'

echo "== Holen"
git pull --ff-only

NACHHER=$(cat VERSION 2>/dev/null || echo unbekannt)
if [ "$VORHER" = "$NACHHER" ]; then
  echo "   Schon aktuell ($NACHHER)."
else
  echo "   Jetzt $NACHHER. Datenbankänderungen laufen beim nächsten Seitenaufruf von selbst."
fi
