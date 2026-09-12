-- Offline: Zustand am Knopf und die Automatik fuer kommende Termine (#277)
INSERT INTO translations (lang, tkey, value) VALUES
('en','off_ready','✓ Available offline'),
('nl','off_ready','✓ Offline beschikbaar'),
('fr','off_ready','✓ Disponible hors ligne'),
('es','off_ready','✓ Disponible sin conexión'),
('it','off_ready','✓ Disponibile offline'),
('en','off_auto','Take upcoming dates along automatically'),
('nl','off_auto','Komende data automatisch meenemen'),
('fr','off_auto','Emporter automatiquement les dates à venir'),
('es','off_auto','Llevar automáticamente las próximas fechas'),
('it','off_auto','Porta con te automaticamente le prossime date'),
('en','off_auto_hint','Whatever stands under "next dates" on the overview is fetched to the device in the background — setlist, lyrics, sheet music, rider and patch list. What belongs to dates that are over is released again.'),
('nl','off_auto_hint','Wat op het overzicht onder „volgende data" staat, haalt de app op de achtergrond naar het toestel — setlist, teksten, bladmuziek, rider en patchlijst. Wat bij afgelopen data hoort, wordt weer vrijgegeven.'),
('fr','off_auto_hint','Ce qui figure sous « prochaines dates » sur la vue d''ensemble est récupéré en arrière-plan sur l''appareil — setlist, paroles, partitions, rider et patch. Ce qui appartient à des dates passées est libéré.'),
('es','off_auto_hint','Lo que aparece en el resumen bajo «próximas fechas» se descarga al dispositivo en segundo plano: lista, letras, partituras, rider y lista de canales. Lo que pertenece a fechas pasadas se libera de nuevo.'),
('it','off_auto_hint','Ciò che compare nel riepilogo sotto «prossime date» viene scaricato sul dispositivo in background: scaletta, testi, spartiti, rider e lista canali. Ciò che appartiene a date passate viene liberato.')
ON DUPLICATE KEY UPDATE value = value;
