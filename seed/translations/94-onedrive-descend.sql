-- Absteigen in Unterordner (#205)
--
-- od_refreshed hat eine vierte Stelle bekommen und ist in 89 geändert. Weil der
-- früheste Seed gewinnt (ON DUPLICATE KEY UPDATE value = value), muss der alte
-- Wert einmal weg, sonst bliebe der Satz ohne die Ordnerzahl stehen.
DELETE FROM translations WHERE tkey = 'od_refreshed'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'od_refreshed_v2') = 0;
INSERT INTO settings (`key`, value) VALUES ('od_refreshed_v2', '1')
  ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','od_refreshed','Looked in %4 folders: %1 new, %2 changed, %3 gone.'),
('en','od_capped','Stopped at %1 files — there is more in there than one pass can take.'),
('en','od_too_deep','%1 folders lie deeper than %2 levels and were not looked at:'),
('en','od_part_unreachable','%1 folders did not answer — their files are left as they were.'),
('en','od_taken','with a taken date'),
('nl','od_refreshed','Gekeken in %4 mappen: %1 nieuw, %2 gewijzigd, %3 verdwenen.'),
('nl','od_capped','Gestopt bij %1 bestanden — er zit meer in dan één ronde aankan.'),
('nl','od_too_deep','%1 mappen liggen dieper dan %2 niveaus en zijn niet bekeken:'),
('nl','od_part_unreachable','%1 mappen antwoordden niet — hun bestanden blijven ongewijzigd.'),
('nl','od_taken','met opnamedatum'),
('fr','od_refreshed','Vu dans %4 dossiers : %1 nouveaux, %2 modifiés, %3 disparus.'),
('fr','od_capped','Arrêté à %1 fichiers — il y en a plus que ce qu''un passage peut prendre.'),
('fr','od_too_deep','%1 dossiers sont plus profonds que %2 niveaux et n''ont pas été regardés :'),
('fr','od_part_unreachable','%1 dossiers n''ont pas répondu — leurs fichiers restent inchangés.'),
('fr','od_taken','avec date de prise'),
('es','od_refreshed','Mirado en %4 carpetas: %1 nuevos, %2 cambiados, %3 desaparecidos.'),
('es','od_capped','Detenido en %1 archivos — hay más de lo que cabe en una pasada.'),
('es','od_too_deep','%1 carpetas están más profundas que %2 niveles y no se miraron:'),
('es','od_part_unreachable','%1 carpetas no respondieron — sus archivos quedan como estaban.'),
('es','od_taken','con fecha de captura'),
('it','od_refreshed','Guardato in %4 cartelle: %1 nuovi, %2 modificati, %3 spariti.'),
('it','od_capped','Fermato a %1 file — dentro c''è più di quanto un passaggio possa prendere.'),
('it','od_too_deep','%1 cartelle sono più profonde di %2 livelli e non sono state guardate:'),
('it','od_part_unreachable','%1 cartelle non hanno risposto — i loro file restano invariati.'),
('it','od_taken','con data di scatto')
ON DUPLICATE KEY UPDATE value = value;
