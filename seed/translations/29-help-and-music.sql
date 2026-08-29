-- Hilfetexte und Musikseite
--
-- help_orte stand hier ein zweites Mal und konnte nie gewinnen: Seed 16 setzt
-- ihn und laedt vorher (glob sortiert alphabetisch). Zwei Stellen fuer einen
-- Text heisst, dass eine davon irgendwann veraltet — sie ist raus (#249).
--
-- help_setlists ist mit den Sprechpausen und der Klammer laenger geworden (#241,
-- #242, #248). Weil der frueheste Seed gewinnt, muss der alte Text einmal weg —
-- und zwar HIER, nicht in einem spaeteren Seed: der loeschte sonst, was diese
-- Datei gerade eingesetzt hat.
DELETE FROM translations WHERE tkey = 'help_setlists'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'help_setlists_v3') = 0;
INSERT INTO settings (`key`, value) VALUES ('help_setlists_v3', '1')
  ON DUPLICATE KEY UPDATE value = value;
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','inav_musik','Music & videos'),('en','inav_hilfe','Help'),
('en','fl_media_saved','Link saved.'),('en','fl_media_deleted','Link deleted.'),
('en','help_title','Help'),('en','help_intro','What is behind each area?'),
('en','help_more','More about the app, the licence and the contributors is under "About".'),
('en','help_termine','Every gig, rehearsal and meeting. Everyone accepts or declines, files and comments hang on the event, and the packing list says which gear comes along.'),
('en','help_songs','The repertoire with key, tempo, length and status. Charts, lyrics and recordings hang on the song.'),
('en','help_setlists','The running order for a gig, with breaks, announcements and encores. Playing time adds up from the song lengths. Three separators, and they mean different things: a **break** splits the evening — the band leaves the stage, and in print a new sheet begins. An **announcement** separates within the sheet: nothing is played, something is said — introducing the band, retuning. It draws as a dashed line, the way the line on a paper setlist does, with the announcement sitting in it. The **encore rule** separates what is only played if the audience asks for it. And the **brace** ties together songs played as one run, with a cue for all of them — "drop D" above the first three means those three in drop D. Cue and announcement belong to the setlist, not to the song: the same song sits in many setlists, and both are true for one evening. Only songs are numbered; separators carry no number. For the stage, the "Teleprompter" button starts the whole set: it begins at the first song that has lyrics, and at the end of a song the next one comes up by itself — a tap on the text starts it. In the print view, "Also print" at the top decides what stands beside the title: artist, release year, tempo, playing time and the song note (first line or complete). Your device remembers the choice.'),
('en','help_abwesenheiten','Holidays and blocked dates. If an event falls inside one, the event list warns you.'),
('en','help_aufgaben','What needs doing and who does it.'),
('en','help_themen','Discussions in peace, without anything getting lost in a chat.'),
('en','help_kasse','Income and expenses of the band; fees can be taken over from the events.'),
('en','help_equipment','The inventory with parts, prices and deadlines such as inspections or insurance. One record stands for one device: two identical microphones are two records, numbered "#1" and "#2", because they are carried, lent and missed one at a time. For consumables and bulk goods there is the "Quantity" field instead — ten XLR boots are not ten records. Where a row carries a count although they are devices, "Split into individual devices" turns it into separate records; price, purchase date, invoice and photo go to each of them. A new record can adopt a photo already in the inventory instead of uploading the same file twice.'),
('en','help_rider','What a promoter needs to know about your technical side, plus the channel assignment for the desk. The stage plot is to scale: 8 × 6 m by default, and everything with a real footprint — risers, amps, monitors, cabinets — is drawn at its own size. That is how a promoter sees whether the band fits on his stage. "Generate from the member list" sets out a template: drums back centre on a 3 × 2 m riser, bass back left, vocals at the front, plus power and a stagebox. Everything can then be dragged or typed into the number fields. Whoever unticks "I am on stage" in their profile is not placed — that is where the engineer belongs. Which figure a member gets on the plot is their own choice in their profile; with "My photo" their profile picture stands there.'),
('en','help_fotos','Pictures for the public page and for the archive.'),
('en','help_musik','Videos and streams that appear on the public music page.'),
('en','help_downloads','Press material for promoters — with a link to pass on.'),
('en','help_mitglieder','Who is in the band, with contact details and roles.'),

('fr','inav_musik','Musique & vidéos'),('fr','inav_hilfe','Aide'),
('fr','fl_media_saved','Lien enregistré.'),('fr','fl_media_deleted','Lien supprimé.'),
('fr','help_title','Aide'),('fr','help_intro','Que trouve-t-on dans chaque domaine ?'),
('fr','help_more','Plus d''informations sur l''application, la licence et les contributeurs sous « À propos ».'),
('fr','help_termine','Tous les concerts, répétitions et réunions. Chacun confirme ou décline, les fichiers et commentaires sont attachés à l''événement, et la liste de matériel dit ce qu''on emporte.'),
('fr','help_songs','Le répertoire avec tonalité, tempo, durée et statut. Partitions, textes et enregistrements sont attachés au morceau.'),
('fr','help_setlists','L''ordre de passage d''un concert, avec pauses, annonces et rappels. Le temps de jeu s''additionne a partir des durees. Trois separateurs, de sens different : une **pause** coupe la soiree — le groupe quitte la scene et une nouvelle feuille commence a l''impression. Une **annonce** separe a l''interieur de la feuille : on ne joue pas, on parle — presenter le groupe, raccorder. Elle se trace en pointilles, comme le trait sur une setlist papier, l''annonce ecrite dedans. Le **trait de rappel** separe ce qui n''est joue que si le public le demande. Et l''**accolade** relie des morceaux joues d''affilee, avec une consigne pour tous. Consigne et annonce appartiennent a la setlist, pas au morceau. Seuls les morceaux sont numerotes. Pour la scene, le bouton "Prompteur" lance tout le set : il commence au premier morceau qui a des paroles, et a la fin d''un morceau le suivant arrive de lui-meme — une touche sur le texte le lance. A l''impression, "Imprimer aussi" en haut decide de ce qui figure a cote du titre : interprete, annee, tempo, duree et la note du morceau (premiere ligne ou complete). L''appareil retient le choix.'),
('fr','help_abwesenheiten','Vacances et dates bloquées. Si un événement tombe dedans, la liste vous prévient.'),
('fr','help_aufgaben','Ce qu''il y a à faire et qui s''en occupe.'),
('fr','help_themen','Des discussions au calme, sans que rien ne se perde dans un chat.'),
('fr','help_kasse','Recettes et dépenses du groupe ; les cachets se reprennent depuis les événements.'),
('fr','help_equipment','L''inventaire avec composants, prix et échéances comme contrôles ou assurances. Une fiche correspond à un appareil : deux micros identiques font deux fiches, numérotées « #1 » et « #2 », car on les transporte, les prête et les perd un par un. Pour la petite quincaillerie et le câble au mètre, il y a le champ « Quantité » — dix manchons XLR ne font pas dix fiches. Si une ligne porte un nombre alors qu''il s''agit d''appareils, « Séparer en appareils individuels » en fait des fiches distinctes ; prix, date d''achat, facture et photo suivent chacune. Une nouvelle fiche peut reprendre une photo déjà présente dans l''inventaire au lieu de téléverser deux fois le même fichier.'),
('fr','help_rider','Ce qu''un organisateur doit savoir de votre technique, ainsi que l''affectation des canaux pour la console. Le plan de scène est à l''échelle : 8 × 6 m par défaut, et tout ce qui a une emprise réelle — praticables, amplis, retours, enceintes — est dessiné à sa taille. C''est ainsi qu''un organisateur voit si le groupe tient sur sa scène. « Générer depuis la liste des membres » dispose un modèle : batterie au centre au fond sur un praticable de 3 × 2 m, basse au fond à gauche, chant devant, plus l''alimentation et le boîtier de scène. Tout peut ensuite être déplacé ou saisi dans les champs numériques. Qui décoche « Je suis sur scène » dans son profil n''est pas placé — c''est là que va la technique. La figure qu''un membre obtient sur le plan, il la choisit lui-même dans son profil ; avec « Ma photo », c''est sa photo de profil qui y figure.'),
('fr','help_fotos','Photos pour la page publique et pour les archives.'),
('fr','help_musik','Vidéos et streams qui apparaissent sur la page musique publique.'),
('fr','help_downloads','Matériel de presse pour les organisateurs — avec un lien à transmettre.'),
('fr','help_mitglieder','Qui fait partie du groupe, avec coordonnées et rôles.'),

('es','inav_musik','Música y vídeos'),('es','inav_hilfe','Ayuda'),
('es','fl_media_saved','Enlace guardado.'),('es','fl_media_deleted','Enlace eliminado.'),
('es','help_title','Ayuda'),('es','help_intro','¿Qué hay detrás de cada área?'),
('es','help_more','Más sobre la aplicación, la licencia y quienes colaboran, en «Acerca de».'),
('es','help_termine','Todos los conciertos, ensayos y reuniones. Cada uno confirma o cancela, los archivos y comentarios cuelgan del evento, y la lista dice qué equipo se lleva.'),
('es','help_songs','El repertorio con tonalidad, tempo, duración y estado. Partituras, letras y grabaciones cuelgan de la canción.'),
('es','help_setlists','El orden de un concierto, con descansos, presentaciones y propinas. El tiempo de juego se suma de las duraciones. Hay tres separadores con sentidos distintos: un **descanso** divide la noche — la banda deja el escenario y al imprimir empieza una hoja nueva. Una **presentacion** separa dentro de la hoja: no se toca, se habla — presentar la banda, afinar. Se dibuja como linea de puntos, como el trazo en una hoja de papel, con el anuncio dentro. La **linea de propina** separa lo que solo se toca si el publico lo pide. Y la **llave** agrupa canciones que se tocan seguidas, con una indicacion para todas. Indicacion y anuncio pertenecen a la lista, no a la cancion. Solo las canciones llevan numero. Para el escenario, el boton "Teleprompter" arranca todo el set: empieza con la primera cancion que tiene letra, y al acabar una aparece la siguiente por si sola; un toque en el texto la pone en marcha. En la vista de impresion, "Imprimir tambien" decide que aparece junto al titulo: interprete, ano, tempo, duracion y la nota de la cancion (primera linea o completa). Tu dispositivo recuerda la eleccion.'),
('es','help_abwesenheiten','Vacaciones y fechas bloqueadas. Si un evento cae dentro, la lista avisa.'),
('es','help_aufgaben','Qué hay que hacer y quién lo hace.'),
('es','help_themen','Discusiones con calma, sin que nada se pierda en un chat.'),
('es','help_kasse','Ingresos y gastos del grupo; los caches se pueden tomar de los eventos.'),
('es','help_equipment','El inventario con componentes, precios y plazos como revisiones o seguros. Una ficha equivale a un aparato: dos micrófonos iguales son dos fichas, numeradas «#1» y «#2», porque se transportan, se prestan y se echan en falta de uno en uno. Para piezas pequeñas y material a metros está el campo «Cantidad»: diez fundas XLR no son diez fichas. Si una línea lleva una cantidad aunque sean aparatos, «Separar en aparatos individuales» crea fichas independientes; precio, fecha de compra, factura y foto pasan a cada una. Una ficha nueva puede adoptar una foto que ya está en el inventario en lugar de subir el mismo archivo dos veces.'),
('es','help_rider','Lo que un organizador necesita saber de vuestra técnica, además de la asignación de canales para la mesa. El plano de escenario está a escala: 8 × 6 m por defecto, y todo lo que ocupa sitio de verdad —tarimas, amplificadores, monitores, cajas— se dibuja con su medida. Así un organizador ve si la banda cabe en su escenario. «Generar desde la lista de miembros» coloca una plantilla: batería al fondo en el centro sobre una tarima de 3 × 2 m, bajo al fondo a la izquierda, voces delante, más corriente y caja de escenario. Después todo se puede arrastrar o escribir en los campos numéricos. Quien desmarca «Estoy en el escenario» en su perfil no se coloca: ahí va la técnica. La figura que recibe un miembro en el plano la elige él mismo en su perfil; con «Mi foto» aparece ahí su foto de perfil.'),
('es','help_fotos','Fotos para la página pública y para el archivo.'),
('es','help_musik','Vídeos y streams que aparecen en la página pública de música.'),
('es','help_downloads','Material de prensa para promotores — con enlace para compartir.'),
('es','help_mitglieder','Quién está en el grupo, con datos de contacto y roles.'),

('nl','inav_musik','Muziek & video''s'),('nl','inav_hilfe','Hulp'),
('nl','fl_media_saved','Link opgeslagen.'),('nl','fl_media_deleted','Link verwijderd.'),
('nl','help_title','Hulp'),('nl','help_intro','Wat zit er achter elk onderdeel?'),
('nl','help_more','Meer over de app, de licentie en de medewerkers staat onder "Over".'),
('nl','help_termine','Alle optredens, repetities en overleggen. Iedereen zegt toe of af, bestanden en reacties hangen aan de afspraak, en de paklijst zegt welke apparatuur meegaat.'),
('nl','help_songs','Het repertoire met toonsoort, tempo, duur en status. Bladmuziek, teksten en opnames hangen aan het nummer.'),
('nl','help_setlists','De volgorde voor een optreden, met pauzes, praatpauzes en toegiften. De speeltijd telt op uit de nummerduren. Drie scheidingen met verschillende betekenis: een **pauze** deelt de avond — de band gaat van het podium en in de afdruk begint een nieuw blad. Een **praatpauze** scheidt binnen het blad: er wordt niet gespeeld maar gesproken — de band voorstellen, stemmen. Ze staat als streepjeslijn, met de aankondiging erin. De **toegiftlijn** scheidt wat alleen gespeeld wordt als het publiek erom vraagt. En de **accolade** bindt nummers samen die aan een stuk gespeeld worden, met een aanwijzing voor alles erbinnen. Aanwijzing en aankondiging horen bij de setlijst, niet bij het nummer. Alleen nummers krijgen een cijfer. Voor het podium start de knop "Teleprompter" de hele set: hij begint bij het eerste nummer met tekst, en aan het eind van een nummer komt het volgende er zelf bovenaan — een tik op de tekst start het. In de afdrukweergave bepaalt "Ook afdrukken" bovenaan wat er naast de titel staat: artiest, jaar, tempo, speelduur en de notitie (eerste regel of compleet). Je apparaat onthoudt de keuze.'),
('nl','help_abwesenheiten','Vakanties en geblokkeerde dagen. Valt een afspraak erin, dan waarschuwt de lijst.'),
('nl','help_aufgaben','Wat er moet gebeuren en wie het doet.'),
('nl','help_themen','Rustig overleggen, zonder dat iets in een chat verdwijnt.'),
('nl','help_kasse','Inkomsten en uitgaven van de band; gages kun je uit de afspraken overnemen.'),
('nl','help_equipment','De inventaris met onderdelen, prijzen en termijnen zoals keuringen of verzekeringen. Eén item staat voor één apparaat: twee dezelfde microfoons zijn twee items, genummerd "#1" en "#2", want ze worden afzonderlijk gedragen, uitgeleend en gemist. Voor kleine onderdelen en materiaal per meter is er in plaats daarvan het veld "Aantal" — tien XLR-tulen zijn geen tien items. Staat er in een regel een aantal terwijl het apparaten zijn, dan maakt "In afzonderlijke apparaten splitsen" er losse items van; prijs, aankoopdatum, factuur en foto gaan met elk mee. Een nieuw item kan een foto overnemen die al in de inventaris staat, in plaats van hetzelfde bestand twee keer te uploaden.'),
('nl','help_rider','Wat een organisator over jullie techniek moet weten, plus de kanaalindeling voor de tafel. Het podiumplan is op schaal: standaard 8 × 6 m, en alles met een echte voetafdruk — podesten, versterkers, monitors, kasten — wordt op maat getekend. Zo ziet een organisator of de band op zijn podium past. "Genereren uit de ledenlijst" zet een sjabloon op: drums achter in het midden op een podest van 3 × 2 m, bas achter links, zang vooraan, plus stroom en een stagebox. Daarna kan alles versleept of in de getalvelden ingetypt worden. Wie in zijn profiel "Ik sta op het podium" uitvinkt, wordt niet geplaatst — daar hoort de techniek. Welke figuur een lid op het plan krijgt, kiest het zelf in het profiel; met "Mijn foto" staat daar de profielfoto.'),
('nl','help_fotos','Foto''s voor de publieke pagina en voor het archief.'),
('nl','help_musik','Video''s en streams die op de publieke muziekpagina verschijnen.'),
('nl','help_downloads','Persmateriaal voor organisatoren — met een link om door te geven.'),
('nl','help_mitglieder','Wie er in de band zit, met contactgegevens en rollen.'),

('it','inav_musik','Musica e video'),('it','inav_hilfe','Aiuto'),
('it','fl_media_saved','Link salvato.'),('it','fl_media_deleted','Link eliminato.'),
('it','help_title','Aiuto'),('it','help_intro','Che cosa c''è dietro ogni area?'),
('it','help_more','Altro sull''applicazione, la licenza e chi ha contribuito si trova sotto «Informazioni».'),
('it','help_termine','Tutti i concerti, le prove e le riunioni. Ognuno conferma o declina, file e commenti stanno sull''evento, e la lista dice quale attrezzatura viene portata.'),
('it','help_songs','Il repertorio con tonalità, tempo, durata e stato. Spartiti, testi e registrazioni stanno sul brano.'),
('it','help_setlists','L''ordine di un concerto, con pause, presentazioni e bis. Il tempo di gioco si somma dalle durate. Tre separatori con significati diversi: una **pausa** divide la serata — la band lascia il palco e in stampa comincia un foglio nuovo. Una **presentazione** separa dentro il foglio: non si suona, si parla — presentare la band, riaccordare. Si disegna tratteggiata, come il tratto su un foglio di carta, con l''annuncio dentro. La **linea del bis** separa cio che si suona solo se il pubblico lo chiede. E la **graffa** unisce i pezzi suonati di seguito, con un''indicazione per tutti. Indicazione e annuncio appartengono alla scaletta, non al pezzo. Solo i pezzi hanno un numero. Per il palco il pulsante "Teleprompter" avvia tutta la scaletta: parte dal primo pezzo che ha un testo, e alla fine di un pezzo arriva il successivo da solo; un tocco sul testo lo avvia. Nella stampa, "Stampa anche" in alto decide cosa sta accanto al titolo: interprete, anno, tempo, durata e la nota del pezzo (prima riga o completa). Il dispositivo ricorda la scelta.'),
('it','help_abwesenheiten','Ferie e date bloccate. Se un evento ci cade dentro, la lista avvisa.'),
('it','help_aufgaben','Che cosa c''è da fare e chi lo fa.'),
('it','help_themen','Discussioni con calma, senza che nulla si perda in una chat.'),
('it','help_kasse','Entrate e uscite della band; i cachet si riprendono dagli eventi.'),
('it','help_equipment','L''inventario con componenti, prezzi e scadenze come revisioni o assicurazioni. Una scheda corrisponde a un apparecchio: due microfoni identici sono due schede, numerate «#1» e «#2», perché si trasportano, si prestano e si perdono uno per uno. Per la minuteria e il materiale a metro c''è invece il campo «Quantità»: dieci gommini XLR non sono dieci schede. Se una riga porta una quantità pur trattandosi di apparecchi, «Dividi in apparecchi singoli» ne ricava schede distinte; prezzo, data d''acquisto, fattura e foto passano a ciascuna. Una scheda nuova può riprendere una foto già presente nell''inventario invece di caricare due volte lo stesso file.'),
('it','help_rider','Ciò che un organizzatore deve sapere della vostra tecnica, più l''assegnazione dei canali per il mixer. La piantina del palco è in scala: 8 × 6 m per impostazione predefinita, e tutto ciò che ha un ingombro reale — pedane, amplificatori, monitor, casse — è disegnato nella sua misura. Così un organizzatore vede se la band sta sul suo palco. «Genera dall''elenco dei membri» dispone un modello: batteria in fondo al centro su una pedana di 3 × 2 m, basso in fondo a sinistra, voci davanti, più corrente e stagebox. Poi tutto si può trascinare o digitare nei campi numerici. Chi nel proprio profilo togli la spunta a «Sono sul palco» non viene posizionato: lì va la tecnica. La figura che un membro ottiene sulla piantina la scegli lui stesso nel profilo; con «La mia foto» compare lì la foto del profilo.'),
('it','help_fotos','Foto per la pagina pubblica e per l''archivio.'),
('it','help_musik','Video e stream che compaiono sulla pagina pubblica della musica.'),
('it','help_downloads','Materiale stampa per gli organizzatori — con un link da passare.'),
('it','help_mitglieder','Chi fa parte della band, con contatti e ruoli.')
ON DUPLICATE KEY UPDATE value = value;
