<?php
// Das gemeinsame Aussehen aller Druckbögen (#304): Blattmaß, Schrift, Logo-Ecke
// und Wasserzeichen. Vier Bögen trugen das vierfach und verschieden — die
// GEMA-Meldung sogar in einer anderen Schrift und ohne Blattmaß.
//
// Gehört in ein <style> und steht dort zuerst. Wer etwas anderes braucht,
// überschreibt es darunter: gleiche Regel, spätere Zeile gewinnt. Die Setliste
// tut das, weil ihr Blatt feste Höhe und knappere Ränder hat.
//
// Vorher setzen: nichts.
?>
/* Ränder fest ins Blatt eingebaut (Padding) statt über @page — so stimmen sie
   unabhängig von der Rand-Einstellung im Druckdialog des Browsers. */
@page { size: A4 portrait; margin: 0; }
/* Keine Schriftgröße hier: Die Setliste rechnet ihre selbst aus, die anderen
   Bögen setzen ihre darunter. Eine gemeinsame Vorgabe würde beides stören. */
body { font-family: Calibri, Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; }
.sheet { box-sizing: border-box; width: 210mm; min-height: 296mm; padding: 14mm 16mm 12mm; position: relative; }
.head-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; }
.logo img { max-height: 20mm; max-width: 65mm; }
.logo .bandname { font-size: 18pt; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
/* Das Wasserzeichen liegt im Blatt, nicht davor, und schluckt keine Klicks.
   print-color-adjust, weil Browser blasse Flächen sonst wegoptimieren. */
.watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 0; pointer-events: none; }
.watermark img { width: 72%; opacity: 0.07; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
/* Am Bildschirm liegt das Blatt auf grauem Grund — sonst sieht man seinen Rand
   nicht und hält den Ausdruck für randlos. */
@media screen {
  body { background: #777; padding: 1rem 0; }
  .sheet { margin: 0 auto 1rem; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
}
