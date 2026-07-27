-- Translations for the "About Bandroadie" page.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','about_title','About Bandroadie'),
('en','about_tagline','Website and organization for bands — events, setlists, songs, treasury, equipment.'),
('en','about_credits','Development'),('en','about_by','Developed by'),('en','about_contributors','Contributors'),
('en','about_thanks','Built by a band that would rather play loud than keep lists — because “who''s got the setlist again?” got old fast. Plenty of ideas still lying around.'),
('en','about_project','Project'),
('en','about_license','License'),('en','about_source','Source code'),('en','about_version','Version'),
('en','about_changelog','What''s new?'),('en','about_stack','Built with'),
('en','about_data_note','Everything in this instance — events, songs, photos, files — belongs to the band, not to the project.'),
('nl','about_title','Over Bandroadie'),
('nl','about_tagline','Website en organisatie voor bands — afspraken, setlists, nummers, kas, apparatuur.'),
('nl','about_credits','Ontwikkeling'),('nl','about_by','Ontwikkeld door'),('nl','about_contributors','Medewerkers'),
('nl','about_thanks','Gebouwd door een band die liever hard speelt dan lijstjes bijhoudt — omdat „wie heeft de setlist?” snel ging vervelen. Ideeën liggen er nog genoeg.'),
('nl','about_project','Project'),
('nl','about_license','Licentie'),('nl','about_source','Broncode'),('nl','about_version','Versie'),
('nl','about_changelog','Wat is er nieuw?'),('nl','about_stack','Gebouwd met'),
('nl','about_data_note','Alles in deze installatie — afspraken, nummers, foto''s, bestanden — is van de band, niet van het project.'),
('fr','about_title','À propos de Bandroadie'),
('fr','about_tagline','Site et organisation pour groupes — dates, setlists, chansons, caisse, équipement.'),
('fr','about_credits','Développement'),('fr','about_by','Développé par'),('fr','about_contributors','Contributeurs'),
('fr','about_thanks','Fait par un groupe qui préfère jouer fort que tenir des listes — parce que « qui a la setlist ? », on l''a assez entendu. Des idées, il en reste plein.'),
('fr','about_project','Projet'),
('fr','about_license','Licence'),('fr','about_source','Code source'),('fr','about_version','Version'),
('fr','about_changelog','Quoi de neuf ?'),('fr','about_stack','Construit avec'),
('fr','about_data_note','Tout ce qui se trouve dans cette instance — dates, chansons, photos, fichiers — appartient au groupe, pas au projet.'),
('es','about_title','Acerca de Bandroadie'),
('es','about_tagline','Web y organización para bandas — eventos, setlists, canciones, caja, equipo.'),
('es','about_credits','Desarrollo'),('es','about_by','Desarrollado por'),('es','about_contributors','Colaboradores'),
('es','about_thanks','Hecho por una banda que prefiere tocar fuerte antes que llevar listas — porque «¿quién tiene la setlist?» ya cansaba. Ideas quedan de sobra.'),
('es','about_project','Proyecto'),
('es','about_license','Licencia'),('es','about_source','Código fuente'),('es','about_version','Versión'),
('es','about_changelog','¿Qué hay de nuevo?'),('es','about_stack','Hecho con'),
('es','about_data_note','Todo lo que hay en esta instalación — eventos, canciones, fotos, archivos — pertenece a la banda, no al proyecto.'),
('it','about_title','Informazioni su Bandroadie'),
('it','about_tagline','Sito e organizzazione per band — eventi, scalette, brani, cassa, attrezzatura.'),
('it','about_credits','Sviluppo'),('it','about_by','Sviluppato da'),('it','about_contributors','Collaboratori'),
('it','about_thanks','Fatto da una band che preferisce suonare forte invece di tenere elenchi — perché «chi ha la scaletta?» aveva stufato. Di idee ne restano parecchie.'),
('it','about_project','Progetto'),
('it','about_license','Licenza'),('it','about_source','Codice sorgente'),('it','about_version','Versione'),
('it','about_changelog','Novità'),('it','about_stack','Realizzato con'),
('it','about_data_note','Tutto ciò che si trova in questa installazione — eventi, brani, foto, file — appartiene alla band, non al progetto.')
ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','about_settings_hint','Version, license, source code and who is behind it.'),('en','about_open','Open'),
('en','set_copyright','Copyright line in the footer'),
('en','set_copyright_hint','Leave empty for "© year band name" — the year is filled in automatically.'),
('nl','about_settings_hint','Versie, licentie, broncode en wie erachter zit.'),('nl','about_open','Openen'),
('nl','set_copyright','Copyrightregel in de voettekst'),
('nl','set_copyright_hint','Leeg laten voor "© jaar bandnaam" — het jaar wordt automatisch ingevuld.'),
('fr','about_settings_hint','Version, licence, code source et qui est derrière.'),('fr','about_open','Ouvrir'),
('fr','set_copyright','Ligne de copyright dans le pied de page'),
('fr','set_copyright_hint','Laisser vide pour « © année nom du groupe » — l''année est ajoutée automatiquement.'),
('es','about_settings_hint','Versión, licencia, código fuente y quién está detrás.'),('es','about_open','Abrir'),
('es','set_copyright','Línea de copyright en el pie'),
('es','set_copyright_hint','Déjalo vacío para "© año nombre de la banda" — el año se rellena solo.'),
('it','about_settings_hint','Versione, licenza, codice sorgente e chi c''è dietro.'),('it','about_open','Apri'),
('it','set_copyright','Riga di copyright nel piè di pagina'),
('it','set_copyright_hint','Lascia vuoto per "© anno nome della band" — l''anno viene inserito automaticamente.')
ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','about_license_note','Free to use for your own band — only offering it as a commercial service is reserved to the author.'),
('nl','about_license_note','Vrij te gebruiken voor je eigen band — alleen het aanbieden als commerciële dienst is voorbehouden aan de auteur.'),
('fr','about_license_note','Libre d''utilisation pour votre propre groupe — seule la proposition comme service commercial est réservée à l''auteur.'),
('es','about_license_note','Libre para usar con tu propia banda — solo ofrecerlo como servicio comercial queda reservado al autor.'),
('it','about_license_note','Libero per la propria band — solo l''offerta come servizio commerciale è riservata all''autore.')
ON DUPLICATE KEY UPDATE value = value;
