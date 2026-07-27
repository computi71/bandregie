-- Translations for asking a substitute, their ranking and their track record.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_sub_for','Substitute for'),
('en','ev_sub_ask','ask'),('en','ev_sub_asked','asked'),('en','ev_sub_open','no answer'),
('en','ev_sub_requested','Asked:'),('en','ev_sub_withdraw','Withdraw the request'),
('en','ev_sub_rehearsals','rehearsals'),('en','ev_sub_gigs','gigs'),
('en','mem_substitute_rank','Order as substitute'),
('en','mem_substitute_rank_hint','A lower number is asked first; 0 means no particular order.'),
('en','fl_sub_requested','Substitute asked.'),('en','fl_sub_withdrawn','Request withdrawn.'),

('fr','ev_sub_for','Remplaçant pour'),
('fr','ev_sub_ask','demander'),('fr','ev_sub_asked','demandé'),('fr','ev_sub_open','sans réponse'),
('fr','ev_sub_requested','Demandé :'),('fr','ev_sub_withdraw','Retirer la demande'),
('fr','ev_sub_rehearsals','répétitions'),('fr','ev_sub_gigs','concerts'),
('fr','mem_substitute_rank','Ordre comme remplaçant'),
('fr','mem_substitute_rank_hint','Le plus petit numéro est demandé en premier ; 0 signifie sans ordre.'),
('fr','fl_sub_requested','Remplaçant demandé.'),('fr','fl_sub_withdrawn','Demande retirée.'),

('es','ev_sub_for','Sustituto de'),
('es','ev_sub_ask','preguntar'),('es','ev_sub_asked','preguntado'),('es','ev_sub_open','sin respuesta'),
('es','ev_sub_requested','Preguntados:'),('es','ev_sub_withdraw','Retirar la petición'),
('es','ev_sub_rehearsals','ensayos'),('es','ev_sub_gigs','conciertos'),
('es','mem_substitute_rank','Orden como sustituto'),
('es','mem_substitute_rank_hint','El número más bajo se pregunta primero; 0 significa sin orden.'),
('es','fl_sub_requested','Sustituto preguntado.'),('es','fl_sub_withdrawn','Petición retirada.'),

('nl','ev_sub_for','Invaller voor'),
('nl','ev_sub_ask','vragen'),('nl','ev_sub_asked','gevraagd'),('nl','ev_sub_open','geen antwoord'),
('nl','ev_sub_requested','Gevraagd:'),('nl','ev_sub_withdraw','Verzoek intrekken'),
('nl','ev_sub_rehearsals','repetities'),('nl','ev_sub_gigs','optredens'),
('nl','mem_substitute_rank','Volgorde als invaller'),
('nl','mem_substitute_rank_hint','Een lager nummer wordt eerst gevraagd; 0 betekent geen volgorde.'),
('nl','fl_sub_requested','Invaller gevraagd.'),('nl','fl_sub_withdrawn','Verzoek ingetrokken.'),

('it','ev_sub_for','Sostituto di'),
('it','ev_sub_ask','chiedere'),('it','ev_sub_asked','richiesto'),('it','ev_sub_open','nessuna risposta'),
('it','ev_sub_requested','Richiesti:'),('it','ev_sub_withdraw','Ritira la richiesta'),
('it','ev_sub_rehearsals','prove'),('it','ev_sub_gigs','concerti'),
('it','mem_substitute_rank','Ordine come sostituto'),
('it','mem_substitute_rank_hint','Il numero più basso viene chiesto per primo; 0 significa senza ordine.'),
('it','fl_sub_requested','Sostituto richiesto.'),('it','fl_sub_withdrawn','Richiesta ritirata.')
ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_sub_auto','Ask a substitute automatically'),
('en','set_sub_auto_hint','When someone declines, the request goes to one of their substitutes by itself. If that one declines too, the next moves up.'),
('en','sub_auto_off','off — ask by hand'),('en','sub_auto_rank','by rank'),
('en','sub_auto_shuffle','at random'),('en','sub_auto_rotate','taking turns — whoever waited longest'),

('fr','set_sub_auto','Demander un remplaçant automatiquement'),
('fr','set_sub_auto_hint','Quand quelqu''un décline, la demande part d''elle-même vers un de ses remplaçants. S''il décline aussi, le suivant prend le relais.'),
('fr','sub_auto_off','désactivé — demander à la main'),('fr','sub_auto_rank','par ordre'),
('fr','sub_auto_shuffle','au hasard'),('fr','sub_auto_rotate','à tour de rôle — celui qui a attendu le plus longtemps'),

('es','set_sub_auto','Preguntar a un sustituto automáticamente'),
('es','set_sub_auto_hint','Cuando alguien cancela, la petición va sola a uno de sus sustitutos. Si ese también cancela, pasa al siguiente.'),
('es','sub_auto_off','desactivado — preguntar a mano'),('es','sub_auto_rank','por orden'),
('es','sub_auto_shuffle','al azar'),('es','sub_auto_rotate','por turnos — quien lleva más tiempo esperando'),

('nl','set_sub_auto','Automatisch een invaller vragen'),
('nl','set_sub_auto_hint','Zegt iemand af, dan gaat het verzoek vanzelf naar een van zijn invallers. Zegt die ook af, dan schuift de volgende door.'),
('nl','sub_auto_off','uit — met de hand vragen'),('nl','sub_auto_rank','op volgorde'),
('nl','sub_auto_shuffle','willekeurig'),('nl','sub_auto_rotate','om de beurt — wie het langst wachtte'),

('it','set_sub_auto','Chiedere automaticamente un sostituto'),
('it','set_sub_auto_hint','Se qualcuno declina, la richiesta va da sola a uno dei suoi sostituti. Se anche quello declina, subentra il successivo.'),
('it','sub_auto_off','disattivato — chiedere a mano'),('it','sub_auto_rank','per ordine'),
('it','sub_auto_shuffle','a caso'),('it','sub_auto_rotate','a turno — chi ha aspettato di più')
ON DUPLICATE KEY UPDATE value = value;
