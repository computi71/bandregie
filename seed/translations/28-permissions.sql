-- Translations for per-module permissions.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','perm_title','Permissions'),('en','perm_read','view'),('en','perm_write','edit'),
('en','perm_intro','Who may view and edit which area? Whoever may edit may also view.'),
('en','perm_admin_all','Admins may do everything — settings, translations and backups stay theirs alone.'),
('en','perm_template','Apply template'),('en','perm_tpl_member','Member'),('en','perm_tpl_ersatz','Substitute'),
('en','perm_tpl_hint','Resets every box to the default for that role.'),
('en','perm_open','Assign permissions'),
('en','fl_no_permission','You do not have permission for that.'),
('en','fl_perm_saved','Permissions saved.'),

('fr','perm_title','Droits'),('fr','perm_read','voir'),('fr','perm_write','modifier'),
('fr','perm_intro','Qui peut voir et modifier quel domaine ? Qui peut modifier peut aussi voir.'),
('fr','perm_admin_all','Les administrateurs peuvent tout — réglages, traductions et sauvegardes restent à eux seuls.'),
('fr','perm_template','Appliquer le modèle'),('fr','perm_tpl_member','Membre'),('fr','perm_tpl_ersatz','Remplaçant'),
('fr','perm_tpl_hint','Remet toutes les cases sur la valeur par défaut du rôle.'),
('fr','perm_open','Attribuer les droits'),
('fr','fl_no_permission','Tu n''as pas le droit de faire cela.'),
('fr','fl_perm_saved','Droits enregistrés.'),

('es','perm_title','Permisos'),('es','perm_read','ver'),('es','perm_write','editar'),
('es','perm_intro','¿Quién puede ver y editar cada área? Quien puede editar también puede ver.'),
('es','perm_admin_all','Los administradores pueden todo: ajustes, traducciones y copias siguen siendo solo suyos.'),
('es','perm_template','Aplicar plantilla'),('es','perm_tpl_member','Miembro'),('es','perm_tpl_ersatz','Sustituto'),
('es','perm_tpl_hint','Devuelve todas las casillas al valor por defecto del rol.'),
('es','perm_open','Asignar permisos'),
('es','fl_no_permission','No tienes permiso para eso.'),
('es','fl_perm_saved','Permisos guardados.'),

('nl','perm_title','Rechten'),('nl','perm_read','zien'),('nl','perm_write','wijzigen'),
('nl','perm_intro','Wie mag welk onderdeel zien en wijzigen? Wie mag wijzigen, mag ook zien.'),
('nl','perm_admin_all','Beheerders mogen alles — instellingen, vertalingen en back-ups blijven van hen.'),
('nl','perm_template','Sjabloon toepassen'),('nl','perm_tpl_member','Lid'),('nl','perm_tpl_ersatz','Invaller'),
('nl','perm_tpl_hint','Zet alle vinkjes terug op de standaard van die rol.'),
('nl','perm_open','Rechten toekennen'),
('nl','fl_no_permission','Daar heb je geen recht toe.'),
('nl','fl_perm_saved','Rechten opgeslagen.'),

('it','perm_title','Permessi'),('it','perm_read','vedere'),('it','perm_write','modificare'),
('it','perm_intro','Chi può vedere e modificare quale area? Chi può modificare può anche vedere.'),
('it','perm_admin_all','Gli amministratori possono tutto: impostazioni, traduzioni e backup restano solo loro.'),
('it','perm_template','Applica modello'),('it','perm_tpl_member','Membro'),('it','perm_tpl_ersatz','Sostituto'),
('it','perm_tpl_hint','Riporta tutte le caselle al valore predefinito del ruolo.'),
('it','perm_open','Assegna i permessi'),
('it','fl_no_permission','Non hai il permesso per questo.'),
('it','fl_perm_saved','Permessi salvati.')
ON DUPLICATE KEY UPDATE value = value;
