-- Eine Absage aus der Band steht im Kopf des Termins und setzt „Findet statt“
-- zurück auf „Angefragt“ (#442).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_dropout_title','Cancelled'),
('nl','ev_dropout_title','Heeft afgezegd'),
('fr','ev_dropout_title','S’est désisté'),
('es','ev_dropout_title','Ha cancelado'),
('it','ev_dropout_title','Ha disdetto'),
('en','push_topic_wackelt','When a cancellation reopens one of my confirmed events'),
('nl','push_topic_wackelt','Als een afzegging een bevestigde afspraak van mij weer openzet'),
('fr','push_topic_wackelt','Quand un désistement rouvre une de mes dates confirmées'),
('es','push_topic_wackelt','Cuando una cancelación vuelve a abrir una cita mía confirmada'),
('it','push_topic_wackelt','Quando una disdetta riapre un mio appuntamento confermato'),
('en','push_ev_wobbles','Open again: %1'),
('nl','push_ev_wobbles','Weer open: %1'),
('fr','push_ev_wobbles','De nouveau ouvert : %1'),
('es','push_ev_wobbles','Abierto de nuevo: %1'),
('it','push_ev_wobbles','Di nuovo aperto: %1'),
('en','push_ev_wobbles_body','%1 has cancelled. The event is now “Requested”.'),
('nl','push_ev_wobbles_body','%1 heeft afgezegd. De afspraak staat nu op „Aangevraagd”.'),
('fr','push_ev_wobbles_body','%1 s’est désisté. La date est maintenant « Demandé ».'),
('es','push_ev_wobbles_body','%1 ha cancelado. La cita está ahora en «Solicitado».'),
('it','push_ev_wobbles_body','%1 ha disdetto. L’appuntamento ora è «Richiesto».'),
('en','help_termine_15','When someone in the band cancels, their name appears right in the event’s head next to its status, in red with a ✘, so you don’t have to open the card to see it. If the event was set to “Confirmed”, it goes back to “Requested”, and the person responsible (otherwise whoever entered it) gets a notification. They decide: move it, cancel it, or set it back to “Confirmed”. This does not apply to gigs, set-up/tear-down and travel. A gig happens even without one of you, the date belongs to the promoter, and a substitute fills the gap. Cancellations by substitutes change nothing, and someone who is not on stage doesn’t count for rehearsals, recordings and photo shoots.'),
('nl','help_termine_15','Zegt iemand uit de band af, dan staat de naam meteen in de kop van de afspraak naast de status, rood met een ✘. Je hoeft de kaart dus niet open te klappen om het te zien. Stond de afspraak op „Gaat door”, dan gaat ze terug naar „Aangevraagd”, en wie verantwoordelijk is (anders wie haar invoerde) krijgt een melding. Die beslist: verzetten, afzeggen of weer op „Gaat door” zetten. Dit geldt niet voor gigs, op-/afbouw en reizen. Een gig gaat ook zonder een van jullie door, de datum is van de organisator, en een invaller vult het gat. Afzeggingen van invallers veranderen niets, en wie niet op het podium staat, telt bij repetities, opnames en fotoshoots niet mee.'),
('fr','help_termine_15','Quand quelqu’un du groupe se désiste, son nom apparaît tout de suite dans l’en-tête de la date à côté du statut, en rouge avec un ✘ : pas besoin d’ouvrir la carte pour le voir. Si la date était sur « Confirmé », elle repasse à « Demandé », et la personne responsable (sinon celle qui l’a saisie) reçoit une notification. C’est elle qui décide : déplacer, annuler ou remettre sur « Confirmé ». Cela ne vaut pas pour les concerts, le montage/démontage et les trajets. Un concert a lieu même sans l’un de vous, la date appartient à l’organisateur, et un remplaçant comble le trou. Les désistements de remplaçants ne changent rien, et qui n’est pas sur scène ne compte pas pour les répétitions, enregistrements et séances photo.'),
('es','help_termine_15','Cuando alguien de la banda cancela, su nombre aparece enseguida en la cabecera de la cita junto al estado, en rojo con una ✘, así que no hace falta abrir la tarjeta para verlo. Si la cita estaba en «Confirmado», vuelve a «Solicitado», y la persona responsable (si no, quien la creó) recibe un aviso. Esa persona decide: moverla, cancelarla o volver a ponerla en «Confirmado». No se aplica a conciertos, montaje/desmontaje ni viajes. Un concierto se celebra aunque falte uno de vosotros, la fecha es del organizador y un sustituto cubre el hueco. Las cancelaciones de sustitutos no cambian nada, y quien no está en el escenario no cuenta en ensayos, grabaciones ni sesiones de fotos.'),
('it','help_termine_15','Quando qualcuno della band disdice, il suo nome compare subito nell’intestazione dell’appuntamento accanto allo stato, in rosso con una ✘: non serve aprire la scheda per vederlo. Se l’appuntamento era su «Confermato», torna a «Richiesto», e la persona responsabile (altrimenti chi l’ha inserito) riceve una notifica. È lei a decidere: spostarlo, annullarlo o rimetterlo su «Confermato». Non vale per concerti, montaggio/smontaggio e viaggi. Un concerto si svolge anche senza uno di voi, la data appartiene all’organizzatore e un sostituto copre il vuoto. Le disdette dei sostituti non cambiano nulla, e chi non è sul palco non conta per prove, registrazioni e servizi fotografici.')
ON DUPLICATE KEY UPDATE value = value;
