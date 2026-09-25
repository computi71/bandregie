<?php
// Der italienische Bausteinsatz (#360).
//
// KEINE Übersetzung des deutschen. Der entscheidende Unterschied heißt
// AGIBILITÀ:
//
// Für jede künstlerische Darbietung — bezahlt ODER unbezahlt — braucht es
// das certificato di agibilità der INPS (frühere ENPALS-Verwaltung). Ohne
// das Papier darf niemand öffentlich auftreten. Es nennt die Personalien der
// Mitwirkenden, Datum, Ort und das Entgelt der einzelnen Darbietung.
// Bestraft wird, wer unregelmäßig Beschäftigte auftreten lässt, also der
// Veranstalter oder der Betreiber des Lokals: Ihm obliegt zu prüfen, dass
// die Auftretenden in Ordnung sind. Nachträglich geht es nicht — das
// INPS-Portal lässt keine rückwirkende Eintragung zu.
//
// Deshalb steht die Agibilità hier als fester Baustein und nicht als Kür,
// und der Hinweis sagt, dass sie VOR dem Abend da sein muss.
//
// SIAE statt GEMA: Die Erlaubnis für die Musiknutzung holt der Veranstalter.
//
// Quellen: INPS, obbligo assicurativo ex ENPALS; Hinweise zum certificato di
// agibilità (esibirsi.it, musicaitalia.org, sosmusicisti.org).

return [

  ['bkey' => 'it_intestazione', 'gruppe' => 'kopf', 'fest' => 1, 'an' => 1,
   'label' => 'Intestazione',
   'body' => "CONTRATTO DI ESIBIZIONE MUSICALE\n\ntra\n{veranstalter}\n{veranstalter_anschrift}\n— di seguito l'Organizzatore —\n\ne\n{band}\n— di seguito il Gruppo —"],

  // ---------- Oggetto ----------
  ['bkey' => 'it_oggetto', 'gruppe' => 'rahmen', 'fest' => 1, 'an' => 1,
   'label' => 'Oggetto',
   'body' => "L'Organizzatore ingaggia il Gruppo per la seguente esibizione:\nLuogo: {ort}\nData: {datum}\nOrario: {spielzeit}"],

  ['bkey' => 'it_montaggio', 'gruppe' => 'rahmen', 'an' => 1,
   'label' => 'Montaggio e prove del suono',
   'body' => "Il locale è a disposizione dalle {einlass} per il montaggio e le prove del suono. Fino all'apertura al pubblico il Gruppo dispone del palco senza interruzioni."],

  ['bkey' => 'it_set', 'gruppe' => 'rahmen',
   'label' => 'Set e intervallo',
   'body' => "Il Gruppo esegue ____ set di circa ____ minuti ciascuno, con un intervallo di ____ minuti. È concordato un bis di durata non superiore a ____ minuti."],

  // ---------- Compenso e spese ----------
  ['bkey' => 'it_compenso_fisso', 'gruppe' => 'gage', 'wahl' => 'Compenso', 'an' => 1,
   'label' => 'Compenso fisso',
   'body' => "L'Organizzatore corrisponde al Gruppo un compenso di {gage} per l'esibizione. Tale importo remunera tutte le prestazioni previste dal presente contratto, salvo quanto diversamente pattuito di seguito."],

  ['bkey' => 'it_compenso_incasso', 'gruppe' => 'gage', 'wahl' => 'Compenso',
   'label' => 'Percentuale sugli incassi',
   'body' => "L'Organizzatore corrisponde al Gruppo il ____ % degli incassi dei biglietti, calcolati al netto dell'IVA. Il biglietto costa ____ euro, ridotto ____ euro. L'Organizzatore consegna il rendiconto dei biglietti venduti subito dopo l'esibizione."],

  ['bkey' => 'it_compenso_misto', 'gruppe' => 'gage', 'wahl' => 'Compenso',
   'label' => 'Minimo garantito più percentuale',
   'body' => "L'Organizzatore garantisce un minimo di {gage}. Se il ____ % degli incassi dei biglietti supera tale importo, il Gruppo percepisce quella quota in luogo del minimo. Il rendiconto è consegnato subito dopo l'esibizione."],

  ['bkey' => 'it_pagamento_fattura', 'gruppe' => 'gage', 'wahl' => 'Pagamento', 'an' => 1,
   'label' => 'Pagamento: su fattura',
   'body' => "Il Gruppo emette fattura dopo l'esibizione. L'importo è esigibile senza sconto entro 30 giorni dalla data della fattura."],

  ['bkey' => 'it_pagamento_contanti', 'gruppe' => 'gage', 'wahl' => 'Pagamento',
   'label' => 'Pagamento: in contanti la sera stessa',
   'body' => "Il compenso è corrisposto in contanti al termine dell'esibizione, contro ricevuta."],

  ['bkey' => 'it_agibilita', 'gruppe' => 'gage', 'fest' => 1, 'an' => 1,
   'label' => 'Agibilità INPS (ex ENPALS)',
   'body' => "Il certificato di agibilità INPS per i lavoratori dello spettacolo è acquisito prima dell'esibizione e riporta i dati anagrafici dei partecipanti, la data, il luogo e il compenso della singola prestazione. Le parti si comunicano tempestivamente i dati necessari. L'Organizzatore verifica che chi si esibisce sia in regola.",
   'hinweis' => "Sempre presente e non disattivabile: l'agibilità serve per ogni esibizione artistica, retribuita o gratuita, e nessuno può esibirsi in pubblico senza. Va richiesta PRIMA della serata — il portale INPS non consente operazioni retroattive. La sanzione per personale irregolare ricade su chi organizza o gestisce il locale."],

  ['bkey' => 'it_siae', 'gruppe' => 'gage', 'fest' => 1, 'an' => 1,
   'label' => 'SIAE',
   'body' => "I diritti d'autore per l'esecuzione delle opere sono a carico dell'Organizzatore, che acquisisce il permesso SIAE prima dell'esibizione e ne sostiene i compensi. Il Gruppo consegna l'elenco delle opere eseguite (borderò).",
   'hinweis' => "L'obbligo è dell'Organizzatore, non dei musicisti."],

  ['bkey' => 'it_iva', 'gruppe' => 'gage',
   'label' => 'IVA',
   'body' => "Gli importi si intendono al netto dell'IVA, che è aggiunta nella misura di legge e indicata in fattura."],

  ['bkey' => 'it_ritenuta', 'gruppe' => 'gage',
   'label' => 'Ritenuta d\'acconto',
   'body' => "Sull'importo fatturato l'Organizzatore opera la ritenuta d'acconto dovuta per legge, la versa all'erario e ne rilascia al Gruppo la relativa certificazione."],

  ['bkey' => 'it_viaggio', 'gruppe' => 'gage',
   'label' => 'Spese di viaggio',
   'body' => "L'Organizzatore rimborsa le spese di viaggio nella misura di ____ euro per chilometro percorso, dalla sala prove al luogo dell'esibizione e ritorno."],

  ['bkey' => 'it_impianto', 'gruppe' => 'gage',
   'label' => 'Compenso per impianto proprio',
   'body' => "Se il Gruppo fornisce l'impianto audio e luci, percepisce in aggiunta ____ euro."],

  ['bkey' => 'it_annull_org', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Annullamento da parte dell\'Organizzatore',
   'body' => "Se l'Organizzatore annulla l'esibizione o questa non ha luogo per causa a lui imputabile, il compenso pattuito resta integralmente dovuto. Si detraggono le spese che il Gruppo ha effettivamente risparmiato."],

  ['bkey' => 'it_annull_gruppo', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Annullamento da parte del Gruppo',
   'body' => "Se il Gruppo non può esibirsi per causa a lui imputabile, viene meno il diritto al compenso. Ne dà immediata comunicazione all'Organizzatore e si adopera per una sostituzione equivalente."],

  ['bkey' => 'it_malattia', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Malattia',
   'body' => "In caso di malattia di un componente, il Gruppo ne dà immediata comunicazione all'Organizzatore. Se per questo l'esibizione non è possibile, vengono meno sia l'obbligo di esibirsi sia quello di pagare, senza ulteriori pretese di alcuna delle parti."],

  ['bkey' => 'it_forza_maggiore', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Forza maggiore',
   'body' => "Se l'esibizione diviene impossibile per forza maggiore — tra l'altro eventi naturali, provvedimenti dell'autorità o calamità —, vengono meno gli obblighi di entrambe le parti senza indennizzo. Le somme già corrisposte sono restituite; ciascuna parte sopporta le spese che non poteva più evitare.",
   'hinweis' => "Senza una clausola propria vale la regola generale, che in caso di divieto dell'autorità di norma non riconosce alcun compenso. Chi vuole diversamente deve scriverlo qui."],

  // ---------- Obblighi dell'Organizzatore ----------
  ['bkey' => 'it_tecnica_org', 'gruppe' => 'veranstalter', 'wahl' => 'Tecnica', 'an' => 1,
   'label' => 'Tecnica: la fornisce l\'Organizzatore',
   'body' => "L'Organizzatore mette a disposizione un palco praticabile con alimentazione elettrica sufficiente, nonché impianto audio e luci adeguati al locale. Durante montaggio, prove e esibizione è reperibile una persona in grado di utilizzare l'impianto."],

  ['bkey' => 'it_tecnica_gruppo', 'gruppe' => 'veranstalter', 'wahl' => 'Tecnica',
   'label' => 'Tecnica: la porta il Gruppo',
   'body' => "L'Organizzatore mette a disposizione il locale e l'alimentazione elettrica. Il Gruppo porta impianto audio e luci in misura adeguata al locale. L'Organizzatore comunica la capienza prevista almeno quattro settimane prima e consente un sopralluogo su richiesta."],

  ['bkey' => 'it_rider', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'La scheda tecnica fa parte del contratto',
   'body' => "La scheda tecnica del Gruppo è parte integrante del presente contratto. L'Organizzatore concorda per tempo ogni scostamento."],

  ['bkey' => 'it_sicurezza', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Autorizzazioni, sicurezza e assicurazione',
   'body' => "L'Organizzatore ottiene le autorizzazioni amministrative necessarie, risponde della sicurezza del pubblico e del palco e dispone di una copertura assicurativa di responsabilità civile per la manifestazione."],

  ['bkey' => 'it_responsabilita', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Responsabilità su persone e attrezzatura',
   'body' => "Per tutta la permanenza del Gruppo nel luogo dell'esibizione, l'Organizzatore risponde della sicurezza dei componenti e dei loro collaboratori, nonché dell'attrezzatura e degli strumenti introdotti. Dei danni causati dal Gruppo stesso risponde quest'ultimo."],

  ['bkey' => 'it_camerino', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Camerino',
   'body' => "L'Organizzatore mette a disposizione del Gruppo un camerino chiudibile a chiave e riscaldabile."],

  ['bkey' => 'it_bevande', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Bevande',
   'body' => "L'Organizzatore mette gratuitamente a disposizione del Gruppo e dei suoi collaboratori bevande in quantità adeguata durante montaggio, prove ed esibizione."],

  ['bkey' => 'it_pasto', 'gruppe' => 'veranstalter',
   'label' => 'Pasto caldo',
   'body' => "L'Organizzatore fornisce un pasto caldo per giornata di esibizione al Gruppo e ai suoi collaboratori. Numero: ____, di cui vegetariani: ____."],

  ['bkey' => 'it_pernottamento', 'gruppe' => 'veranstalter',
   'label' => 'Pernottamento',
   'body' => "L'Organizzatore sostiene le spese di pernottamento con prima colazione per ____ persone in ____ camere singole e ____ doppie. La struttura è indicata al Gruppo almeno una settimana prima."],

  ['bkey' => 'it_aperto', 'gruppe' => 'veranstalter',
   'label' => 'All\'aperto',
   'body' => "Se l'esibizione si tiene all'aperto, il palco e la postazione di regia sono coperti e protetti dalle intemperie. L'Organizzatore garantisce un fondo piano e portante."],

  ['bkey' => 'it_promozione', 'gruppe' => 'veranstalter',
   'label' => 'Promozione',
   'body' => "L'Organizzatore promuove la manifestazione in misura adeguata e cita il Gruppo su tutti gli annunci nella grafia comunicata."],

  ['bkey' => 'it_lista', 'gruppe' => 'veranstalter',
   'label' => 'Lista ospiti',
   'body' => "Il Gruppo può tenere una lista ospiti di due ingressi gratuiti per componente, consegnata all'Organizzatore prima dell'apertura al pubblico."],

  ['bkey' => 'it_riprese', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Riprese audio e video',
   'body' => "Le riprese audio, fotografiche, cinematografiche e video delle prove e dell'esibizione, oltre l'uso privato del pubblico, richiedono il preventivo consenso del Gruppo. Le riprese che l'Organizzatore intenda usare per la propria pubblicità richiedono accordo separato."],

  // ---------- Obblighi e diritti del Gruppo ----------
  ['bkey' => 'it_orari', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Rispetto degli orari',
   'body' => "Il Gruppo rispetta gli orari concordati per montaggio, prove ed esibizione."],

  ['bkey' => 'it_programma', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Libertà di programma',
   'body' => "Il Gruppo compone liberamente il proprio programma. Richieste particolari dell'Organizzatore richiedono accordo separato."],

  ['bkey' => 'it_sostituzione', 'gruppe' => 'kuenstler',
   'label' => 'Sostituzione di un componente',
   'body' => "Se viene a mancare un singolo componente, il Gruppo può sostituirlo con una formazione equivalente, senza che ne derivino diritti per l'Organizzatore."],

  ['bkey' => 'it_materiale', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Materiale promozionale',
   'body' => "Il Gruppo mette gratuitamente a disposizione dell'Organizzatore informazioni, testo per la stampa e fotografie per annunciare l'esibizione. I relativi diritti restano al Gruppo; l'uso è limitato alla promozione di questa esibizione."],

  // ---------- Disposizioni finali ----------
  ['bkey' => 'it_nullita', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Nullità parziale',
   'body' => "Se una clausola del presente contratto risulta nulla o ineseguibile, le altre restano valide. Le parti la sostituiscono con una ammissibile che si avvicini il più possibile allo scopo economico voluto."],

  ['bkey' => 'it_forma_scritta', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Nessun accordo verbale',
   'body' => "Non sussistono accordi verbali. Modifiche e integrazioni del presente contratto avvengono per iscritto; è sufficiente un messaggio di posta elettronica."],

  ['bkey' => 'it_foro', 'gruppe' => 'schluss',
   'label' => 'Legge applicabile e foro',
   'body' => "Il presente contratto è regolato dalla legge italiana. In difetto di accordo, è competente il foro previsto dalla legge."],

  ['bkey' => 'it_firme', 'gruppe' => 'fuss', 'fest' => 1, 'an' => 1,
   'label' => 'Firme',
   'body' => "Luogo, data: ......................        Luogo, data: {heute}\n\n\n__________________________                __________________________\nL'Organizzatore                           Il Gruppo"],
];
