-- UI translations for the favicon branding option (EN/NL/FR/ES/IT).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_favicon_lbl','Site icon / favicon (square PNG, max. 5 MB)'),
('en','set_favicon_remove','Remove favicon'),
('nl','set_favicon_lbl','Site-icoon / favicon (vierkante PNG, max. 5 MB)'),
('nl','set_favicon_remove','Favicon verwijderen'),
('fr','set_favicon_lbl','Icône du site / favicon (PNG carré, max. 5 Mo)'),
('fr','set_favicon_remove','Supprimer le favicon'),
('es','set_favicon_lbl','Icono del sitio / favicon (PNG cuadrado, máx. 5 MB)'),
('es','set_favicon_remove','Quitar favicon'),
('it','set_favicon_lbl','Icona del sito / favicon (PNG quadrato, max 5 MB)'),
('it','set_favicon_remove','Rimuovi favicon')
ON DUPLICATE KEY UPDATE value = VALUES(value);
