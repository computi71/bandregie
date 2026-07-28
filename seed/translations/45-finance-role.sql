-- The finance role is granted, not inherited from being an admin.
SET NAMES utf8mb4;

-- The old note said admins may do everything. They no longer hold the
-- treasury by default, so the superseded wording goes first — and only where
-- it is still the seeded text.
DELETE FROM translations WHERE tkey = 'perm_admin_all' AND value IN (
  'Admins may do everything — settings, translations and backups stay theirs alone.',
  'Les administrateurs peuvent tout — réglages, traductions et sauvegardes restent à eux seuls.',
  'Los administradores pueden todo: ajustes, traducciones y copias siguen siendo solo suyos.',
  'Beheerders mogen alles — instellingen, vertalingen en back-ups blijven van hen.',
  'Gli amministratori possono tutto: impostazioni, traduzioni e backup restano solo loro.'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','perm_admin_all','Admins may do everything — settings, translations and backups stay theirs alone. Everything except the treasury: keeping it is a job of its own and is granted here.'),
('fr','perm_admin_all','Les administrateurs peuvent tout — réglages, traductions et sauvegardes restent à eux seuls. Tout sauf la caisse : la tenir est une fonction à part, attribuée ici.'),
('es','perm_admin_all','Los administradores pueden todo: ajustes, traducciones y copias siguen siendo solo suyos. Todo menos la caja: llevarla es una tarea propia y se concede aquí.'),
('nl','perm_admin_all','Beheerders mogen alles — instellingen, vertalingen en back-ups blijven van hen. Alles behalve de kas: die bijhouden is een eigen taak en wordt hier toegekend.'),
('it','perm_admin_all','Gli amministratori possono tutto: impostazioni, traduzioni e backup restano solo loro. Tutto tranne la cassa: tenerla è un compito a sé e si assegna qui.')
ON DUPLICATE KEY UPDATE value = value;
