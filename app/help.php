<?php
declare(strict_types=1);

/**
 * Die Hilfeseite: ihre Abschnitte und ihre Bilder (#305, #311).
 *
 * Gezeichnete Bilder
 *
 * Die meisten Bereiche zeigen ein Bildschirmfoto aus der Demo — man erkennt
 * seine eigene Seite darin wieder, und das hilft mehr als jede Zeichnung.
 * Fotografieren lässt sich aber nur, was eine Seite hat. Der Versand einer
 * Mail, eine ausbleibende Mitteilung, der zweite Faktor und das Symbol auf dem
 * Startbildschirm haben keine; für sie bleiben diese Zeichnungen.
 *
 * Sie tragen keine Wörter, nehmen die Farben des Programms an und bleiben auf
 * einem Telefon lesbar. Die Fotos entstehen mit bin/help-shots.js neu, sobald
 * sich die Oberfläche ändert.
 */

/**
 * Welche Abschnitte die Hilfe für dieses Konto hat — in der Reihenfolge, in der
 * sie auf der Seite stehen (#311).
 *
 * Eine Stelle für die Bedingungen: Register, Druckansicht und die Seite selbst
 * müssen sich einig sein, was es überhaupt gibt. Stünden die Bedingungen
 * dreimal da, zeigte das Register irgendwann auf einen Abschnitt, den es für
 * diesen Leser nicht gibt.
 *
 * Je Eintrag: Sprungmarke, Zeichen und Beschriftung.
 */
function help_sections(array $user): array {
  $abschnitte = ['zusammenhang' => ['🔗', t('help_flow_title')]];
  foreach (array_keys(PERM_MODULES) as $mod) {
    // Wer den Angebotsschritt nicht benutzt, bekommt ihn auch nicht erklärt —
    // sonst steht in der Hilfe ein Bereich, den es im Menü nicht gibt (#312).
    if ($mod === 'angebote' && !quote_step_active()) continue;
    if (perm_allows($user, $mod)) $abschnitte[$mod] = [MODULE_ICONS[$mod] ?? '•', t('inav_' . $mod)];
  }
  if (perm_allows($user, 'kasse')) {
    $abschnitte['steuer'] = ['⚖', t('taxr_title')];
    if (setting('tax_small_business', '0') === '1') {
      $abschnitte['kleinunternehmer'] = ['⚖', t('help_tax_title')];
    }
  }
  if (push_available()) $abschnitte['push'] = ['🔕', t('help_push_trouble_title')];
  if (totp_available() || totp_active((int) $user['id'])) $abschnitte['totp'] = ['🔑', t('help_totp_title')];
  if (passkey_available()) $abschnitte['passkey'] = ['🔐', t('help_passkey_title')];
  $abschnitte['app'] = ['📱', t('app_install')];
  return $abschnitte;
}

/**
 * Dieselben Abschnitte, nach Beschriftung sortiert — das Register für alle, die
 * den Namen kennen und nicht das Vorhaben. Sortiert wird mit der Sprache des
 * Lesers: „Übersicht" gehört hinter „Termine" und nicht ans Ende.
 */
function help_sections_sorted(array $user): array {
  $abschnitte = help_sections($user);
  uasort($abschnitte, static fn(array $a, array $b): int => strcoll($a[1], $b[1]));
  return $abschnitte;
}

/** Ein Bild der Hilfeseite als SVG. Unbekannter Name gibt nichts zurück. */
function help_figure(string $name): string {
  $inhalt = HELP_FIGURES[$name] ?? '';
  if ($inhalt === '') return '';
  return '<svg class="helpfig" viewBox="0 0 320 130" role="img" aria-hidden="true" focusable="false">'
       . $inhalt . '</svg>';
}

/**
 * Das Bild eines Hilfeabschnitts.
 *
 * Bevorzugt ein Bildschirmfoto aus der Demo: Man erkennt darin seine eigene
 * Seite wieder, und genau das sucht jemand, der nicht weiß, wo er klicken soll.
 * Gibt es zu einem Abschnitt keines — weil er keine Seite hat —, kommt die
 * Zeichnung. Nachgeladen wird beides erst beim Hinsehen; eine Hilfeseite mit
 * zwanzig Bildern soll nicht am Mobilfunknetz hängen bleiben.
 */
function help_picture(string $name, string $bereich): string {
  $pfad = '/assets/help/help-' . $name . '.png';
  if (is_file(BASE_DIR . '/httpdocs' . $pfad)) {
    return '<img class="helpshot" src="' . e(asset($pfad)) . '"'
         . ' alt="' . e(sprintf(t('help_shot_alt'), $bereich)) . '"'
         . ' loading="lazy" decoding="async">';
  }
  return help_figure($name);
}

/**
 * Der Pfeilkopf steht einmal im Dokument und wird von allen Bildern benutzt.
 * Ohne ihn zeichnet ein Pfeil nur einen Strich.
 */
function help_figure_defs(): string {
  return '<svg width="0" height="0" style="position:absolute" aria-hidden="true">'
       . '<defs><marker id="hf-tip" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto">'
       . '<path d="M0 0 L10 5 L0 10 z" class="hf-edge-fill"/></marker></defs></svg>';
}

const HELP_FIGURES = [

  // E-Mail-Versand: ein Brief verlässt das Haus.
  'mailversand' =>
    '<rect class="hf-card" x="20" y="34" width="110" height="66" rx="8"/>
     <path class="hf-edge" d="M20 40 L75 76 L130 40"/>
     <path class="hf-accent-stroke" d="M142 67 H262"/>
     <path class="hf-accent-fill" d="M256 55 l22 12 l-22 12 z"/>
     <circle class="hf-ok" cx="292" cy="34" r="12"/>
     <path class="hf-card-fill-stroke" d="M286 34 l4 4 l8 -9"/>',

  // Mitteilungen: eine Glocke mit Zähler.
  'push' =>
    '<path class="hf-accent-fill" d="M160 26 a34 34 0 0 1 34 34 v22 l10 14 h-88 l10 -14 v-22 a34 34 0 0 1 34 -34 z"/>
     <path class="hf-accent-fill" d="M148 100 a12 12 0 0 0 24 0 z"/>
     <circle class="hf-no" cx="200" cy="34" r="14"/>
     <rect class="hf-card" x="36" y="44" width="56" height="40" rx="8"/>
     <rect class="hf-card" x="228" y="44" width="56" height="40" rx="8"/>',

  // Zweiter Faktor: Kennwort plus Zahl vom Handy.
  'totp' =>
    '<rect class="hf-card" x="16" y="30" width="118" height="70" rx="8"/>
     <rect class="hf-line-soft" x="32" y="48" width="86" height="8" rx="4"/>
     <rect class="hf-line-soft" x="32" y="70" width="60" height="8" rx="4"/>
     <path class="hf-edge" d="M146 66 H180" marker-end="url(#hf-tip)"/>
     <rect class="hf-card" x="196" y="14" width="76" height="102" rx="12"/>
     <rect class="hf-accent" x="212" y="46" width="44" height="14" rx="4"/>
     <rect class="hf-line-soft" x="212" y="70" width="44" height="6" rx="3"/>
     <circle class="hf-line" cx="234" cy="104" r="5"/>',

  // Passkey: das Gerät öffnet sich mit dem Finger.
  'passkey' =>
    '<rect class="hf-card" x="120" y="12" width="80" height="106" rx="14"/>
     <circle class="hf-accent" cx="160" cy="56" r="20"/>
     <path class="hf-card-fill-stroke" d="M152 56 a8 10 0 0 1 16 0 M156 56 a4 8 0 0 1 8 0 M160 50 v14"/>
     <rect class="hf-line-soft" x="136" y="88" width="48" height="6" rx="3"/>
     <rect class="hf-card" x="16" y="42" width="80" height="46" rx="8"/>
     <rect class="hf-card" x="224" y="42" width="80" height="46" rx="8"/>
     <path class="hf-edge" d="M100 65 H114" marker-end="url(#hf-tip)"/>
     <path class="hf-edge" d="M206 65 H220" marker-end="url(#hf-tip)"/>',

  // Auf dem Handy installieren: aus der Seite wird ein Symbol.
  'app' =>
    '<rect class="hf-card" x="16" y="18" width="120" height="94" rx="10"/>
     <rect class="hf-line-soft" x="30" y="34" width="72" height="7" rx="3"/>
     <rect class="hf-line-soft" x="30" y="52" width="92" height="6" rx="3"/>
     <rect class="hf-accent" x="30" y="86" width="52" height="12" rx="6"/>
     <path class="hf-edge" d="M150 65 H186" marker-end="url(#hf-tip)"/>
     <rect class="hf-card" x="202" y="14" width="76" height="102" rx="12"/>
     <rect class="hf-accent" x="216" y="34" width="22" height="22" rx="6"/>
     <rect class="hf-line-soft" x="246" y="34" width="22" height="22" rx="6"/>
     <rect class="hf-line-soft" x="216" y="66" width="22" height="22" rx="6"/>
     <rect class="hf-line-soft" x="246" y="66" width="22" height="22" rx="6"/>',

  // Wie alles zusammenhängt: Anfrage, Angebot, Vertrag, Auftritt, Kasse.
  'flow' =>
    '<rect class="hf-card" x="6" y="44" width="52" height="42" rx="8"/>
     <path class="hf-edge" d="M62 65 H78" marker-end="url(#hf-tip)"/>
     <rect class="hf-card" x="82" y="44" width="52" height="42" rx="8"/>
     <path class="hf-edge" d="M138 65 H154" marker-end="url(#hf-tip)"/>
     <rect class="hf-accent-soft hf-stroke" x="158" y="44" width="52" height="42" rx="8"/>
     <path class="hf-edge" d="M214 65 H230" marker-end="url(#hf-tip)"/>
     <rect class="hf-card" x="234" y="44" width="52" height="42" rx="8"/>
     <path class="hf-edge" d="M290 65 H306" marker-end="url(#hf-tip)"/>
     <rect class="hf-line-soft" x="18" y="60" width="28" height="6" rx="3"/>
     <rect class="hf-line-soft" x="94" y="60" width="28" height="6" rx="3"/>
     <rect class="hf-line" x="170" y="60" width="28" height="6" rx="3"/>
     <rect class="hf-line-soft" x="246" y="60" width="28" height="6" rx="3"/>
     <circle class="hf-ok" cx="312" cy="65" r="8"/>
     <path class="hf-edge" d="M108 40 V22 H184 V40" marker-end="url(#hf-tip)"/>
     <rect class="hf-line-soft" x="126" y="10" width="40" height="6" rx="3"/>
     <path class="hf-edge" d="M184 90 V108 H108 V90" marker-end="url(#hf-tip)"/>
     <rect class="hf-line-soft" x="126" y="114" width="40" height="6" rx="3"/>',
];
