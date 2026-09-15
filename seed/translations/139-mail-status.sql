-- Zustellstatus der Einladung in der Mitgliederliste (#293)
INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_mail_invited','Invitation'),('en','mem_mail_queued','handed to the mail server — delivery not known yet'),
('en','mem_mail_sent','delivered'),('en','mem_mail_bounced','refused'),('en','mem_mail_deferred','will be retried'),
('en','mem_mail_failed','not sent'),('en','mem_mail_checked','log checked'),
('nl','mem_mail_invited','Uitnodiging'),('nl','mem_mail_queued','aan de mailserver overgedragen — bezorging nog onbekend'),
('nl','mem_mail_sent','bezorgd'),('nl','mem_mail_bounced','geweigerd'),('nl','mem_mail_deferred','wordt opnieuw geprobeerd'),
('nl','mem_mail_failed','niet verzonden'),('nl','mem_mail_checked','log gecontroleerd'),
('fr','mem_mail_invited','Invitation'),('fr','mem_mail_queued','remise au serveur de mail — livraison pas encore connue'),
('fr','mem_mail_sent','livrée'),('fr','mem_mail_bounced','refusée'),('fr','mem_mail_deferred','nouvel essai à venir'),
('fr','mem_mail_failed','non envoyée'),('fr','mem_mail_checked','journal vérifié'),
('es','mem_mail_invited','Invitación'),('es','mem_mail_queued','entregada al servidor de correo; entrega aún desconocida'),
('es','mem_mail_sent','entregada'),('es','mem_mail_bounced','rechazada'),('es','mem_mail_deferred','se reintentará'),
('es','mem_mail_failed','no enviada'),('es','mem_mail_checked','registro comprobado'),
('it','mem_mail_invited','Invito'),('it','mem_mail_queued','consegnato al server di posta — recapito ancora sconosciuto'),
('it','mem_mail_sent','recapitato'),('it','mem_mail_bounced','rifiutato'),('it','mem_mail_deferred','verrà ritentato'),
('it','mem_mail_failed','non inviato'),('it','mem_mail_checked','log controllato')
ON DUPLICATE KEY UPDATE value = value;
