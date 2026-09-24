<?php
/**
 * Die Sprachen, die es gibt.
 *
 * Eigene Datei, weil sie zwei Leser hat: bootstrap.php für die Anwendung und
 * bin/texte-pruefen.php, das ohne Datenbank auskommen muss und deshalb kein
 * bootstrap einbinden kann. Vorher stand die Liste dort ein zweites Mal — käme
 * eine siebte Sprache dazu, liefe die Prüfung weiter grün, während in ihr
 * jeder neue Text fehlt.
 */
declare(strict_types=1);

const LANGS = ['de' => 'Deutsch', 'en' => 'English', 'nl' => 'Nederlands',
               'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano'];
