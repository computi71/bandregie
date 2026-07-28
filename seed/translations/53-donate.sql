-- A line on the about page saying where a tip can go.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','about_donate','Tip jar'),
('en','about_donate_link','for the project'),
('en','about_donate_note','Bandroadie is free and stays free. Anyone who would like to leave something may — nobody has to. Can be switched off in the settings.'),
('en','set_about_donate','Show the tip jar under "About"'),
('fr','about_donate','Pourboire'),
('fr','about_donate_link','pour le projet'),
('fr','about_donate_note','Bandroadie est gratuit et le restera. Qui veut laisser quelque chose le peut — personne n''y est tenu. Désactivable dans les réglages.'),
('fr','set_about_donate','Afficher le pourboire sous « À propos »'),
('es','about_donate','Propina'),
('es','about_donate_link','para el proyecto'),
('es','about_donate_note','Bandroadie es gratuito y lo seguirá siendo. Quien quiera dejar algo puede hacerlo; nadie tiene que. Se puede desactivar en los ajustes.'),
('es','set_about_donate','Mostrar la propina en «Acerca de»'),
('nl','about_donate','Fooi'),
('nl','about_donate_link','voor het project'),
('nl','about_donate_note','Bandroadie is gratis en blijft dat. Wie toch iets wil achterlaten mag dat — het hoeft niet. Uit te zetten in de instellingen.'),
('nl','set_about_donate','De fooi tonen onder "Over"'),
('it','about_donate','Mancia'),
('it','about_donate_link','per il progetto'),
('it','about_donate_note','Bandroadie è gratuito e resta tale. Chi vuole lasciare qualcosa può farlo, nessuno è tenuto. Disattivabile nelle impostazioni.'),
('it','set_about_donate','Mostrare la mancia in «Informazioni»')
ON DUPLICATE KEY UPDATE value = value;
