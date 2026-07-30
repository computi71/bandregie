#!/bin/sh
# Setzt die öffentliche Demo zurück. Dünner Mantel um bin/demo-reset.php: sorgt
# dafür, dass der Reset als Webbenutzer läuft und sich zwei Läufe nicht in die
# Quere kommen.
#
# Aufruf:   sh bin/demo-reset.sh [kennwort]
# Per cron: 5 * * * *  sh /pfad/zur/demo/bin/demo-reset.sh >> /var/log/bandregie-demo.log 2>&1
#
# Zurückgesetzt wird die Installation, zu der dieses Skript gehört — es gibt
# keinen Pfad als Aufrufwert, damit es nicht auf eine fremde zeigen kann.
#
# Die Dateien der Demo müssen dem Webbenutzer gehören — sonst legt der Reset
# Uploads an, die der Webserver hinterher nicht mehr schreiben darf. Deshalb
# läuft das PHP als dieser Benutzer und nicht als root.
set -eu

DEMO_PASSWORD=${1:-${BANDREGIE_DEMO_PASSWORD:-demo}}
WEBUSER=${BANDREGIE_WEBUSER:-www-data}
PHP=${PHP:-php}

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

if [ "$(id -un)" = "$WEBUSER" ]; then
  RUN=""
elif command -v sudo >/dev/null 2>&1 && sudo -n -u "$WEBUSER" true 2>/dev/null; then
  RUN="sudo -u $WEBUSER"
else
  echo "Kann den Reset nicht als $WEBUSER anstoßen." >&2
  echo "Entweder das Skript als $WEBUSER laufen lassen, oder sudo dafür erlauben." >&2
  exit 1
fi

# Ein Reset dauert ein paar Sekunden; stündlich ist reichlich Luft. Sollte ein
# Lauf doch einmal hängen, wartet der nächste nicht, sondern lässt es sein —
# zwei gleichzeitige Resets auf derselben Datenbank wären schlimmer als eine
# ausgefallene Stunde. Die Sperre trägt den Namen der Installation, damit
# zwei Demos auf einem Server sich nicht gegenseitig aussperren.
LOCK=/tmp/bandregie-demo-reset-$(echo "$SCRIPT_DIR" | tr -c 'A-Za-z0-9' '-').lock

if command -v flock >/dev/null 2>&1; then
  exec flock -n "$LOCK" $RUN "$PHP" "$SCRIPT_DIR/demo-reset.php" "$DEMO_PASSWORD"
fi

echo "Hinweis: flock nicht vorhanden — Reset läuft ohne Sperre." >&2
exec $RUN "$PHP" "$SCRIPT_DIR/demo-reset.php" "$DEMO_PASSWORD"
