-- Warning before the OneDrive client secret expires (#339), the calendar
-- button on date fields (#343), and the reminder mail itself (#345).
--
-- The mail matters most here: it goes to the one person who can renew the
-- secret, and without these lines it would arrive in German whatever language
-- that person reads.
-- Der Hinweistext nannte nur zwei der drei Mahnstufen und wusste nichts vom
-- Rueckfall auf den Eintragetag (#354). Weil der frueheste Seed gewinnt, muss
-- die alte Fassung einmal weg - und zwar HIER, nicht in einem spaeteren Seed,
-- der sonst loescht, was diese Datei gerade eingesetzt hat.
DELETE FROM translations WHERE tkey = 'od_secret_expires_hint'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'od_hint_v2') = 0;
INSERT INTO settings (`key`, value) VALUES ('od_hint_v2', '1')
  ON DUPLICATE KEY UPDATE value = value;
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','date_pick','Open calendar'),
('en','od_secret_expires','Secret valid until'),
('en','od_secret_expires_hint','Azure shows this date when the secret is created; 24 months is the maximum. Leaving it empty is fine — Bandregie then counts 24 months from the day the secret was entered, and would rather warn too early than not at all. Reminders go to the band leadership 30 and 7 days beforehand and on the day it lapses.'),
('en','od_secret_subject','OneDrive: the secret expires in %1 days'),
('en','od_secret_subject_over','OneDrive: the secret has expired'),
('en','od_secret_body','the secret of the Microsoft application expires in %1 days, on %2. After that Bandregie can no longer reach the OneDrive: no new pictures, no storage, and no word about it.'),
('en','od_secret_body_over','the secret of the Microsoft application expired on %1. Bandregie has not been able to reach the OneDrive since.'),
('en','od_secret_howto','A new one is created in the Azure portal under App registrations › the application › Certificates & secrets. The value is shown only once. It belongs here together with the new expiry date:'),
('en','sys_od_secret','OneDrive secret'),
('en','sys_od_secret_left','%d days left, until %s'),
('en','sys_od_secret_over','expired on %s — OneDrive has stopped'),
('en','sys_od_secret_none','no expiry date on record'),
('en','sys_od_secret_none_hint','Every Azure secret expires, after 24 months at the latest. Without the date nobody can warn here, and the outage arrives unannounced. Add it under Set up OneDrive.'),
('en','sys_od_secret_hint','Create a new secret in the Azure portal and enter it, with its new expiry date, under Set up OneDrive. Once it expires Bandregie fetches no more files.'),
('en','fl_od_expires_invalid','That is not a valid date — the expiry date was left unchanged.'),

('nl','date_pick','Kalender openen'),
('nl','od_secret_expires','Geheim geldig tot'),
('nl','od_secret_expires_hint','Azure toont deze datum bij het aanmaken van het geheim; langer dan 24 maanden kan niet. Leeg laten mag — Bandregie rekent dan 24 maanden vanaf de dag waarop het geheim is ingevoerd, en waarschuwt liever te vroeg dan helemaal niet. Er wordt 30 en 7 dagen van tevoren gewaarschuwd en op de dag zelf, per mail aan de bandleiding.'),
('nl','od_secret_subject','OneDrive: het geheim verloopt over %1 dagen'),
('nl','od_secret_subject_over','OneDrive: het geheim is verlopen'),
('nl','od_secret_body','het geheim van de Microsoft-toepassing verloopt over %1 dagen, op %2. Daarna kan Bandregie niet meer bij de OneDrive: geen nieuwe foto''s, geen opslag en geen melding daarover.'),
('nl','od_secret_body_over','het geheim van de Microsoft-toepassing is op %1 verlopen. Bandregie kan sindsdien niet meer bij de OneDrive.'),
('nl','od_secret_howto','Een nieuw geheim maak je in het Azure-portaal onder App-registraties › de toepassing › Certificaten en geheimen. De waarde is maar één keer te zien. Die hoort hier, samen met de nieuwe vervaldatum:'),
('nl','sys_od_secret','OneDrive-geheim'),
('nl','sys_od_secret_left','nog %d dagen, tot %s'),
('nl','sys_od_secret_over','op %s verlopen — OneDrive staat stil'),
('nl','sys_od_secret_none','geen vervaldatum vastgelegd'),
('nl','sys_od_secret_none_hint','Elk Azure-geheim verloopt, uiterlijk na 24 maanden. Zonder de datum kan hier niemand waarschuwen en komt de storing onaangekondigd. Vul hem aan onder OneDrive instellen.'),
('nl','sys_od_secret_hint','Maak in het Azure-portaal een nieuw geheim aan en vul het, met de nieuwe vervaldatum, in onder OneDrive instellen. Verloopt het, dan haalt Bandregie geen bestanden meer op.'),
('nl','fl_od_expires_invalid','Dat is geen geldige datum — de vervaldatum bleef ongewijzigd.'),

('fr','date_pick','Ouvrir le calendrier'),
('fr','od_secret_expires','Secret valable jusqu''au'),
('fr','od_secret_expires_hint','Azure affiche cette date au moment où le secret est créé ; 24 mois est le maximum. La laisser vide est possible — Bandregie compte alors 24 mois à partir du jour où le secret a été saisi, et préfère prévenir trop tôt que pas du tout. Les rappels partent vers la direction du groupe 30 et 7 jours avant, et le jour même.'),
('fr','od_secret_subject','OneDrive : le secret expire dans %1 jours'),
('fr','od_secret_subject_over','OneDrive : le secret a expiré'),
('fr','od_secret_body','le secret de l''application Microsoft expire dans %1 jours, le %2. Ensuite, Bandregie n''atteint plus le OneDrive : plus de nouvelles photos, plus de dépôt, et aucun message à ce sujet.'),
('fr','od_secret_body_over','le secret de l''application Microsoft a expiré le %1. Depuis, Bandregie n''atteint plus le OneDrive.'),
('fr','od_secret_howto','Un nouveau se crée dans le portail Azure sous Inscriptions d''applications › l''application › Certificats et secrets. La valeur n''est visible qu''une seule fois. Elle va ici, avec la nouvelle date d''expiration :'),
('fr','sys_od_secret','Secret OneDrive'),
('fr','sys_od_secret_left','encore %d jours, jusqu''au %s'),
('fr','sys_od_secret_over','expiré le %s — OneDrive est à l''arrêt'),
('fr','sys_od_secret_none','aucune date d''expiration enregistrée'),
('fr','sys_od_secret_none_hint','Tout secret Azure expire, au plus tard au bout de 24 mois. Sans la date, personne ne peut prévenir ici, et la panne arrive sans annonce. À compléter sous Configurer OneDrive.'),
('fr','sys_od_secret_hint','Créer un nouveau secret dans le portail Azure et le saisir, avec sa nouvelle date d''expiration, sous Configurer OneDrive. Une fois expiré, Bandregie ne récupère plus aucun fichier.'),
('fr','fl_od_expires_invalid','Ce n''est pas une date valable — la date d''expiration est restée inchangée.'),

('es','date_pick','Abrir calendario'),
('es','od_secret_expires','Secreto válido hasta'),
('es','od_secret_expires_hint','Azure muestra esta fecha al crear el secreto; más de 24 meses no es posible. Dejarla vacía está bien — Bandregie cuenta entonces 24 meses desde el día en que se introdujo el secreto, y prefiere avisar demasiado pronto que no avisar. Los avisos van a la dirección de la banda 30 y 7 días antes y el mismo día.'),
('es','od_secret_subject','OneDrive: el secreto caduca en %1 días'),
('es','od_secret_subject_over','OneDrive: el secreto ha caducado'),
('es','od_secret_body','el secreto de la aplicación de Microsoft caduca en %1 días, el %2. Después Bandregie ya no llega al OneDrive: ni fotos nuevas, ni archivo, ni aviso alguno.'),
('es','od_secret_body_over','el secreto de la aplicación de Microsoft caducó el %1. Desde entonces Bandregie no llega al OneDrive.'),
('es','od_secret_howto','Uno nuevo se crea en el portal de Azure en Registros de aplicaciones › la aplicación › Certificados y secretos. El valor solo se ve una vez. Va aquí, junto con la nueva fecha de caducidad:'),
('es','sys_od_secret','Secreto de OneDrive'),
('es','sys_od_secret_left','quedan %d días, hasta el %s'),
('es','sys_od_secret_over','caducado el %s — OneDrive está parado'),
('es','sys_od_secret_none','sin fecha de caducidad registrada'),
('es','sys_od_secret_none_hint','Todo secreto de Azure caduca, como muy tarde a los 24 meses. Sin la fecha nadie puede avisar aquí y el fallo llega sin anunciarse. Añádela en Configurar OneDrive.'),
('es','sys_od_secret_hint','Crear un secreto nuevo en el portal de Azure e introducirlo, con su nueva fecha de caducidad, en Configurar OneDrive. Una vez caducado, Bandregie ya no recoge archivos.'),
('es','fl_od_expires_invalid','Eso no es una fecha válida — la fecha de caducidad no se modificó.'),

('it','date_pick','Apri calendario'),
('it','od_secret_expires','Segreto valido fino al'),
('it','od_secret_expires_hint','Azure mostra questa data quando il segreto viene creato; oltre 24 mesi non si può. Lasciarla vuota va bene — Bandregie conta allora 24 mesi dal giorno in cui il segreto è stato inserito, e preferisce avvisare troppo presto che non avvisare. Gli avvisi arrivano alla direzione della band 30 e 7 giorni prima e il giorno stesso.'),
('it','od_secret_subject','OneDrive: il segreto scade tra %1 giorni'),
('it','od_secret_subject_over','OneDrive: il segreto è scaduto'),
('it','od_secret_body','il segreto dell''applicazione Microsoft scade tra %1 giorni, il %2. Dopodiché Bandregie non raggiunge più il OneDrive: niente foto nuove, niente archivio e nessun avviso in proposito.'),
('it','od_secret_body_over','il segreto dell''applicazione Microsoft è scaduto il %1. Da allora Bandregie non raggiunge più il OneDrive.'),
('it','od_secret_howto','Uno nuovo si crea nel portale di Azure in Registrazioni app › l''applicazione › Certificati e segreti. Il valore si vede una volta sola. Va qui, insieme alla nuova data di scadenza:'),
('it','sys_od_secret','Segreto OneDrive'),
('it','sys_od_secret_left','ancora %d giorni, fino al %s'),
('it','sys_od_secret_over','scaduto il %s — OneDrive è fermo'),
('it','sys_od_secret_none','nessuna data di scadenza registrata'),
('it','sys_od_secret_none_hint','Ogni segreto di Azure scade, al più tardi dopo 24 mesi. Senza la data qui nessuno può avvisare e il guasto arriva senza preavviso. Da aggiungere in Configura OneDrive.'),
('it','sys_od_secret_hint','Creare un nuovo segreto nel portale di Azure e inserirlo, con la nuova data di scadenza, in Configura OneDrive. Una volta scaduto, Bandregie non preleva più file.'),
('it','fl_od_expires_invalid','Questa non è una data valida — la data di scadenza è rimasta invariata.')
ON DUPLICATE KEY UPDATE value = value;
