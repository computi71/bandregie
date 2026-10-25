-- Hinweis nach einer Absage (#401).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','decline_title','A shame you cannot make it'),
('en','decline_hint','You know best why it does not work — so you also know when it would. Put an alternative date in right away; title and place are filled in already, you only have to set the date.'),
('en','decline_add','Add an alternative'),
('en','decline_later','Later'),
('nl','decline_title','Jammer dat je niet kunt'),
('nl','decline_hint','Jij weet het beste waarom het niet gaat — dus ook wanneer het wél zou gaan. Zet meteen een vervangende datum neer; titel en plaats staan er al in, je hoeft alleen de datum te kiezen.'),
('nl','decline_add','Alternatief invoeren'),
('nl','decline_later','Later'),
('fr','decline_title','Dommage que tu ne puisses pas'),
('fr','decline_hint','C’est toi qui sais le mieux pourquoi ça ne va pas — donc aussi quand ça irait. Propose tout de suite une autre date ; le titre et le lieu sont déjà remplis, il ne reste que la date à poser.'),
('fr','decline_add','Proposer une autre date'),
('fr','decline_later','Plus tard'),
('es','decline_title','Una pena que no puedas'),
('es','decline_hint','Tú sabes mejor que nadie por qué no va — así que también cuándo sí iría. Propon ya una fecha alternativa; título y lugar ya están puestos, solo falta la fecha.'),
('es','decline_add','Proponer alternativa'),
('es','decline_later','Más tarde'),
('it','decline_title','Peccato che non puoi'),
('it','decline_hint','Tu sai meglio di tutti perché non va — quindi anche quando andrebbe. Proponi subito una data alternativa; titolo e luogo ci sono già, manca solo la data.'),
('it','decline_add','Proponi un’alternativa'),
('it','decline_later','Più tardi')
ON DUPLICATE KEY UPDATE value = value;
