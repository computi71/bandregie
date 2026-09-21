-- Rücksetzung für eine unbekannte Adresse sichtbar machen (#327)
INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_reset_unknown','Somebody asked for a password reset for an address that has no account. Usually a typo — compare it with the address on file:'),
('nl','mem_reset_unknown','Iemand vroeg een wachtwoordherstel aan voor een adres zonder account. Meestal een typefout — vergelijk met het opgeslagen adres:'),
('fr','mem_reset_unknown','Quelqu''un a demandé une réinitialisation pour une adresse sans compte. En général une faute de frappe — comparez avec l''adresse enregistrée :'),
('es','mem_reset_unknown','Alguien pidió restablecer la contraseña para una dirección sin cuenta. Normalmente un error de escritura — compárala con la dirección guardada:'),
('it','mem_reset_unknown','Qualcuno ha chiesto il ripristino della password per un indirizzo senza account. Di solito un errore di battitura — confrontalo con l''indirizzo registrato:')
ON DUPLICATE KEY UPDATE value = value;
