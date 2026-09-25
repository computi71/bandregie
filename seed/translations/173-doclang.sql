-- Die Sprache ausgehender Blätter (#363).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','promoter_lang','Language of the paperwork'),
('en','promoter_lang_band','Band language'),
('en','promoter_lang_hint','Contract, invoice and quote for this promoter come out in this language, no matter who prints them.'),
('en','doclang_contract_note','The language switches headings and dates. The clauses stay as they are written in the blocks.'),

('nl','promoter_lang','Taal van de documenten'),
('nl','promoter_lang_band','Taal van de band'),
('nl','promoter_lang_hint','Contract, factuur en offerte voor deze organisator komen in deze taal, ongeacht wie ze afdrukt.'),
('nl','doclang_contract_note','De taal wisselt kopjes en datums om. De bepalingen blijven zoals ze in de bouwstenen staan.'),

('fr','promoter_lang','Langue des documents'),
('fr','promoter_lang_band','Langue du groupe'),
('fr','promoter_lang_hint','Le contrat, la facture et le devis de cet organisateur sortent dans cette langue, quelle que soit la personne qui les imprime.'),
('fr','doclang_contract_note','La langue change les titres et les dates. Les clauses restent telles qu''elles sont écrites dans les blocs.'),

('es','promoter_lang','Idioma de los documentos'),
('es','promoter_lang_band','Idioma del grupo'),
('es','promoter_lang_hint','El contrato, la factura y el presupuesto de este organizador salen en este idioma, sin importar quién los imprima.'),
('es','doclang_contract_note','El idioma cambia los encabezados y las fechas. Las cláusulas se quedan como están escritas en los bloques.'),

('it','promoter_lang','Lingua dei documenti'),
('it','promoter_lang_band','Lingua della band'),
('it','promoter_lang_hint','Contratto, fattura e preventivo per questo organizzatore escono in questa lingua, chiunque li stampi.'),
('it','doclang_contract_note','La lingua cambia titoli e date. Le clausole restano come sono scritte nelle voci.')
ON DUPLICATE KEY UPDATE value = value;
