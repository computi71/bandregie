-- Die mitgelieferte Vertragsvorlage ist weg (#359).
--
-- Seit der Vertrag aus Bausteinen entsteht, ruft niemand mehr
-- t('contract_template') auf. Der Text stand in sechs Sprachen herum und
-- hätte beim nächsten Lesen so ausgesehen, als würde er noch irgendwo
-- gebraucht.
--
-- Was die Band in die Einstellung geschrieben hat, bleibt davon unberührt:
-- Das ist contract_text und steht in settings, nicht hier.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'contract_template';
