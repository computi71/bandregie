-- Verträge, Veranstalter und die Rolle Bookingagent (#303, #308)
INSERT INTO translations (lang, tkey, value) VALUES
('en','inav_vertraege','Contracts'),
('en','contract_title','Contracts'),
('en','contract_intro','The performance agreement for a gig. Date, venue, playing time and fee already sit on the event; here the wording joins them, and you see at a glance where no signed sheet has come back.'),
('en','contract_none','No contract yet.'),
('en','contract_new','New contract'),
('en','contract_sheet_title','Performance agreement'),
('en','contract_no','Contract number'),
('en','contract_date','Contract date'),
('en','contract_event','Event'),
('en','contract_promoter','Promoter'),
('en','contract_promoter_hint','The contracting party, not the hall. A hall is called "town festival" and is often booked through an agency, and the agency signs.'),
('en','contract_promoter_new','– new promoter –'),
('en','contract_fee','Fee'),
('en','contract_fee_hint','Taken from a quote when there is one, otherwise by hand.'),
('en','contract_play_from','Start of playing'),
('en','contract_play_to','End of playing'),
('en','contract_get_in','Room open from'),
('en','contract_status','State'),
('en','contract_status_entwurf','draft'),
('en','contract_status_verschickt','sent'),
('en','contract_status_unterschrieben','signed copy back'),
('en','contract_mark_sent','Mark as sent'),
('en','contract_mark_signed','Signed copy back'),
('en','contract_mark_draft','Back to draft'),
('en','contract_body','Wording'),
('en','contract_body_hint','Built from your template when the contract is created and frozen afterwards: what was sent must not change because someone edits the template later. While it is still a draft you can edit it here anyway.'),
('en','contract_rebuild','Rebuild from the template'),
('en','contract_rebuild_confirm','Rebuild the wording from the template? Your own edits to it will be lost.'),
('en','contract_print','Print contract'),
('en','contract_from_quote','Fee from the quote'),
('en','contract_delete_confirm','Really delete this contract?'),
('en','contract_signed_file','The signed sheet comes back as a scan and belongs with the files of the event.'),
('en','contract_open_missing','Without a contract'),
('en','fl_contract_saved','Contract saved.'),
('en','fl_contract_deleted','Contract deleted.'),
('en','fl_contract_event_required','A contract belongs to an event — pick one.'),
('en','fl_contract_promoter_required','No promoter, no contracting party. A name is enough.'),
('en','fl_contract_status','State changed: %s.'),
('en','printdoc_contract','Contract'),
('en','promoter_title','Promoters'),
('en','promoter_name','Name or company'),
('en','promoter_contact','Contact person'),
('en','promoter_none','No promoter yet.'),
('en','set_contract_title','Contract template'),
('en','set_contract_intro','The wording every new contract is built from. It is yours: change it as your band needs it. As long as nothing stands here, the supplied version applies.'),
('en','set_contract_legal','This is a writing aid, not legal advice. What you sign is your responsibility — if in doubt, have the text looked at by someone who can judge it.'),
('en','set_contract_fields','Placeholders: whatever stands in curly braces is replaced by the details of the event when the contract is created.'),
('en','set_contract_reset','Reset to the supplied template'),
('en','set_contract_reset_confirm','Discard your version and take the supplied template?'),
('en','help_vertraege','A gig is agreed by contract, and almost everything in it is already known to the program: date, venue, playing time and fee sit on the event. Here the wording joins them, and the list shows which gig still has no signed sheet back.'),
('en','help_vertraege_2','The contracting party is the promoter, not the hall. A hall is called "town festival" and is often booked through an agency, and the agency signs. That is why promoters have a list of their own.'),
('en','help_vertraege_3','The wording is built from your template when the contract is created and then stays put. That is deliberate: what was sent and signed must not change because somebody edits the template half a year later. While a contract is still a draft you can change its text anyway.'),
('en','help_vertraege_4','It prints like your other sheets, with logo and watermark. Signed, it comes back as a scan and belongs with the files of the event. Electronic signatures are deliberately absent: they are a subject of their own with requirements of their own, and for a band they add nothing a scanned sheet does not.'),
('en','role_booking','Booking agent'),
('en','role_booking_hint','Someone outside the band who books for you: sees events, venues, the rider and the contracts. Of the topics they see only the ones they opened themselves — to any other you have to let them in one by one.'),
('en','topic_guests','Let in from outside'),
('en','topic_guests_hint','Anyone not in the band sees this topic only if they stand here. For members nothing changes, they see everything anyway.'),
('en','topic_guest_add','Let in'),
('en','topic_guest_remove','Take out again'),
('en','topic_guests_none','Nobody from outside.'),
('en','fl_topic_guest_added','%s can see this topic now.'),
('en','fl_topic_guest_removed','%s can no longer see this topic.'),
('en','contract_template','PERFORMANCE AGREEMENT

between
{veranstalter}
{veranstalter_anschrift}
— the promoter —

and
{band}
— the artist —

1. Subject
The promoter engages the artist for the following performance:
a) Venue: {ort}
b) Date: {datum}
c) Playing time: {spielzeit}
d) The room is open for load-in and soundcheck from: {einlass}

2. Fee
a) The promoter pays the artist a fixed fee of {gage}.
b) Payable before the performance in cash or against invoice.
c) Music rights fees and any social security levy for artists are borne by the
   promoter.
d) If the performance is cancelled for a reason within the promoter''s control,
   the fee remains due. If it is cancelled for a reason within the artist''s
   control, the claim lapses. Illness is to be reported without delay; both the
   duty to perform and the duty to pay then lapse.

3. The promoter''s obligations
a) A stage that can be played on, with power.
b) The attached stage rider forms part of this agreement.
c) Recordings of the performance require the artist''s consent.
d) A lockable dressing room and drinks in reasonable quantity.

4. The artist''s obligations and rights
a) The artist arrives punctually at the agreed times.
b) The artist is free in the design of the programme.

5. Severability
Should any provision be invalid, the remaining provisions stay in force.

6. Final provisions
There are no verbal side agreements. Changes require written form. This
agreement does not create an employment relationship.


Place, date: ......................        Place, date: {heute}


__________________________                __________________________
The promoter                              The artist'),
('nl','inav_vertraege','Contracten'),
('nl','contract_title','Contracten'),
('nl','contract_sheet_title','Optredensovereenkomst'),
('nl','contract_new','Nieuw contract'),
('nl','contract_none','Nog geen contract.'),
('nl','contract_promoter','Organisator'),
('nl','contract_promoter_new','– nieuwe organisator –'),
('nl','contract_event','Afspraak'),
('nl','contract_fee','Gage'),
('nl','contract_no','Contractnummer'),
('nl','contract_date','Contractdatum'),
('nl','contract_play_from','Aanvang'),
('nl','contract_play_to','Einde'),
('nl','contract_get_in','Ruimte open vanaf'),
('nl','contract_status','Stand'),
('nl','contract_status_entwurf','concept'),
('nl','contract_status_verschickt','verstuurd'),
('nl','contract_status_unterschrieben','ondertekend terug'),
('nl','contract_mark_sent','Als verstuurd noteren'),
('nl','contract_mark_signed','Ondertekend terug'),
('nl','contract_mark_draft','Terug naar concept'),
('nl','contract_body','Tekst'),
('nl','contract_print','Contract afdrukken'),
('nl','contract_open_missing','Zonder contract'),
('nl','contract_delete_confirm','Dit contract echt verwijderen?'),
('nl','promoter_title','Organisatoren'),
('nl','promoter_name','Naam of bedrijf'),
('nl','promoter_contact','Contactpersoon'),
('nl','promoter_none','Nog geen organisator.'),
('nl','printdoc_contract','Contract'),
('nl','role_booking','Boekingsagent'),
('nl','topic_guests','Van buiten toegelaten'),
('nl','topic_guest_add','Toelaten'),
('nl','topic_guest_remove','Weer verwijderen'),
('nl','topic_guests_none','Niemand van buiten.'),
('nl','set_contract_title','Contractsjabloon'),
('nl','contract_template','OPTREDENSOVEREENKOMST

tussen
{veranstalter}
{veranstalter_anschrift}
— de organisator —

en
{band}
— de artiest —

1. Onderwerp
De organisator engageert de artiest voor het volgende optreden:
a) Locatie: {ort}
b) Datum: {datum}
c) Speeltijd: {spielzeit}
d) De ruimte is open voor opbouw en soundcheck vanaf: {einlass}

2. Gage
a) De organisator betaalt de artiest een vaste gage van {gage}.
b) Te voldoen voor het optreden, contant of op factuur.
c) Kosten voor muziekrechten en eventuele sociale heffingen voor artiesten zijn
   voor rekening van de organisator.
d) Vervalt het optreden door een oorzaak bij de organisator, dan blijft de gage
   verschuldigd. Ligt de oorzaak bij de artiest, dan vervalt de aanspraak. Bij
   ziekte wordt onverwijld bericht; beide verplichtingen vervallen dan.

3. Verplichtingen van de organisator
a) Een bespeelbaar podium met stroomvoorziening.
b) De bijgevoegde stagerider maakt deel uit van deze overeenkomst.
c) Opnamen van het optreden vereisen toestemming van de artiest.
d) Een afsluitbare kleedruimte en drinken in redelijke hoeveelheid.

4. Verplichtingen en rechten van de artiest
a) De artiest is op de afgesproken tijden op tijd aanwezig.
b) De artiest is vrij in de samenstelling van het programma.

5. Deelbaarheid
Is een bepaling ongeldig, dan blijven de overige bepalingen van kracht.

6. Slotbepalingen
Er zijn geen mondelinge nevenafspraken. Wijzigingen vereisen de schriftelijke
vorm. Deze overeenkomst schept geen arbeidsverhouding.


Plaats, datum: ....................        Plaats, datum: {heute}


__________________________                __________________________
De organisator                            De artiest'),
('fr','inav_vertraege','Contrats'),
('fr','contract_title','Contrats'),
('fr','contract_sheet_title','Contrat de représentation'),
('fr','contract_new','Nouveau contrat'),
('fr','contract_none','Aucun contrat pour le moment.'),
('fr','contract_promoter','Organisateur'),
('fr','contract_promoter_new','– nouvel organisateur –'),
('fr','contract_event','Date'),
('fr','contract_fee','Cachet'),
('fr','contract_no','Numéro de contrat'),
('fr','contract_date','Date du contrat'),
('fr','contract_play_from','Début'),
('fr','contract_play_to','Fin'),
('fr','contract_get_in','Salle ouverte à partir de'),
('fr','contract_status','État'),
('fr','contract_status_entwurf','brouillon'),
('fr','contract_status_verschickt','envoyé'),
('fr','contract_status_unterschrieben','signé, revenu'),
('fr','contract_mark_sent','Noter comme envoyé'),
('fr','contract_mark_signed','Signé, revenu'),
('fr','contract_mark_draft','Remettre en brouillon'),
('fr','contract_body','Texte'),
('fr','contract_print','Imprimer le contrat'),
('fr','contract_open_missing','Sans contrat'),
('fr','contract_delete_confirm','Supprimer vraiment ce contrat ?'),
('fr','promoter_title','Organisateurs'),
('fr','promoter_name','Nom ou société'),
('fr','promoter_contact','Interlocuteur'),
('fr','promoter_none','Aucun organisateur pour le moment.'),
('fr','printdoc_contract','Contrat'),
('fr','role_booking','Agent de booking'),
('fr','topic_guests','Admis de l’extérieur'),
('fr','topic_guest_add','Faire entrer'),
('fr','topic_guest_remove','Retirer'),
('fr','topic_guests_none','Personne de l’extérieur.'),
('fr','set_contract_title','Modèle de contrat'),
('fr','contract_template','CONTRAT DE REPRÉSENTATION

entre
{veranstalter}
{veranstalter_anschrift}
— l''organisateur —

et
{band}
— l''artiste —

1. Objet
L''organisateur engage l''artiste pour la représentation suivante :
a) Lieu : {ort}
b) Date : {datum}
c) Durée de jeu : {spielzeit}
d) La salle est ouverte pour le montage et les balances à partir de : {einlass}

2. Cachet
a) L''organisateur verse à l''artiste un cachet forfaitaire de {gage}.
b) Payable avant la représentation, en espèces ou sur facture.
c) Les droits d''auteur musicaux et les éventuelles cotisations sociales des
   artistes sont à la charge de l''organisateur.
d) Si la représentation est annulée pour un motif relevant de l''organisateur,
   le cachet reste dû. Si elle est annulée pour un motif relevant de l''artiste,
   le droit s''éteint. En cas de maladie, information sans délai ; les deux
   obligations s''éteignent alors.

3. Obligations de l''organisateur
a) Une scène praticable avec alimentation électrique.
b) La fiche technique jointe fait partie du présent contrat.
c) Tout enregistrement de la représentation requiert l''accord de l''artiste.
d) Une loge fermant à clé et des boissons en quantité raisonnable.

4. Obligations et droits de l''artiste
a) L''artiste se présente ponctuellement aux heures convenues.
b) L''artiste est libre dans la conception de son programme.

5. Clause de sauvegarde
Si une disposition est nulle, les autres dispositions restent en vigueur.

6. Dispositions finales
Il n''existe aucun accord verbal annexe. Les modifications requièrent la forme
écrite. Le présent contrat ne crée pas de relation de travail.


Lieu, date : ......................        Lieu, date : {heute}


__________________________                __________________________
L''organisateur                            L''artiste'),
('es','inav_vertraege','Contratos'),
('es','contract_title','Contratos'),
('es','contract_sheet_title','Contrato de actuación'),
('es','contract_new','Nuevo contrato'),
('es','contract_none','Todavía no hay contrato.'),
('es','contract_promoter','Organizador'),
('es','contract_promoter_new','– nuevo organizador –'),
('es','contract_event','Evento'),
('es','contract_fee','Caché'),
('es','contract_no','Número de contrato'),
('es','contract_date','Fecha del contrato'),
('es','contract_play_from','Inicio'),
('es','contract_play_to','Fin'),
('es','contract_get_in','Sala abierta desde'),
('es','contract_status','Estado'),
('es','contract_status_entwurf','borrador'),
('es','contract_status_verschickt','enviado'),
('es','contract_status_unterschrieben','firmado y devuelto'),
('es','contract_mark_sent','Marcar como enviado'),
('es','contract_mark_signed','Firmado y devuelto'),
('es','contract_mark_draft','Volver a borrador'),
('es','contract_body','Texto'),
('es','contract_print','Imprimir contrato'),
('es','contract_open_missing','Sin contrato'),
('es','contract_delete_confirm','¿Borrar de verdad este contrato?'),
('es','promoter_title','Organizadores'),
('es','promoter_name','Nombre o empresa'),
('es','promoter_contact','Persona de contacto'),
('es','promoter_none','Todavía no hay organizadores.'),
('es','printdoc_contract','Contrato'),
('es','role_booking','Agente de contratación'),
('es','topic_guests','Admitidos desde fuera'),
('es','topic_guest_add','Dar acceso'),
('es','topic_guest_remove','Quitar el acceso'),
('es','topic_guests_none','Nadie de fuera.'),
('es','set_contract_title','Plantilla de contrato'),
('es','contract_template','CONTRATO DE ACTUACIÓN

entre
{veranstalter}
{veranstalter_anschrift}
— el organizador —

y
{band}
— el artista —

1. Objeto
El organizador contrata al artista para la siguiente actuación:
a) Lugar: {ort}
b) Fecha: {datum}
c) Tiempo de actuación: {spielzeit}
d) La sala está abierta para montaje y prueba de sonido desde: {einlass}

2. Caché
a) El organizador paga al artista un caché fijo de {gage}.
b) Pagadero antes de la actuación, en efectivo o contra factura.
c) Los derechos de autor musicales y las eventuales cotizaciones sociales de
   artistas corren a cargo del organizador.
d) Si la actuación se cancela por causa del organizador, el caché sigue
   debiéndose. Si se cancela por causa del artista, decae el derecho. En caso de
   enfermedad se avisará sin demora; decaen entonces ambas obligaciones.

3. Obligaciones del organizador
a) Un escenario practicable con suministro eléctrico.
b) El rider técnico adjunto forma parte de este contrato.
c) Las grabaciones de la actuación requieren el consentimiento del artista.
d) Un camerino con llave y bebidas en cantidad razonable.

4. Obligaciones y derechos del artista
a) El artista se presenta puntualmente a las horas acordadas.
b) El artista es libre en la composición de su programa.

5. Cláusula de salvedad
Si alguna disposición fuera nula, las demás siguen en vigor.

6. Disposiciones finales
No existen acuerdos verbales adicionales. Las modificaciones requieren forma
escrita. Este contrato no crea una relación laboral.


Lugar, fecha: .....................        Lugar, fecha: {heute}


__________________________                __________________________
El organizador                            El artista'),
('it','inav_vertraege','Contratti'),
('it','contract_title','Contratti'),
('it','contract_sheet_title','Contratto di esibizione'),
('it','contract_new','Nuovo contratto'),
('it','contract_none','Ancora nessun contratto.'),
('it','contract_promoter','Organizzatore'),
('it','contract_promoter_new','– nuovo organizzatore –'),
('it','contract_event','Data'),
('it','contract_fee','Cachet'),
('it','contract_no','Numero di contratto'),
('it','contract_date','Data del contratto'),
('it','contract_play_from','Inizio'),
('it','contract_play_to','Fine'),
('it','contract_get_in','Sala aperta dalle'),
('it','contract_status','Stato'),
('it','contract_status_entwurf','bozza'),
('it','contract_status_verschickt','inviato'),
('it','contract_status_unterschrieben','firmato, tornato'),
('it','contract_mark_sent','Segna come inviato'),
('it','contract_mark_signed','Firmato, tornato'),
('it','contract_mark_draft','Torna a bozza'),
('it','contract_body','Testo'),
('it','contract_print','Stampa il contratto'),
('it','contract_open_missing','Senza contratto'),
('it','contract_delete_confirm','Eliminare davvero questo contratto?'),
('it','promoter_title','Organizzatori'),
('it','promoter_name','Nome o azienda'),
('it','promoter_contact','Referente'),
('it','promoter_none','Ancora nessun organizzatore.'),
('it','printdoc_contract','Contratto'),
('it','role_booking','Agente di booking'),
('it','topic_guests','Ammessi dall’esterno'),
('it','topic_guest_add','Far entrare'),
('it','topic_guest_remove','Togliere'),
('it','topic_guests_none','Nessuno dall’esterno.'),
('it','set_contract_title','Modello di contratto'),
('it','contract_template','CONTRATTO DI ESIBIZIONE

tra
{veranstalter}
{veranstalter_anschrift}
— l''organizzatore —

e
{band}
— l''artista —

1. Oggetto
L''organizzatore ingaggia l''artista per la seguente esibizione:
a) Luogo: {ort}
b) Data: {datum}
c) Tempo di esecuzione: {spielzeit}
d) La sala è aperta per montaggio e prova del suono dalle: {einlass}

2. Cachet
a) L''organizzatore versa all''artista un cachet fisso di {gage}.
b) Pagabile prima dell''esibizione, in contanti o dietro fattura.
c) I diritti musicali e gli eventuali contributi previdenziali per gli artisti
   sono a carico dell''organizzatore.
d) Se l''esibizione salta per causa dell''organizzatore, il cachet resta dovuto.
   Se salta per causa dell''artista, il diritto decade. In caso di malattia si
   informa senza indugio; decadono allora entrambi gli obblighi.

3. Obblighi dell''organizzatore
a) Un palco agibile con alimentazione elettrica.
b) La scheda tecnica allegata è parte di questo contratto.
c) Le riprese dell''esibizione richiedono il consenso dell''artista.
d) Un camerino chiudibile e bevande in quantità ragionevole.

4. Obblighi e diritti dell''artista
a) L''artista si presenta puntuale agli orari concordati.
b) L''artista è libero nella composizione del programma.

5. Clausola di salvaguardia
Se una disposizione è nulla, le restanti restano valide.

6. Disposizioni finali
Non esistono accordi verbali accessori. Le modifiche richiedono la forma
scritta. Questo contratto non instaura un rapporto di lavoro.


Luogo, data: ......................        Luogo, data: {heute}


__________________________                __________________________
L''organizzatore                           L''artista')
ON DUPLICATE KEY UPDATE value = value;
