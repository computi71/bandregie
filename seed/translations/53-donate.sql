-- A line on the about page saying where a tip can go.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','about_donate','Tip jar'),
('en','about_donate_link','for the project'),
('en','about_donate_note','Bandroadie is free and stays free. Anyone who would like to leave something may — nobody has to.'),
('fr','about_donate','Pourboire'),
('fr','about_donate_link','pour le projet'),
('fr','about_donate_note','Bandroadie est gratuit et le restera. Qui veut laisser quelque chose le peut — personne n''y est tenu.'),
('es','about_donate','Propina'),
('es','about_donate_link','para el proyecto'),
('es','about_donate_note','Bandroadie es gratuito y lo seguirá siendo. Quien quiera dejar algo puede hacerlo; nadie tiene que.'),
('nl','about_donate','Fooi'),
('nl','about_donate_link','voor het project'),
('nl','about_donate_note','Bandroadie is gratis en blijft dat. Wie toch iets wil achterlaten mag dat — het hoeft niet.'),
('it','about_donate','Mancia'),
('it','about_donate_link','per il progetto'),
('it','about_donate_note','Bandroadie è gratuito e resta tale. Chi vuole lasciare qualcosa può farlo, nessuno è tenuto.')
ON DUPLICATE KEY UPDATE value = value;
