<?php
// Der französische Bausteinsatz (#360).
//
// KEINE Übersetzung des deutschen. Frankreich kennt den deutschen Zuschnitt
// nicht: Nach Artikel L. 7121-3 des Code du travail gilt jeder Vertrag, mit
// dem jemand gegen Entgelt die Mitwirkung eines ausübenden Künstlers
// beschafft, als Arbeitsvertrag — unabhängig davon, was die Parteien
// hineinschreiben, und unabhängig von der Staatsangehörigkeit. Eine Klausel
// „hierdurch wird kein Arbeitsverhältnis begründet" wäre dort nicht nur
// wirkungslos, sie stünde gegen das Gesetz. Sie fehlt hier deshalb.
//
// Der übliche Weg ist stattdessen der „contrat de cession du droit
// d'exploitation d'un spectacle": Die Band verkauft dem Veranstalter die
// fertige Vorstellung. Sie bleibt Arbeitgeberin ihrer Musiker und trägt
// deren Abgaben; der Veranstalter stellt den Ort. Wer statt dessen direkt
// engagiert, wird Arbeitgeber und läuft als Gelegenheitsveranstalter über
// den GUSO, den einheitlichen Schalter für das gelegentliche Engagement.
// Beides steht als Entweder-oder in der Gruppe „engagement".
//
// SACEM: Die Anmeldung schuldet der Veranstalter, und zwar VOR der
// Veranstaltung — vorher angemeldet ist es außerdem billiger. Die SACEM zieht
// für Gelegenheitsveranstalter zugleich die Nachbarrechte der SPRÉ ein, es
// gibt also nur einen Ansprechpartner.
//
// Quellen: Code du travail L. 7121-3; Guide des obligations sociales du
// spectacle vivant (culture.gouv.fr); GUSO; SACEM/SPRÉ.

return [

  ['bkey' => 'fr_kopf', 'gruppe' => 'kopf', 'fest' => 1, 'an' => 1,
   'label' => 'En-tête du contrat',
   'body' => "CONTRAT DE CESSION DU DROIT D'EXPLOITATION D'UN SPECTACLE\n\nentre\n{veranstalter}\n{veranstalter_anschrift}\n— ci-après l'Organisateur —\n\net\n{band}\n— ci-après le Producteur —"],

  // ---------- Objet du contrat ----------
  ['bkey' => 'fr_objet', 'gruppe' => 'rahmen', 'fest' => 1, 'an' => 1,
   'label' => 'Objet',
   'body' => "Le Producteur cède à l'Organisateur le droit d'exploiter le spectacle suivant :\nLieu : {ort}\nDate : {datum}\nHoraire : {spielzeit}"],

  ['bkey' => 'fr_montage', 'gruppe' => 'rahmen', 'an' => 1,
   'label' => 'Montage et balances',
   'body' => "La salle est mise à disposition à partir de {einlass} pour le montage et les balances. Le Producteur dispose de la scène sans interruption jusqu'à l'ouverture des portes."],

  ['bkey' => 'fr_duree', 'gruppe' => 'rahmen',
   'label' => 'Sets et entracte',
   'body' => "Le spectacle comprend ____ parties d'environ ____ minutes, séparées d'un entracte de ____ minutes. Un rappel de ____ minutes au plus est convenu."],

  // ---------- Engagement : les deux voies ----------
  ['bkey' => 'fr_cession', 'gruppe' => 'rahmen', 'wahl' => 'Engagement', 'an' => 1,
   'label' => 'Cession : le Producteur emploie les artistes',
   'body' => "Le Producteur demeure l'employeur des artistes et techniciens attachés au spectacle. Il assume leur rémunération ainsi que les cotisations sociales et les charges fiscales correspondantes, et en justifie à première demande. L'Organisateur met le lieu à disposition et assume les obligations qui s'y rattachent.",
   'hinweis' => "C'est la voie habituelle. L'Organisateur achète un spectacle terminé et n'emploie personne."],

  ['bkey' => 'fr_guso', 'gruppe' => 'rahmen', 'wahl' => 'Engagement',
   'label' => 'Engagement direct : l\'Organisateur emploie, via le GUSO',
   'body' => "L'Organisateur engage directement les artistes. Il est leur employeur, procède à la déclaration et au versement des cotisations par l'intermédiaire du Guichet unique du spectacle occasionnel (GUSO) et remet à chaque artiste le formulaire valant contrat de travail et bulletin de paie.",
   'hinweis' => "À ne cocher que si l'Organisateur engage vraiment lui-même. Le GUSO est réservé à celui dont le spectacle n'est pas l'activité principale. Rappel : l'article L. 7121-3 du Code du travail présume le contrat de travail dès qu'on rémunère la participation d'un artiste — aucune clause contraire n'y change rien."],

  // ---------- Cachet et frais ----------
  ['bkey' => 'fr_cachet', 'gruppe' => 'gage', 'wahl' => 'Cachet', 'an' => 1,
   'label' => 'Prix de cession forfaitaire',
   'body' => "En contrepartie, l'Organisateur verse au Producteur un prix de cession de {gage}. Ce prix couvre l'ensemble des prestations prévues au présent contrat, sauf stipulation contraire ci-après."],

  ['bkey' => 'fr_part', 'gruppe' => 'gage', 'wahl' => 'Cachet',
   'label' => 'Participation à la billetterie',
   'body' => "L'Organisateur verse au Producteur ____ % de la recette des entrées, calculée après déduction de la TVA et des taxes applicables. Le prix d'entrée est de ____ euros, tarif réduit ____ euros. L'Organisateur remet le relevé de billetterie immédiatement après la représentation."],

  ['bkey' => 'fr_minimum', 'gruppe' => 'gage', 'wahl' => 'Cachet',
   'label' => 'Minimum garanti plus participation',
   'body' => "L'Organisateur garantit un minimum de {gage}. Si ____ % de la recette des entrées dépasse ce montant, le Producteur perçoit cette part à la place du minimum. Le relevé de billetterie est remis immédiatement après la représentation."],

  ['bkey' => 'fr_paiement_facture', 'gruppe' => 'gage', 'wahl' => 'Paiement', 'an' => 1,
   'label' => 'Paiement : sur facture',
   'body' => "Le Producteur établit une facture après la représentation. Le montant est exigible sans escompte dans les 30 jours suivant la date de facture."],

  ['bkey' => 'fr_paiement_soir', 'gruppe' => 'gage', 'wahl' => 'Paiement',
   'label' => 'Paiement : le soir même',
   'body' => "Le prix de cession est réglé à l'issue de la représentation, contre reçu."],

  ['bkey' => 'fr_sacem', 'gruppe' => 'gage', 'fest' => 1, 'an' => 1,
   'label' => 'SACEM et droits voisins',
   'body' => "Les droits d'auteur et les droits voisins sont à la charge de l'Organisateur. Il déclare la manifestation à la SACEM avant la date du spectacle et en règle les redevances. Le Producteur lui remet le programme des œuvres interprétées.",
   'hinweis' => "Figure toujours au contrat : l'obligation pèse sur l'organisateur et non sur les musiciens. La déclaration se fait en ligne AVANT la date — après, c'est plus cher, et diffuser sans autorisation est un délit. Pour les organisateurs occasionnels, la SACEM encaisse aussi les droits voisins pour le compte de la SPRÉ : un seul interlocuteur."],

  ['bkey' => 'fr_tva', 'gruppe' => 'gage',
   'label' => 'TVA',
   'body' => "Les montants s'entendent hors taxes. La TVA au taux légal s'y ajoute et figure sur la facture."],

  ['bkey' => 'fr_transport', 'gruppe' => 'gage',
   'label' => 'Transport',
   'body' => "L'Organisateur rembourse les frais de transport à hauteur de ____ euros par kilomètre parcouru, du local de répétition au lieu du spectacle et retour."],

  ['bkey' => 'fr_annul_org', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Annulation par l\'Organisateur',
   'body' => "Si l'Organisateur annule la représentation ou si celle-ci n'a pas lieu pour une cause qui lui est imputable, le prix de cession reste dû en totalité. Les dépenses que le Producteur a effectivement épargnées en sont déduites."],

  ['bkey' => 'fr_annul_prod', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Annulation par le Producteur',
   'body' => "Si le Producteur ne peut assurer la représentation pour une cause qui lui est imputable, le prix de cession n'est pas dû. Il en informe l'Organisateur sans délai et s'emploie à proposer un remplacement équivalent."],

  ['bkey' => 'fr_maladie', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Maladie',
   'body' => "En cas de maladie d'un artiste, le Producteur en informe l'Organisateur sans délai. Si la représentation s'en trouve empêchée, l'obligation de jouer et celle de payer tombent l'une et l'autre, sans autre indemnité de part ni d'autre."],

  ['bkey' => 'fr_force_majeure', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Force majeure',
   'body' => "Si la représentation devient impossible par force majeure — notamment intempérie, décision administrative ou sinistre —, les obligations des deux parties tombent sans indemnité. Les sommes déjà versées sont restituées ; chaque partie supporte les dépenses qu'elle ne pouvait plus éviter.",
   'hinweis' => "Sans clause expresse, c'est le droit commun qui s'applique, et il ne garantit aucun cachet lorsqu'une autorité interdit la manifestation. Qui veut autre chose doit l'écrire ici."],

  // ---------- Obligations de l'organisateur ----------
  ['bkey' => 'fr_technique_org', 'gruppe' => 'veranstalter', 'wahl' => 'Technique', 'an' => 1,
   'label' => 'Technique : fournie par l\'Organisateur',
   'body' => "L'Organisateur met à disposition une scène praticable, une alimentation électrique suffisante ainsi qu'une sonorisation et un éclairage adaptés au lieu. Pendant le montage, les balances et la représentation, une personne sachant exploiter le matériel est joignable."],

  ['bkey' => 'fr_technique_prod', 'gruppe' => 'veranstalter', 'wahl' => 'Technique',
   'label' => 'Technique : apportée par le Producteur',
   'body' => "L'Organisateur met à disposition le lieu et l'alimentation électrique. Le Producteur apporte la sonorisation et l'éclairage dans une mesure adaptée au lieu. L'Organisateur communique la jauge attendue au plus tard quatre semaines avant la date et permet une visite des lieux sur demande."],

  ['bkey' => 'fr_fiche_technique', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'La fiche technique fait partie du contrat',
   'body' => "La fiche technique du Producteur fait partie intégrante du présent contrat. Toute divergence est convenue en temps utile entre les parties."],

  ['bkey' => 'fr_securite', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Sécurité, autorisations et assurance',
   'body' => "L'Organisateur obtient les autorisations administratives nécessaires, assure la sécurité du public et du plateau et souscrit une assurance de responsabilité civile couvrant la manifestation. Il répond de la sécurité des personnes du Producteur et du matériel apporté pendant toute la durée de leur présence sur les lieux."],

  ['bkey' => 'fr_loges', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Loges',
   'body' => "L'Organisateur met à disposition une loge fermant à clé et chauffable."],

  ['bkey' => 'fr_catering', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Boissons',
   'body' => "L'Organisateur met gratuitement à disposition des boissons en quantité raisonnable pendant le montage, les balances et la représentation."],

  ['bkey' => 'fr_repas', 'gruppe' => 'veranstalter',
   'label' => 'Repas',
   'body' => "L'Organisateur fournit un repas chaud par jour de représentation. Nombre : ____, dont végétariens : ____."],

  ['bkey' => 'fr_hebergement', 'gruppe' => 'veranstalter',
   'label' => 'Hébergement',
   'body' => "L'Organisateur prend en charge l'hébergement et le petit-déjeuner de ____ personnes, en ____ chambres simples et ____ chambres doubles. L'établissement est indiqué au plus tard une semaine avant la date."],

  ['bkey' => 'fr_plein_air', 'gruppe' => 'veranstalter',
   'label' => 'Plein air',
   'body' => "En plein air, la scène et la régie sont couvertes et protégées des intempéries. L'Organisateur assure un sol plan et porteur."],

  ['bkey' => 'fr_promotion', 'gruppe' => 'veranstalter',
   'label' => 'Promotion',
   'body' => "L'Organisateur assure la promotion du spectacle dans une mesure raisonnable et mentionne le Producteur sur toutes les annonces dans l'orthographe qui lui a été communiquée."],

  ['bkey' => 'fr_invitations', 'gruppe' => 'veranstalter',
   'label' => 'Invitations',
   'body' => "Le Producteur dispose d'une liste d'invitations de deux entrées par membre, remise à l'Organisateur avant l'ouverture des portes."],

  ['bkey' => 'fr_captation', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Captation sonore et visuelle',
   'body' => "Toute captation sonore, photographique ou audiovisuelle des balances et de la représentation, au-delà de l'usage privé du public, requiert l'accord préalable du Producteur. Une captation destinée à la communication de l'Organisateur fait l'objet d'un accord distinct."],

  // ---------- Obligations et droits de l'artiste ----------
  ['bkey' => 'fr_horaires', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Respect des horaires',
   'body' => "Le Producteur respecte les horaires convenus pour le montage, les balances et la représentation."],

  ['bkey' => 'fr_programme', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Liberté du programme',
   'body' => "Le Producteur établit librement son programme. Toute demande particulière de l'Organisateur fait l'objet d'un accord distinct."],

  ['bkey' => 'fr_remplacement', 'gruppe' => 'kuenstler',
   'label' => 'Remplacement d\'un musicien',
   'body' => "En cas d'indisponibilité d'un musicien, le Producteur peut le remplacer par une personne de qualité équivalente, sans que l'Organisateur puisse en tirer un droit."],

  ['bkey' => 'fr_promo_materiel', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Matériel de promotion',
   'body' => "Le Producteur fournit gratuitement à l'Organisateur une présentation, un texte de presse et des photographies pour annoncer la représentation. Les droits afférents lui restent acquis ; l'usage est limité à la promotion de cette représentation."],

  // ---------- Dispositions finales ----------
  ['bkey' => 'fr_nullite', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Clause de sauvegarde',
   'body' => "Si une stipulation du présent contrat se révèle nulle ou inapplicable, les autres demeurent en vigueur. Les parties y substituent une stipulation licite s'approchant au plus près de l'intention économique."],

  ['bkey' => 'fr_ecrit', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Pas d\'accord verbal',
   'body' => "Il n'existe aucun accord verbal. Toute modification ou tout complément du présent contrat se fait par écrit, un échange de courriels étant suffisant."],

  ['bkey' => 'fr_litiges', 'gruppe' => 'schluss',
   'label' => 'Droit applicable et litiges',
   'body' => "Le présent contrat est soumis au droit français. À défaut d'accord amiable, le litige relève des juridictions compétentes.",
   'hinweis' => "Une clause désignant un tribunal précis ne s'impose qu'entre commerçants. Face à une association ou à un particulier, la compétence légale s'applique quoi qu'il soit écrit — mieux vaut ne rien promettre d'autre."],

  ['bkey' => 'fr_signature', 'gruppe' => 'fuss', 'fest' => 1, 'an' => 1,
   'label' => 'Signatures',
   'body' => "Fait en deux exemplaires.\n\nLieu, date : ......................        Lieu, date : {heute}\n\n\n__________________________                __________________________\nL'Organisateur                            Le Producteur"],
];
