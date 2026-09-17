<?php
// Ein Kontaktblock für alle, die einen haben (#306): Mitglieder, Gäste und die
// Ansprechpartner der Veranstaltungsorte.
//
// Vorher fragten drei Formulare dasselbe in drei Anordnungen und unter drei
// Namen ab: ein Mitglied hatte Telefon und Mobil, aber keine Anschrift, ein
// Gast nur ein Telefonfeld, ein Ort noch einmal andere. Wer sie ausfüllt,
// musste jedes Mal neu suchen; wer sie liest, konnte sich nicht darauf
// verlassen, dass eine Nummer dort steht, wo sie letztes Mal stand.
//
// Vorher setzen:
//   $kfWerte  — die vorhandenen Werte; fehlende Schlüssel bleiben leer
//   $kfFelder — welche Felder in welcher Reihenfolge (Vorgabe: alle)
//   $kfNamen  — Vorsatz für die Feldnamen, etwa 'new_' (Vorgabe: keiner)
//
// Die Anschrift ist beim Ort bewusst nicht dabei: Dort ist die Anschrift die
// des Hauses und nicht die der Person, und die steht schon im Ort selbst.
$kfFelder = $kfFelder ?? ['email', 'phone', 'mobile', 'street', 'postcode', 'city'];
$kfNamen  = $kfNamen ?? '';
$kfWerte  = $kfWerte ?? [];

$kfBeschriftung = [
  'email' => t('email'), 'phone' => t('phone'), 'mobile' => t('mem_mobile'),
  'street' => t('address'), 'postcode' => t('postcode'), 'city' => t('city'),
];
?>
<?php foreach ($kfFelder as $kfFeld): ?>
  <label class="<?= $kfFeld === 'street' ? 'span2' : '' ?>">
    <?= e($kfBeschriftung[$kfFeld] ?? $kfFeld) ?>
    <input <?= $kfFeld === 'email' ? 'type="email"' : '' ?>
           name="<?= e($kfNamen . $kfFeld) ?>"
           value="<?= e((string) ($kfWerte[$kfFeld] ?? '')) ?>"
           maxlength="<?= $kfFeld === 'postcode' ? 20 : ($kfFeld === 'email' ? 190 : 190) ?>">
  </label>
<?php endforeach; ?>
