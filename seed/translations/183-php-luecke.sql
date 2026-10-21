-- Warnung vor PHP-Fassungen mit PHP2Shell (#389).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','sys_php_hole','known hole'),
('en','sys_php_hole_hint','This PHP version has a hole that lets a stranger run commands on the server (CVE-2026-109618 and CVE-2026-109619). The attack lands before Bandregie even starts, so the application can do nothing about it. Fixed from PHP %1; staying on %2 needs at least that release. Ask your host.'),
('en','sys_php_eol','no longer maintained'),
('en','sys_php_eol_hint','PHP %1 no longer gets security fixes of its own. There is no official release fixing CVE-2026-109618 and CVE-2026-109619 for it; some providers backport the fix, and the version number does not show that. Ask your host whether this server has it, or move to a newer branch.'),

('nl','sys_php_hole','bekend gat'),
('nl','sys_php_hole_hint','Deze PHP-versie heeft een gat waardoor een vreemde opdrachten op de server kan uitvoeren (CVE-2026-109618 en CVE-2026-109619). De aanval slaat toe voordat Bandregie überhaupt start, dus de toepassing kan er niets tegen doen. Verholpen vanaf PHP %1; wie op %2 wil blijven heeft minstens die versie nodig. Vraag het je hoster.'),
('nl','sys_php_eol','niet meer onderhouden'),
('nl','sys_php_eol_hint','PHP %1 krijgt zelf geen beveiligingsfixes meer. Er is daarvoor geen officiële versie die CVE-2026-109618 en CVE-2026-109619 verhelpt; sommige aanbieders zetten de fix terug, en dat zie je niet aan het versienummer. Vraag je hoster of deze server hem heeft, of stap over op een nieuwere tak.'),

('fr','sys_php_hole','faille connue'),
('fr','sys_php_hole_hint','Cette version de PHP comporte une faille permettant à un inconnu d''exécuter des commandes sur le serveur (CVE-2026-109618 et CVE-2026-109619). L''attaque frappe avant même le démarrage de Bandregie : l''application ne peut rien y faire. Corrigé à partir de PHP %1 ; rester en %2 exige au minimum cette version. Adressez-vous à votre hébergeur.'),
('fr','sys_php_eol','plus maintenu'),
('fr','sys_php_eol_hint','PHP %1 ne reçoit plus de correctifs de sécurité propres. Aucune version officielle n''y corrige CVE-2026-109618 ni CVE-2026-109619 ; certains fournisseurs rétroportent le correctif, ce que le numéro de version ne montre pas. Demandez à votre hébergeur si ce serveur l''a, ou passez à une branche plus récente.'),

('es','sys_php_hole','fallo conocido'),
('es','sys_php_hole_hint','Esta versión de PHP tiene un fallo que permite a un desconocido ejecutar órdenes en el servidor (CVE-2026-109618 y CVE-2026-109619). El ataque actúa antes de que Bandregie siquiera arranque, así que la aplicación no puede hacer nada. Corregido a partir de PHP %1; seguir en %2 exige al menos esa versión. Consultad a vuestro proveedor.'),
('es','sys_php_eol','ya sin mantenimiento'),
('es','sys_php_eol_hint','PHP %1 ya no recibe correcciones de seguridad propias. No hay versión oficial que corrija ahí CVE-2026-109618 y CVE-2026-109619; algunos proveedores adaptan la corrección, y el número de versión no lo muestra. Preguntad a vuestro proveedor si este servidor la tiene, o pasad a una rama más nueva.'),

('it','sys_php_hole','falla nota'),
('it','sys_php_hole_hint','Questa versione di PHP ha una falla che permette a un estraneo di eseguire comandi sul server (CVE-2026-109618 e CVE-2026-109619). L''attacco arriva prima ancora che Bandregie si avvii, quindi l''applicazione non può farci nulla. Risolto da PHP %1; restare su %2 richiede almeno quella versione. Rivolgetevi al vostro hoster.'),
('it','sys_php_eol','non più mantenuto'),
('it','sys_php_eol_hint','PHP %1 non riceve più correzioni di sicurezza proprie. Non esiste una versione ufficiale che vi risolva CVE-2026-109618 e CVE-2026-109619; alcuni fornitori riportano la correzione, e dal numero di versione non si vede. Chiedete al vostro hoster se questo server ce l''ha, oppure passate a un ramo più recente.')
ON DUPLICATE KEY UPDATE value = value;

-- Die Warnung auf der Übersicht (#389).
INSERT INTO translations (lang, tkey, value) VALUES
('en','dash_php_hole','The PHP on this server has a known hole'),
('en','dash_php_eol','The PHP on this server is no longer maintained'),
('en','dash_php_check','Go to the system check'),
('nl','dash_php_hole','De PHP op deze server heeft een bekend gat'),
('nl','dash_php_eol','De PHP op deze server wordt niet meer onderhouden'),
('nl','dash_php_check','Naar de systeemcontrole'),
('fr','dash_php_hole','Le PHP de ce serveur présente une faille connue'),
('fr','dash_php_eol','Le PHP de ce serveur n''est plus maintenu'),
('fr','dash_php_check','Vers la vérification du système'),
('es','dash_php_hole','El PHP de este servidor tiene un fallo conocido'),
('es','dash_php_eol','El PHP de este servidor ya no recibe mantenimiento'),
('es','dash_php_check','Ir a la comprobación del sistema'),
('it','dash_php_hole','Il PHP di questo server ha una falla nota'),
('it','dash_php_eol','Il PHP di questo server non è più mantenuto'),
('it','dash_php_check','Vai al controllo di sistema')
ON DUPLICATE KEY UPDATE value = value;
