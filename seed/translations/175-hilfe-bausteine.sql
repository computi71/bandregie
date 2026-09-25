-- Die Hilfe zum Vertragsbereich, nachgezogen (#359, #363).
--
-- help_vertraege_3 sagte „aus eurer Vorlage gebildet". Seit der Vertrag aus
-- Bausteinen entsteht, stimmt das nur noch für Bands, die einen eigenen Text
-- eingetragen haben — also für den Ausnahmefall.
--
-- DELETE davor, weil die INSERTs unten mit ON DUPLICATE KEY UPDATE value=value
-- laufen: Ohne das Löschen bliebe in jeder bestehenden Anlage der alte Satz
-- stehen, und die Hilfe erklärte weiter etwas, das es nicht mehr gibt.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'help_vertraege_3';

INSERT INTO translations (lang, tkey, value) VALUES
('en','help_vertraege_3','The wording is built from blocks - single points you tick on and off at the contract: hotel yes, you bring the PA yourselves, a share of the door instead of a flat fee. The paragraphs renumber themselves, so no gap is left behind. Once it goes out, the text stands: what was signed must not change because somebody touches the blocks half a year later. While a contract is a draft you can change both - the points and the text underneath.'),
('en','help_vertraege_5','Which language a sheet comes out in hangs on the promoter, not on who presses print. On the contract the language only turns the headings and the dates, though: the clauses stay as they are written in your blocks.'),

('nl','help_vertraege_3','De tekst wordt uit bouwstenen opgebouwd - losse punten die je bij het contract aan- en uitvinkt: hotel ja, de PA nemen jullie zelf mee, een percentage in plaats van een vaste gage. De artikelen hernummeren zichzelf, er blijft geen gat achter. Eenmaal verstuurd staat de tekst vast: wat getekend is mag niet veranderen omdat iemand een half jaar later aan de bouwstenen komt. Zolang een contract concept is kun je allebei wijzigen - de punten en de tekst eronder.'),
('nl','help_vertraege_5','In welke taal een blad eruit komt hangt af van de organisator, niet van wie er op afdrukken drukt. Bij het contract wisselt de taal alleen de kopjes en de datums om: de bepalingen blijven zoals ze in jullie bouwstenen staan.'),

('fr','help_vertraege_3','Le texte se compose de blocs - des points que vous cochez et décochez sur le contrat : hôtel oui, vous apportez la sono vous-mêmes, un pourcentage plutôt qu''un cachet fixe. Les articles se renumérotent tout seuls, sans laisser de trou. Une fois parti, le texte est figé : ce qui a été signé ne doit pas changer parce que quelqu''un touche aux blocs six mois plus tard. Tant qu''un contrat est un brouillon, vous pouvez modifier les deux - les points et le texte en dessous.'),
('fr','help_vertraege_5','La langue dans laquelle sort une feuille dépend de l''organisateur, pas de qui appuie sur imprimer. Sur le contrat, la langue ne change toutefois que les titres et les dates : les clauses restent telles qu''elles sont écrites dans vos blocs.'),

('es','help_vertraege_3','El texto se compone de bloques: puntos sueltos que marcáis y desmarcáis en el contrato — hotel sí, el equipo lo lleváis vosotros, un porcentaje en lugar de un caché fijo. Los artículos se renumeran solos, sin dejar hueco. Una vez enviado, el texto queda fijo: lo que se firmó no puede cambiar porque medio año después alguien toque los bloques. Mientras un contrato sea borrador podéis cambiar las dos cosas: los puntos y el texto de abajo.'),
('es','help_vertraege_5','El idioma en que sale una hoja depende del organizador, no de quién le dé a imprimir. En el contrato, eso sí, el idioma solo cambia los encabezados y las fechas: las cláusulas se quedan como están escritas en vuestros bloques.'),

('it','help_vertraege_3','Il testo si compone di voci: singoli punti che spuntate e togliete sul contratto — albergo sì, l''impianto lo portate voi, una percentuale invece di un compenso fisso. Gli articoli si rinumerano da soli, senza lasciare buchi. Una volta partito, il testo è fermo: quello che è stato firmato non deve cambiare perché sei mesi dopo qualcuno tocca le voci. Finché un contratto è una bozza potete cambiare entrambe le cose: i punti e il testo sotto.'),
('it','help_vertraege_5','In che lingua esce un foglio dipende dall''organizzatore, non da chi preme stampa. Sul contratto però la lingua cambia solo i titoli e le date: le clausole restano come sono scritte nelle vostre voci.')
ON DUPLICATE KEY UPDATE value = value;
