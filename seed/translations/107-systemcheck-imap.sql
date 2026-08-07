-- Systempruefung nennt die fehlende imap-Erweiterung (#229)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sys_opt_imap','Without the imap extension no mailbox is fetched — the band''s inbox stays empty. PHP 8.4 moved it out of the core, so it has to be installed separately there.'),
('nl','sys_opt_imap','Zonder de imap-extensie wordt geen postvak opgehaald — het postvak van de band blijft leeg. PHP 8.4 haalde de extensie uit de kern, daar moet die apart worden geinstalleerd.'),
('fr','sys_opt_imap','Sans l''extension imap, aucune boite n''est relevee — la boite du groupe reste vide. PHP 8.4 l''a sortie du coeur, il faut donc l''installer separement.'),
('es','sys_opt_imap','Sin la extension imap no se recoge ningun buzon: el buzon del grupo queda vacio. PHP 8.4 la saco del nucleo, alli hay que instalarla por separado.'),
('it','sys_opt_imap','Senza l''estensione imap non viene scaricata alcuna casella: la posta della band resta vuota. PHP 8.4 l''ha spostata fuori dal nucleo, va installata a parte.')
ON DUPLICATE KEY UPDATE value = value;
