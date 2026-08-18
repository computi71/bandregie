-- Umsortieren per Ziehen (#17)
--
-- sl_drag_hint schickte Handynutzer zu den Pfeiltasten, weil HTML5-Drag dort
-- nicht feuerte. Seit #237 zieht der Griff auch am Finger, also ist der Satz
-- geändert. Weil der früheste Seed gewinnt (ON DUPLICATE KEY UPDATE value =
-- value), muss der alte Wert einmal weg — sonst stünde die alte Anleitung
-- weiter da.
DELETE FROM translations WHERE tkey = 'sl_drag_hint'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'sl_drag_hint_v2') = 0;
INSERT INTO settings (`key`, value) VALUES ('sl_drag_hint_v2', '1')
  ON DUPLICATE KEY UPDATE value = value;
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_drag_hint','Drag the ⠿ to reorder — with a mouse or a finger. The arrows work too.'),('en','sl_saved','Order saved'),
('nl','sl_drag_hint','Sleep aan de ⠿ om de volgorde te wijzigen — met muis of vinger. De pijlen werken ook.'),('nl','sl_saved','Volgorde opgeslagen'),
('fr','sl_drag_hint','Fais glisser le ⠿ pour réordonner — à la souris comme au doigt. Les flèches marchent aussi.'),('fr','sl_saved','Ordre enregistré'),
('es','sl_drag_hint','Arrastra el ⠿ para reordenar, con el ratón o con el dedo. Las flechas también funcionan.'),('es','sl_saved','Orden guardado'),
('it','sl_drag_hint','Trascina il ⠿ per riordinare, col mouse o col dito. Anche le frecce funzionano.'),('it','sl_saved','Ordine salvato')
ON DUPLICATE KEY UPDATE value = value;
