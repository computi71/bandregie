-- The default language, not German, is the one that cannot be switched off.
SET NAMES utf8mb4;

-- The old hint named German specifically. Seeds never overwrite, so the
-- superseded wording goes first — and only where it is still the seeded text.
DELETE FROM translations WHERE tkey = 'set_langs_hint' AND value IN (
  'Which languages appear in the website language menu. German is always active (fallback).',
  'Welke talen in het taalmenu van de website verschijnen. Duits is altijd actief (fallback).',
  'Les langues qui apparaissent dans le menu du site. L''allemand est toujours actif (fallback).',
  'Qué idiomas aparecen en el menú de idiomas de la web. El alemán siempre está activo (reserva).',
  'Quali lingue compaiono nel menu lingue del sito. Il tedesco è sempre attivo (fallback).'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_langs_hint','Which languages appear in the website language menu. The default language stays active, every other one you can switch off.'),
('en','set_langs_default_locked','default language'),
('fr','set_langs_hint','Les langues qui apparaissent dans le menu du site. La langue par défaut reste active, toutes les autres peuvent être désactivées.'),
('fr','set_langs_default_locked','langue par défaut'),
('es','set_langs_hint','Qué idiomas aparecen en el menú de idiomas de la web. El idioma predeterminado sigue activo, los demás podéis desactivarlos.'),
('es','set_langs_default_locked','idioma predeterminado'),
('nl','set_langs_hint','Welke talen in het taalmenu van de website verschijnen. De standaardtaal blijft actief, alle andere kun je uitzetten.'),
('nl','set_langs_default_locked','standaardtaal'),
('it','set_langs_hint','Quali lingue compaiono nel menu lingue del sito. La lingua predefinita resta attiva, tutte le altre potete disattivarle.'),
('it','set_langs_default_locked','lingua predefinita')
ON DUPLICATE KEY UPDATE value = value;
