-- UI translation for the photo EXIF hint (EN/NL/FR/ES/IT).
-- INSERT IGNORE: von Hand gepflegte Übersetzungen einer Band bleiben unberührt.
SET NAMES utf8mb4;

INSERT IGNORE INTO translations (lang, tkey, value) VALUES
('en','photo_exif_hint','For event matching, upload originals straight from the device — copies shared via messengers or social networks lose the capture date and GPS.'),
('nl','photo_exif_hint','Upload voor de koppeling aan afspraken originelen rechtstreeks van het apparaat — via messengers of sociale netwerken gedeelde kopieën verliezen opnamedatum en gps.'),
('fr','photo_exif_hint','Pour l''association aux dates, téléverse les originaux directement depuis l''appareil — les copies partagées via messageries ou réseaux sociaux perdent la date de prise de vue et le GPS.'),
('es','photo_exif_hint','Para la asignación a fechas, sube los originales directamente desde el dispositivo — las copias compartidas por mensajería o redes sociales pierden la fecha de captura y el GPS.'),
('it','photo_exif_hint','Per l''abbinamento alle date, carica gli originali direttamente dal dispositivo — le copie condivise via messenger o social perdono data di scatto e GPS.');
