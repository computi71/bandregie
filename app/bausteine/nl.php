<?php
// Der niederländische Bausteinsatz (#360).
//
// KEINE Übersetzung des deutschen. Zwei Dinge sind anders:
//
// 1. Buma/Stemra statt GEMA. Lizenznehmer und Zahler ist der Veranstalter,
//    nicht die Band — auch bei freiem Eintritt kann eine Lizenz nötig sein.
//    Sena spielt bei einem Liveauftritt KEINE Rolle: Sie betrifft das
//    Abspielen von Aufnahmen, und live wird keine Aufnahme wiedergegeben.
//    Eine Sena-Klausel im Auftrittsvertrag wäre schlicht falsch.
//
// 2. Statt der Künstlersozialabgabe gibt es die artiestenregeling: Der
//    Veranstalter muss auf die Gage Lohnabgaben einbehalten, es sei denn,
//    die Musiker geben eine gageverklaring ab oder es läuft über eine
//    inhoudingsplichtigenverklaring. Über die kleinevergoedingsregeling
//    lassen sich bis zu 163 Euro je Person und Auftritt als Aufwand
//    geltend machen. Das ist kein Nebensatz, das entscheidet, was ankommt.
//
// Quellen: Buma/Stemra; Belastingdienst artiestenregeling; muziekenrecht.nl.

return [

  ['bkey' => 'nl_kop', 'gruppe' => 'kopf', 'fest' => 1, 'an' => 1,
   'label' => 'Contractkop',
   'body' => "OPTREEDOVEREENKOMST\n\ntussen\n{veranstalter}\n{veranstalter_anschrift}\n— hierna de Organisator —\n\nen\n{band}\n— hierna de Artiest —"],

  // ---------- Voorwerp ----------
  ['bkey' => 'nl_voorwerp', 'gruppe' => 'rahmen', 'fest' => 1, 'an' => 1,
   'label' => 'Voorwerp',
   'body' => "De Organisator engageert de Artiest voor het volgende optreden:\nLocatie: {ort}\nDatum: {datum}\nSpeeltijd: {spielzeit}"],

  ['bkey' => 'nl_opbouw', 'gruppe' => 'rahmen', 'an' => 1,
   'label' => 'Opbouw en soundcheck',
   'body' => "De zaal is vanaf {einlass} beschikbaar voor opbouw en soundcheck. Tot de deuren opengaan heeft de Artiest ongestoord toegang tot het podium."],

  ['bkey' => 'nl_sets', 'gruppe' => 'rahmen',
   'label' => 'Sets en pauze',
   'body' => "De Artiest speelt ____ sets van elk ongeveer ____ minuten met een pauze van ____ minuten. Een toegift van ten hoogste ____ minuten is afgesproken."],

  // ---------- Gage en kosten ----------
  ['bkey' => 'nl_gage_vast', 'gruppe' => 'gage', 'wahl' => 'Gage', 'an' => 1,
   'label' => 'Vaste gage',
   'body' => "De Organisator betaalt de Artiest voor het optreden een vaste gage van {gage}. Daarmee zijn alle prestaties uit deze overeenkomst voldaan, tenzij hierna anders is bepaald."],

  ['bkey' => 'nl_gage_deel', 'gruppe' => 'gage', 'wahl' => 'Gage',
   'label' => 'Deel van de recette',
   'body' => "De Organisator betaalt de Artiest ____ % van de kaartverkoop, berekend na aftrek van btw. De toegangsprijs bedraagt ____ euro, gereduceerd ____ euro. De Organisator legt direct na afloop een afrekening van de verkochte kaarten over."],

  ['bkey' => 'nl_gage_mix', 'gruppe' => 'gage', 'wahl' => 'Gage',
   'label' => 'Minimumgage plus deel',
   'body' => "De Organisator garandeert een minimumgage van {gage}. Overstijgt ____ % van de kaartverkoop dit bedrag, dan ontvangt de Artiest dat deel in plaats van het minimum. De afrekening volgt direct na afloop."],

  ['bkey' => 'nl_betaling_factuur', 'gruppe' => 'gage', 'wahl' => 'Betaling', 'an' => 1,
   'label' => 'Betaling: op factuur',
   'body' => "De Artiest factureert na het optreden. Het bedrag is binnen 14 dagen na factuurdatum zonder korting opeisbaar."],

  ['bkey' => 'nl_betaling_contant', 'gruppe' => 'gage', 'wahl' => 'Betaling',
   'label' => 'Betaling: contant na afloop',
   'body' => "De gage wordt na afloop van het optreden contant tegen kwitantie uitbetaald."],

  ['bkey' => 'nl_buma', 'gruppe' => 'gage', 'fest' => 1, 'an' => 1,
   'label' => 'Buma/Stemra',
   'body' => "De vergoeding voor het muziekgebruik komt voor rekening van de Organisator. Hij beschikt over de benodigde licentie van Buma/Stemra en meldt het evenement tijdig aan. De Artiest levert desgevraagd de lijst van uitgevoerde werken aan.",
   'hinweis' => "Staat altijd in het contract: de licentienemer is de organisator of de zaal, niet de band — ook bij vrije toegang kan een evenementenlicentie nodig zijn. Sena komt hier bewust niet voor: die gaat over het afspelen van opnamen, en bij een live optreden wordt geen opname openbaar gemaakt."],

  ['bkey' => 'nl_artiestenregeling', 'gruppe' => 'gage', 'wahl' => 'Loonheffing', 'an' => 1,
   'label' => 'Artiestenregeling: gageverklaring',
   'body' => "Op het optreden is de artiestenregeling van toepassing. De leden van de Artiest leveren vóór het optreden een ingevulde en ondertekende gageverklaring in, met een kopie van een geldig identiteitsbewijs. De Organisator verwerkt de inhouding overeenkomstig die verklaring en verstrekt na afloop een afrekening.",
   'hinweis' => "Zonder deze afspraak houdt de Organisator loonheffing in en komt er minder binnen dan er op papier staat. Via de kleinevergoedingsregeling kan per persoon en per optreden tot 163 euro aan kosten worden opgevoerd; bij een band vult ieder lid het formulier in en tekent het."],

  ['bkey' => 'nl_ipv', 'gruppe' => 'gage', 'wahl' => 'Loonheffing',
   'label' => 'Artiestenregeling: via een inhoudingsplichtigenverklaring',
   'body' => "De Artiest treedt op onder een inhoudingsplichtigenverklaring. Een afschrift daarvan wordt vóór het optreden aan de Organisator verstrekt; de Organisator houdt in dat geval geen loonheffing in.",
   'hinweis' => "Alleen aanvinken als die verklaring er werkelijk is. Zonder papier blijft de Organisator inhoudingsplichtig, en dat wordt bij hem nagevorderd."],

  ['bkey' => 'nl_btw', 'gruppe' => 'gage',
   'label' => 'Btw',
   'body' => "De genoemde bedragen zijn exclusief btw. De btw wordt tegen het wettelijke tarief toegevoegd en op de factuur vermeld."],

  ['bkey' => 'nl_reiskosten', 'gruppe' => 'gage',
   'label' => 'Reiskosten',
   'body' => "De Organisator vergoedt de reiskosten met ____ euro per gereden kilometer, gerekend van de repetitieruimte naar de locatie en terug."],

  ['bkey' => 'nl_pa_vergoeding', 'gruppe' => 'gage',
   'label' => 'Vergoeding voor meegebrachte apparatuur',
   'body' => "Levert de Artiest geluids- en lichtapparatuur, dan ontvangt hij daarvoor bovendien ____ euro."],

  ['bkey' => 'nl_annul_org', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Afzegging door de Organisator',
   'body' => "Zegt de Organisator het optreden af, of gaat het niet door om een reden die voor zijn rekening komt, dan blijft de volledige gage verschuldigd. Kosten die de Artiest daardoor bespaart, worden verrekend."],

  ['bkey' => 'nl_annul_art', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Afzegging door de Artiest',
   'body' => "Kan de Artiest niet optreden om een reden die voor zijn rekening komt, dan vervalt de aanspraak op de gage. Hij bericht de Organisator onverwijld en spant zich in voor gelijkwaardige vervanging."],

  ['bkey' => 'nl_ziekte', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Ziekte',
   'body' => "Bij ziekte van een lid bericht de Artiest de Organisator onverwijld. Is het optreden daardoor niet mogelijk, dan vervallen de speelplicht en de betalingsplicht beide; verdergaande aanspraken bestaan over en weer niet."],

  ['bkey' => 'nl_overmacht', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Overmacht',
   'body' => "Wordt het optreden onmogelijk door overmacht — onder meer natuurgeweld, overheidsbesluit of calamiteit — dan vervallen de verplichtingen van beide partijen zonder vergoeding. Reeds betaalde bedragen worden terugbetaald; kosten die niet meer af te wenden waren, draagt ieder voor zich.",
   'hinweis' => "Zonder eigen bepaling geldt de wettelijke regel, en die levert bij een van overheidswege afgelast evenement doorgaans geen gage op. Wie iets anders wil, moet het hier opschrijven."],

  // ---------- Verplichtingen van de Organisator ----------
  ['bkey' => 'nl_techniek_org', 'gruppe' => 'veranstalter', 'wahl' => 'Techniek', 'an' => 1,
   'label' => 'Techniek: Organisator levert',
   'body' => "De Organisator stelt een bespeelbaar podium met voldoende stroomvoorziening ter beschikking, alsmede geluids- en lichtapparatuur in een bij de locatie passende omvang. Tijdens opbouw, soundcheck en optreden is iemand bereikbaar die de apparatuur kan bedienen."],

  ['bkey' => 'nl_techniek_art', 'gruppe' => 'veranstalter', 'wahl' => 'Techniek',
   'label' => 'Techniek: Artiest brengt mee',
   'body' => "De Organisator stelt de ruimte en de stroomvoorziening ter beschikking. De Artiest brengt geluids- en lichtapparatuur mee in een bij de locatie passende omvang. De Organisator meldt uiterlijk vier weken vooraf het verwachte aantal bezoekers en maakt op verzoek een bezichtiging mogelijk."],

  ['bkey' => 'nl_techniek_info', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Opgave van de apparatuur vooraf',
   'body' => "De Organisator meldt uiterlijk een week vóór het optreden welke apparatuur ter plaatse staat en wie die bedient."],

  ['bkey' => 'nl_rider', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'De technische rider hoort bij het contract',
   'body' => "De technische rider van de Artiest maakt deel uit van deze overeenkomst. Afwijkingen stemt de Organisator tijdig met de Artiest af."],

  ['bkey' => 'nl_aansprakelijkheid', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Veiligheid en aansprakelijkheid',
   'body' => "Zolang de Artiest op de locatie is, draagt de Organisator de verantwoordelijkheid voor de veiligheid van de Artiest en zijn helpers en voor de meegebrachte apparatuur en instrumenten. Voor schade die de Artiest zelf veroorzaakt is hij aansprakelijk."],

  ['bkey' => 'nl_kleedkamer', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Kleedkamer',
   'body' => "De Organisator stelt een afsluitbare en verwarmbare ruimte als kleedkamer ter beschikking."],

  ['bkey' => 'nl_drinken', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Drinken',
   'body' => "De Organisator stelt de Artiest en zijn helpers tijdens opbouw, soundcheck en optreden kosteloos drinken in redelijke hoeveelheid ter beschikking."],

  ['bkey' => 'nl_eten', 'gruppe' => 'veranstalter',
   'label' => 'Warme maaltijd',
   'body' => "De Organisator verzorgt per speeldag een warme maaltijd voor de Artiest en zijn helpers. Aantal: ____, waarvan vegetarisch: ____."],

  ['bkey' => 'nl_overnachting', 'gruppe' => 'veranstalter',
   'label' => 'Overnachting',
   'body' => "De Organisator draagt de kosten van overnachting met ontbijt voor ____ personen in ____ eenpersoons- en ____ tweepersoonskamers. Het hotel wordt uiterlijk een week vooraf genoemd."],

  ['bkey' => 'nl_openlucht', 'gruppe' => 'veranstalter',
   'label' => 'Openlucht',
   'body' => "Bij een optreden in de openlucht zijn podium en mengplek overdekt en tegen weer beschermd. De Organisator zorgt voor een vlakke, draagkrachtige ondergrond."],

  ['bkey' => 'nl_promotie', 'gruppe' => 'veranstalter',
   'label' => 'Promotie',
   'body' => "De Organisator maakt in redelijke mate reclame voor het optreden en noemt de Artiest op alle aankondigingen in de door hem opgegeven schrijfwijze."],

  ['bkey' => 'nl_gastenlijst', 'gruppe' => 'veranstalter',
   'label' => 'Gastenlijst',
   'body' => "De Artiest mag een gastenlijst voeren van twee vrije toegangen per lid, vóór de deuren opengaan aan de Organisator overhandigd."],

  ['bkey' => 'nl_opnamen', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Geluids- en beeldopnamen',
   'body' => "Geluids-, foto-, film- en video-opnamen van soundcheck en optreden behoeven, voor zover zij het privégebruik van het publiek te buiten gaan, de voorafgaande toestemming van de Artiest. Opnamen die de Organisator voor eigen reclame wil gebruiken vergen een aparte afspraak."],

  // ---------- Verplichtingen en rechten van de Artiest ----------
  ['bkey' => 'nl_tijden', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Tijden nakomen',
   'body' => "De Artiest houdt zich aan de afgesproken tijden voor opbouw, soundcheck en optreden."],

  ['bkey' => 'nl_programma', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Vrijheid van programma',
   'body' => "De Artiest bepaalt zijn programma zelf. Bijzondere wensen van de Organisator vergen een aparte afspraak."],

  ['bkey' => 'nl_vervanging', 'gruppe' => 'kuenstler',
   'label' => 'Vervanging van een lid',
   'body' => "Valt een afzonderlijk lid uit, dan mag de Artiest het door een gelijkwaardige bezetting vervangen, zonder dat de Organisator daaraan rechten ontleent."],

  ['bkey' => 'nl_promomateriaal', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Promotiemateriaal',
   'body' => "De Artiest stelt de Organisator kosteloos bandinformatie, een perstekst en foto's ter beschikking om het optreden aan te kondigen. De rechten daarop blijven bij de Artiest; het gebruik is beperkt tot het aanprijzen van dit optreden."],

  // ---------- Slotbepalingen ----------
  ['bkey' => 'nl_nietigheid', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Nietigheid van een bepaling',
   'body' => "Is een bepaling van deze overeenkomst nietig of niet uitvoerbaar, dan blijven de overige bepalingen van kracht. Partijen vervangen de nietige bepaling door een toelaatbare die het economisch beoogde zo dicht mogelijk benadert."],

  ['bkey' => 'nl_schriftelijk', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Geen mondelinge afspraken',
   'body' => "Mondelinge nevenafspraken bestaan niet. Wijzigingen en aanvullingen van deze overeenkomst geschieden schriftelijk; een e-mail volstaat."],

  ['bkey' => 'nl_geen_dienstverband', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Geen dienstbetrekking',
   'body' => "Tussen partijen komt geen arbeidsovereenkomst tot stand. Op de fiscale behandeling van de gage is de bepaling over de artiestenregeling hierboven van toepassing.",
   'hinweis' => "Let op: dit zegt iets over het arbeidsrecht, niet over de loonheffing. Die volgt de artiestenregeling en laat zich met een contractzin niet wegschrijven."],

  ['bkey' => 'nl_recht', 'gruppe' => 'schluss',
   'label' => 'Toepasselijk recht',
   'body' => "Op deze overeenkomst is Nederlands recht van toepassing."],

  ['bkey' => 'nl_ondertekening', 'gruppe' => 'fuss', 'fest' => 1, 'an' => 1,
   'label' => 'Handtekeningen',
   'body' => "Plaats, datum: ......................        Plaats, datum: {heute}\n\n\n__________________________                __________________________\nDe Organisator                            De Artiest"],
];
