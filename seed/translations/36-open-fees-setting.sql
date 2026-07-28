-- Translation for the treasury setting that hides unbooked fees.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_fin','Band treasury'),
('en','set_fin_open_fees','Show fees that have not been booked yet'),
('en','set_fin_open_fees_hint','Lists every gig with a fee that has no matching income entry, with a button to book it. Off unless you need it.'),
('fr','set_fin','Caisse du groupe'),
('fr','set_fin_open_fees','Afficher les cachets pas encore comptabilisés'),
('fr','set_fin_open_fees_hint','Liste chaque concert avec un cachet sans recette correspondante, avec un bouton pour l''enregistrer. Désactivé tant que vous n''en avez pas besoin.'),
('es','set_fin','Caja del grupo'),
('es','set_fin_open_fees','Mostrar los caches aún no contabilizados'),
('es','set_fin_open_fees_hint','Lista cada concierto con cache sin ingreso asociado, con un botón para registrarlo. Desactivado mientras no lo necesitéis.'),
('nl','set_fin','Bandkas'),
('nl','set_fin_open_fees','Nog niet geboekte gages tonen'),
('nl','set_fin_open_fees_hint','Somt elk optreden met gage op waarvoor nog geen inkomst is geboekt, met een knop om die te boeken. Uit zolang je het niet nodig hebt.'),
('it','set_fin','Cassa della band'),
('it','set_fin_open_fees','Mostrare i cachet non ancora registrati'),
('it','set_fin_open_fees_hint','Elenca ogni concerto con cachet senza entrata corrispondente, con un pulsante per registrarla. Spento finché non serve.')
ON DUPLICATE KEY UPDATE value = value;
