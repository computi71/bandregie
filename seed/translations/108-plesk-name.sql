-- Hinweis auf den Repository-Namen in Plesk (#226)
INSERT INTO translations (lang, tkey, value) VALUES
('en','up_plesk_name','The value after -name is the name of the repository in Plesk. If it is called something else there — an earlier project name, for instance — put that name into the command, or Plesk will find nothing.'),
('nl','up_plesk_name','De waarde achter -name is de naam van de repository in Plesk. Heet die daar anders, bijvoorbeeld nog naar een eerdere projectnaam, zet dan die naam in het commando, anders vindt Plesk niets.'),
('fr','up_plesk_name','La valeur apres -name est le nom du depot dans Plesk. S''il porte un autre nom la-bas — un ancien nom de projet, par exemple — mettez ce nom dans la commande, sinon Plesk ne trouvera rien.'),
('es','up_plesk_name','El valor tras -name es el nombre del repositorio en Plesk. Si alli se llama de otra manera, por ejemplo con un nombre de proyecto anterior, pon ese nombre en el comando o Plesk no encontrara nada.'),
('it','up_plesk_name','Il valore dopo -name e il nome del repository in Plesk. Se la si chiama diversamente, per esempio come un vecchio nome del progetto, metti quel nome nel comando, altrimenti Plesk non trova nulla.')
ON DUPLICATE KEY UPDATE value = value;
