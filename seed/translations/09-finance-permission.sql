-- UI translations for the treasury permission (EN/NL/FR/ES/IT).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_finance','Manages the treasury (finance)'),
('en','fin_badge','Finance'),
('en','fin_readonly_hint','Entries are made by whoever manages the treasury (finance) — you can view everything here.'),
('en','fl_finance_required','Only the treasury manager (finance) can make entries.'),
('nl','mem_finance','Beheert de kas (financiën)'),
('nl','fin_badge','Financiën'),
('nl','fin_readonly_hint','Boekingen doet degene die de kas beheert (financiën) — jij kunt hier alles inzien.'),
('nl','fl_finance_required','Alleen de kasbeheerder (financiën) mag boeken.'),
('fr','mem_finance','Gère la caisse (finances)'),
('fr','fin_badge','Finances'),
('fr','fin_readonly_hint','Les écritures sont faites par la personne qui gère la caisse (finances) — tu peux tout consulter ici.'),
('fr','fl_finance_required','Seule la personne qui gère la caisse (finances) peut enregistrer des écritures.'),
('es','mem_finance','Gestiona la caja (finanzas)'),
('es','fin_badge','Finanzas'),
('es','fin_readonly_hint','Los apuntes los hace quien gestiona la caja (finanzas) — aquí puedes consultarlo todo.'),
('es','fl_finance_required','Solo quien gestiona la caja (finanzas) puede registrar apuntes.'),
('it','mem_finance','Gestisce la cassa (finanze)'),
('it','fin_badge','Finanze'),
('it','fin_readonly_hint','Le registrazioni le fa chi gestisce la cassa (finanze) — qui puoi consultare tutto.'),
('it','fl_finance_required','Solo chi gestisce la cassa (finanze) può registrare movimenti.')
ON DUPLICATE KEY UPDATE value = value;
