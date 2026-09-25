<?php
// Der englische Bausteinsatz (#360).
//
// Dieser eine ist bewusst OHNE Landesrecht. Englisch ist hier nicht das Recht
// eines Landes, sondern die Sprache, in der ein Auftritt über die Grenze
// verhandelt wird — zwischen einer deutschen Band und einem dänischen
// Veranstalter etwa. Ein Satz, der stillschweigend englisches Recht
// unterstellte, wäre für die meisten seiner Benutzer falsch.
//
// Deshalb: Wo etwas ans Landesrecht gehört, steht eine ausdrückliche Lücke im
// Text — die Verwertungsgesellschaft wird benannt, nicht erraten, und Recht
// und Gerichtsstand sind eine bewusste Entscheidung und keine Voreinstellung.
// Diese Bausteine tragen dazu einen Hinweis.
//
// Dazu die zwei Dinge, die bei einem Auftritt über die Grenze wirklich weh
// tun und die in keinem der nationalen Sätze so stehen müssen: die
// Quellensteuer auf Künstlerhonorare und die Frage, wer die Sozialabgaben
// schuldet.

return [

  ['bkey' => 'en_head', 'gruppe' => 'kopf', 'fest' => 1, 'an' => 1,
   'label' => 'Contract head',
   'body' => "PERFORMANCE AGREEMENT\n\nbetween\n{veranstalter}\n{veranstalter_anschrift}\n— the Promoter —\n\nand\n{band}\n— the Artist —"],

  // ---------- Subject ----------
  ['bkey' => 'en_subject', 'gruppe' => 'rahmen', 'fest' => 1, 'an' => 1,
   'label' => 'Subject',
   'body' => "The Promoter engages the Artist for the following performance:\nVenue: {ort}\nDate: {datum}\nPlaying time: {spielzeit}"],

  ['bkey' => 'en_getin', 'gruppe' => 'rahmen', 'an' => 1,
   'label' => 'Get-in and soundcheck',
   'body' => "The room is available from {einlass} for get-in and soundcheck. The Artist has undisturbed use of the stage until doors."],

  ['bkey' => 'en_sets', 'gruppe' => 'rahmen',
   'label' => 'Sets and interval',
   'body' => "The Artist plays ____ sets of about ____ minutes each, with an interval of ____ minutes. An encore of up to ____ minutes is agreed."],

  // ---------- Fee and costs ----------
  ['bkey' => 'en_fee_flat', 'gruppe' => 'gage', 'wahl' => 'Fee', 'an' => 1,
   'label' => 'Flat fee',
   'body' => "The Promoter pays the Artist a fee of {gage} for the performance. This covers everything the Artist owes under this agreement unless stated otherwise below."],

  ['bkey' => 'en_fee_door', 'gruppe' => 'gage', 'wahl' => 'Fee',
   'label' => 'Share of the door',
   'body' => "The Promoter pays the Artist ____ % of the ticket income, calculated after deduction of any turnover tax. Admission is ____, concession ____. The Promoter hands over the ticket count immediately after the performance."],

  ['bkey' => 'en_fee_mixed', 'gruppe' => 'gage', 'wahl' => 'Fee',
   'label' => 'Guarantee against a share',
   'body' => "The Promoter guarantees a minimum of {gage}. If ____ % of the ticket income exceeds that, the Artist receives that share instead of the guarantee. The ticket count is handed over immediately after the performance."],

  ['bkey' => 'en_pay_invoice', 'gruppe' => 'gage', 'wahl' => 'Payment', 'an' => 1,
   'label' => 'Payment: on invoice',
   'body' => "The Artist invoices after the performance. The amount falls due without deduction within 14 days of the invoice date."],

  ['bkey' => 'en_pay_cash', 'gruppe' => 'gage', 'wahl' => 'Payment',
   'label' => 'Payment: cash on the night',
   'body' => "The fee is paid in cash against a receipt at the end of the performance."],

  ['bkey' => 'en_currency', 'gruppe' => 'gage',
   'label' => 'Currency and bank charges',
   'body' => "All amounts are in ____ . The Promoter bears the charges of his own bank; charges of the Artist's bank are borne by the Artist. The fee reaches the Artist in full, without deduction for currency conversion."],

  ['bkey' => 'en_rights_society', 'gruppe' => 'gage', 'fest' => 1, 'an' => 1,
   'label' => 'Music licensing',
   'body' => "Fees for the public performance of music are borne by the Promoter. He holds the licence required at the place of performance from the competent collecting society (____) and registers the event in good time. The Artist supplies the list of works performed.",
   'hinweis' => "Fill in the society for the country you are playing in — GEMA in Germany, SACEM in France, Buma/Stemra in the Netherlands, SGAE in Spain, SIAE in Italy, PRS in the United Kingdom. In most countries registering before the date is both obligatory and cheaper. The blank is deliberate: naming the wrong society is worse than naming none."],

  ['bkey' => 'en_social', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Social security and payroll charges',
   'body' => "Any levies, contributions or payroll charges that the law of the place of performance imposes on the engagement of performers are borne by the Promoter. He carries out the registrations required there before the performance.",
   'hinweis' => "This differs sharply from country to country and several of them put the duty on the organiser whatever the contract says: the artists' social fund levy in Germany, the presumption of employment and the GUSO scheme in France, the artist withholding scheme in the Netherlands, the INPS agibilità certificate in Italy. Ask the promoter what applies where you are playing, and do it before the day."],

  ['bkey' => 'en_withholding', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Withholding tax on foreign artists',
   'body' => "If the law of the place of performance requires tax to be withheld from the fee of a non-resident performer, the Promoter withholds and remits it and gives the Artist a certificate stating the amount withheld. The parties exchange the certificates of residence needed to apply any double taxation treaty in good time before the performance.",
   'hinweis' => "The one that costs money quietly: many countries tax a foreign performer at source, and without the residence certificate in hand before the night the full rate is taken and the refund takes a year."],

  ['bkey' => 'en_vat', 'gruppe' => 'gage',
   'label' => 'Turnover tax',
   'body' => "Amounts are stated net. Any turnover tax is added at the statutory rate and shown on the invoice. The parties exchange their tax numbers before invoicing."],

  ['bkey' => 'en_travel', 'gruppe' => 'gage',
   'label' => 'Travel',
   'body' => "The Promoter reimburses travel at ____ per kilometre driven, counted from the Artist's rehearsal room to the venue and back."],

  ['bkey' => 'en_own_pa', 'gruppe' => 'gage',
   'label' => 'Payment for the Artist\'s own PA',
   'body' => "If the Artist supplies the sound and lighting system, he receives a further ____ for it."],

  ['bkey' => 'en_cancel_promoter', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Cancellation by the Promoter',
   'body' => "If the Promoter cancels, or the performance does not take place for a reason within his responsibility, the agreed fee remains payable in full. Expenses the Artist actually saves are set off against it."],

  ['bkey' => 'en_cancel_artist', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Cancellation by the Artist',
   'body' => "If the Artist cannot perform for a reason within his responsibility, the claim to the fee falls away. He informs the Promoter without delay and makes an effort to find an equivalent replacement."],

  ['bkey' => 'en_illness', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Illness',
   'body' => "If a member falls ill, the Artist informs the Promoter without delay. If the performance is thereby impossible, both the duty to play and the duty to pay fall away, and neither side has any further claim."],

  ['bkey' => 'en_force_majeure', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Force majeure',
   'body' => "If the performance becomes impossible through force majeure — among others natural events, an order of a public authority, or disaster — the obligations of both sides fall away without compensation. Sums already paid are returned; expenses that could no longer be avoided are borne by the side that incurred them.",
   'hinweis' => "Worth having in writing. In most countries the default rule gives the artist nothing when an authority calls the show off, so anyone who wants a different answer has to put it here."],

  ['bkey' => 'en_visa', 'gruppe' => 'gage',
   'label' => 'Visas, permits and carnets',
   'body' => "The Promoter states in good time which entry, work and equipment formalities apply at the place of performance, and provides the invitation or engagement documents needed for them. Costs of visas, work permits and customs carnets are borne by ____ ."],

  // ---------- The Promoter's obligations ----------
  ['bkey' => 'en_tech_promoter', 'gruppe' => 'veranstalter', 'wahl' => 'Technical', 'an' => 1,
   'label' => 'Technical: supplied by the Promoter',
   'body' => "The Promoter provides a playable stage with sufficient power, and a sound and lighting system appropriate to the room. During get-in, soundcheck and performance a person able to operate the system is reachable."],

  ['bkey' => 'en_tech_artist', 'gruppe' => 'veranstalter', 'wahl' => 'Technical',
   'label' => 'Technical: brought by the Artist',
   'body' => "The Promoter provides the room and the power supply. The Artist brings a sound and lighting system appropriate to the room. The Promoter states the expected audience number at least four weeks before the date and allows a site visit on request."],

  ['bkey' => 'en_tech_info', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Details of the system in advance',
   'body' => "The Promoter states, at the latest one week before the performance, what system is in the room and who operates it."],

  ['bkey' => 'en_rider', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'The rider is part of the agreement',
   'body' => "The Artist's technical rider forms part of this agreement. The Promoter agrees any deviation with the Artist in good time."],

  ['bkey' => 'en_liability', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Safety and liability',
   'body' => "For as long as the Artist is at the venue, the Promoter is responsible for the safety of the Artist and his crew and for the equipment and instruments brought in. The Artist is liable for damage he causes himself."],

  ['bkey' => 'en_permits', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Permits, security and insurance',
   'body' => "The Promoter obtains the permits required for the event, is responsible for the safety of the audience and the stage, and holds public liability insurance covering the event."],

  ['bkey' => 'en_dressing', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Dressing room',
   'body' => "The Promoter provides a lockable, heatable room as a dressing room."],

  ['bkey' => 'en_drinks', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Drinks',
   'body' => "The Promoter provides the Artist and his crew with drinks in reasonable quantity, free of charge, during get-in, soundcheck and performance."],

  ['bkey' => 'en_meal', 'gruppe' => 'veranstalter',
   'label' => 'Hot meal',
   'body' => "The Promoter provides one hot meal per performance day for the Artist and his crew. Number: ____, of which vegetarian: ____."],

  ['bkey' => 'en_hotel', 'gruppe' => 'veranstalter',
   'label' => 'Accommodation',
   'body' => "The Promoter bears the cost of accommodation with breakfast for ____ people in ____ single and ____ twin rooms. The house is named to the Artist at the latest one week before the date."],

  ['bkey' => 'en_crew', 'gruppe' => 'veranstalter',
   'label' => 'Crew for get-in and get-out',
   'body' => "The Promoter provides ____ helpers each for get-in and get-out."],

  ['bkey' => 'en_openair', 'gruppe' => 'veranstalter',
   'label' => 'Open air',
   'body' => "If the event is in the open air, stage and mix position are covered and protected against the weather. The Promoter provides a level, load-bearing surface."],

  ['bkey' => 'en_promotion', 'gruppe' => 'veranstalter',
   'label' => 'Promotion',
   'body' => "The Promoter advertises the event to a reasonable extent and names the Artist on all announcements in the spelling the Artist has given."],

  ['bkey' => 'en_guestlist', 'gruppe' => 'veranstalter',
   'label' => 'Guest list',
   'body' => "The Artist may keep a guest list of two free admissions per member, handed to the Promoter before doors."],

  ['bkey' => 'en_recording', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Sound and picture recording',
   'body' => "Sound, photographic, film and video recording of the soundcheck and the performance requires the Artist's prior consent beyond the private use of the audience. Recordings the Promoter wishes to use for his own advertising require a separate agreement."],

  // ---------- The Artist's obligations and rights ----------
  ['bkey' => 'en_times', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Keeping to the times',
   'body' => "The Artist keeps to the agreed times for get-in, soundcheck and performance."],

  ['bkey' => 'en_programme', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Freedom of programme',
   'body' => "The Artist shapes his own programme. Particular wishes of the Promoter require a separate agreement."],

  ['bkey' => 'en_deputy', 'gruppe' => 'kuenstler',
   'label' => 'Replacing a member',
   'body' => "If a single member drops out, the Artist may replace them with an equivalent player, and the Promoter derives no claim from this."],

  ['bkey' => 'en_promo_material', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Promotional material',
   'body' => "The Artist supplies the Promoter, free of charge, with band information, a press text and photographs to announce the event. The rights in them remain with the Artist; use is limited to advertising this event."],

  // ---------- Final provisions ----------
  ['bkey' => 'en_severability', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Severability',
   'body' => "If a provision of this agreement is void or unenforceable, the remaining provisions stay in force. The parties replace it with a permissible provision that comes closest to what was economically intended."],

  ['bkey' => 'en_written', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'No verbal side agreements',
   'body' => "There are no verbal side agreements. Changes and additions to this agreement are made in writing; an exchange of emails is sufficient."],

  ['bkey' => 'en_law', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Governing law and jurisdiction',
   'body' => "This agreement is governed by the law of ____ . The courts competent there hear any dispute.",
   'hinweis' => "Left blank on purpose. This set is written for a performance across a border, where the law is a decision and not a default. Usually it is the law of the place of performance — that is where the venue, the licensing and the permits sit. Against a private person or an association a chosen court often does not hold whatever the contract says."],

  ['bkey' => 'en_signature', 'gruppe' => 'fuss', 'fest' => 1, 'an' => 1,
   'label' => 'Signatures',
   'body' => "Place, date: ......................        Place, date: {heute}\n\n\n__________________________                __________________________\nThe Promoter                              The Artist"],
];
