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
('it','decline_later','Più tardi'),
('en','help_termine_8','If you decline a rehearsal, a recording session, a photo shoot or a meeting, a window asks you straight away for an alternative — you know best why it does not work, and therefore also when it would. Tapping “Add an alternative” opens the event form with title and place already filled in; you only set the date. For a gig it does not ask: that date belongs to the promoter. Dismiss the window and it will not come back for that event.'),
('nl','help_termine_8','Zeg je een repetitie, een opname, een fotoshoot of een overleg af, dan vraagt een venster meteen om een alternatief — jij weet het beste waarom het niet gaat, en dus ook wanneer het wél zou gaan. Een tik op „Alternatief invoeren” opent het afsprakenformulier met titel en plaats al ingevuld; alleen de datum zet je zelf. Bij een gig vraagt het niet: die datum maakt de organisator. Wie het venster wegklikt, krijgt het voor die afspraak niet nog een keer.'),
('fr','help_termine_8','Si vous déclinez une répétition, une session d’enregistrement, un shooting ou une réunion, une fenêtre vous demande aussitôt une autre date — c’est vous qui savez le mieux pourquoi ça ne va pas, donc aussi quand ça irait. Un appui sur « Proposer une autre date » ouvre le formulaire avec le titre et le lieu déjà remplis ; vous ne posez que la date. Pour un concert, rien n’est demandé : cette date appartient à l’organisateur. Si vous fermez la fenêtre, elle ne reviendra pas pour cette date.'),
('es','help_termine_8','Si rechazas un ensayo, una sesión de grabación, una sesión de fotos o una reunión, una ventana te pide enseguida una alternativa — tú sabes mejor que nadie por qué no va, y por tanto también cuándo sí iría. Al tocar «Proponer alternativa» se abre el formulario con título y lugar ya puestos; solo pones la fecha. En un concierto no pregunta: esa fecha la pone el organizador. Si cierras la ventana, no vuelve para esa cita.'),
('it','help_termine_8','Se rifiuti una prova, una sessione di registrazione, un servizio fotografico o una riunione, una finestra ti chiede subito un’alternativa — tu sai meglio di tutti perché non va, e quindi anche quando andrebbe. Un tocco su «Proponi un’alternativa» apre il modulo con titolo e luogo già compilati; metti solo la data. Per un concerto non chiede: quella data la decide l’organizzatore. Se chiudi la finestra, per quell’appuntamento non torna.')
ON DUPLICATE KEY UPDATE value = value;
