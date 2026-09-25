<?php
// Der spanische Bausteinsatz (#360).
//
// KEINE Übersetzung des deutschen. Zwei Punkte weichen ab:
//
// 1. SGAE statt GEMA. Die Urheberrechtsabgabe schuldet der Veranstalter, und
//    sie gilt ausdrücklich NICHT als im caché der Band enthalten — das ist in
//    der spanischen Beratungsliteratur eigens hervorgehoben, weil genau daran
//    gestritten wird. Der Baustein sagt es deshalb ausdrücklich.
//
// 2. Real Decreto 1435/1985 regelt das besondere Arbeitsverhältnis der
//    Künstler bei öffentlichen Aufführungen. Wird die Band als Arbeitnehmer
//    tätig, muss der Veranstalter einen Arbeitsvertrag mit dem dort
//    verlangten Inhalt schließen und die Musiker bei der Sozialversicherung
//    anmelden. Der übliche Weg für eine Band mit eigener Rechnung ist der
//    Werkvertrag (contrato mercantil) zwischen Veranstalter und Band als
//    autónomos. Beides steht als Entweder-oder in der Gruppe.
//
// Ebenfalls Veranstalterpflicht: Genehmigungen, Sicherheit des Publikums und
// Arbeitsschutz der Musiker.
//
// Quellen: RD 1435/1985 (BOE-A-1985-17303); Leitfäden für contrato de
// actuación musical; SGAE.

return [

  ['bkey' => 'es_encabezado', 'gruppe' => 'kopf', 'fest' => 1, 'an' => 1,
   'label' => 'Encabezado',
   'body' => "CONTRATO DE ACTUACIÓN MUSICAL\n\nentre\n{veranstalter}\n{veranstalter_anschrift}\n— en adelante el Promotor —\n\ny\n{band}\n— en adelante el Grupo —"],

  // ---------- Objeto ----------
  ['bkey' => 'es_objeto', 'gruppe' => 'rahmen', 'fest' => 1, 'an' => 1,
   'label' => 'Objeto',
   'body' => "El Promotor contrata al Grupo para la siguiente actuación:\nLugar: {ort}\nFecha: {datum}\nHorario: {spielzeit}"],

  ['bkey' => 'es_montaje', 'gruppe' => 'rahmen', 'an' => 1,
   'label' => 'Montaje y prueba de sonido',
   'body' => "El recinto queda a disposición del Grupo desde las {einlass} para el montaje y la prueba de sonido. Hasta la apertura de puertas el Grupo dispone del escenario sin interrupciones."],

  ['bkey' => 'es_pases', 'gruppe' => 'rahmen',
   'label' => 'Pases y descanso',
   'body' => "El Grupo interpreta ____ pases de unos ____ minutos cada uno, con un descanso de ____ minutos. Se acuerda un bis de hasta ____ minutos."],

  // ---------- Naturaleza de la relación ----------
  ['bkey' => 'es_mercantil', 'gruppe' => 'rahmen', 'wahl' => 'Relación', 'an' => 1,
   'label' => 'Relación mercantil: el Grupo factura',
   'body' => "El Grupo actúa por cuenta propia y emite factura por la actuación. Sus integrantes están dados de alta en el régimen que les corresponde y asumen sus propias obligaciones fiscales y de Seguridad Social.",
   'hinweis' => "La vía habitual cuando el grupo factura. Exige que los integrantes estén realmente dados de alta; si no, la relación se recalifica y las consecuencias recaen sobre el promotor."],

  ['bkey' => 'es_laboral', 'gruppe' => 'rahmen', 'wahl' => 'Relación',
   'label' => 'Relación laboral especial (RD 1435/1985)',
   'body' => "La actuación se realiza en régimen de relación laboral especial de los artistas en espectáculos públicos. El Promotor formaliza con cada integrante el contrato con el contenido exigido por el Real Decreto 1435/1985 y tramita su alta en la Seguridad Social con anterioridad a la actuación.",
   'hinweis' => "Marcar solo si el promotor contrata realmente como empleador. Entonces el alta en la Seguridad Social es obligación suya y debe estar hecha antes de la actuación, no después."],

  // ---------- Caché y gastos ----------
  ['bkey' => 'es_cache_fijo', 'gruppe' => 'gage', 'wahl' => 'Caché', 'an' => 1,
   'label' => 'Caché fijo',
   'body' => "El Promotor abona al Grupo un caché de {gage} por la actuación. Dicho importe cubre todas las prestaciones previstas en este contrato, salvo lo que se pacte expresamente a continuación."],

  ['bkey' => 'es_cache_taquilla', 'gruppe' => 'gage', 'wahl' => 'Caché',
   'label' => 'Porcentaje de taquilla',
   'body' => "El Promotor abona al Grupo el ____ % de la recaudación de taquilla, calculada una vez descontado el IVA. La entrada cuesta ____ euros, reducida ____ euros. El Promotor entrega la liquidación de entradas vendidas inmediatamente después de la actuación."],

  ['bkey' => 'es_cache_mixto', 'gruppe' => 'gage', 'wahl' => 'Caché',
   'label' => 'Mínimo garantizado más porcentaje',
   'body' => "El Promotor garantiza un mínimo de {gage}. Si el ____ % de la recaudación de taquilla supera esa cantidad, el Grupo percibe dicho porcentaje en lugar del mínimo. La liquidación se entrega inmediatamente después de la actuación."],

  ['bkey' => 'es_pago_factura', 'gruppe' => 'gage', 'wahl' => 'Pago', 'an' => 1,
   'label' => 'Pago: por factura',
   'body' => "El Grupo emite factura tras la actuación. El importe es exigible sin descuento en el plazo de 30 días desde la fecha de factura."],

  ['bkey' => 'es_pago_efectivo', 'gruppe' => 'gage', 'wahl' => 'Pago',
   'label' => 'Pago: en efectivo la misma noche',
   'body' => "El caché se abona en efectivo al término de la actuación, contra recibo."],

  ['bkey' => 'es_sgae', 'gruppe' => 'gage', 'fest' => 1, 'an' => 1,
   'label' => 'Derechos de autor (SGAE)',
   'body' => "Los derechos de autor derivados de la comunicación pública de las obras corren a cargo del Promotor y no se entienden incluidos en el caché del Grupo. El Promotor comunica el espectáculo a la entidad de gestión con antelación y abona la tarifa correspondiente. El Grupo facilita la relación de obras interpretadas.",
   'hinweis' => "Figura siempre en el contrato: el pago a la SGAE es obligación directa del promotor, y la frase «no incluido en el caché» está ahí porque es justo donde surgen las discusiones."],

  ['bkey' => 'es_iva', 'gruppe' => 'gage',
   'label' => 'IVA',
   'body' => "Las cantidades se entienden sin IVA. El impuesto se añade al tipo legal y consta en la factura."],

  ['bkey' => 'es_irpf', 'gruppe' => 'gage',
   'label' => 'Retención de IRPF',
   'body' => "Sobre el importe facturado el Promotor practica la retención de IRPF que legalmente proceda y la ingresa en Hacienda, entregando al Grupo el correspondiente certificado."],

  ['bkey' => 'es_desplazamiento', 'gruppe' => 'gage',
   'label' => 'Desplazamiento',
   'body' => "El Promotor abona los gastos de desplazamiento a razón de ____ euros por kilómetro recorrido, desde el local de ensayo hasta el lugar de la actuación y vuelta."],

  ['bkey' => 'es_equipo_propio', 'gruppe' => 'gage',
   'label' => 'Compensación por equipo propio',
   'body' => "Si el Grupo aporta el equipo de sonido e iluminación, percibe además ____ euros."],

  ['bkey' => 'es_cancel_promotor', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Cancelación por el Promotor',
   'body' => "Si el Promotor cancela la actuación o esta no se celebra por causa que le sea imputable, el caché pactado sigue siendo exigible en su totalidad. Se descuentan los gastos que el Grupo haya evitado efectivamente."],

  ['bkey' => 'es_cancel_grupo', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Cancelación por el Grupo',
   'body' => "Si el Grupo no puede actuar por causa que le sea imputable, decae el derecho al caché. Lo comunica al Promotor sin demora y procura una sustitución equivalente."],

  ['bkey' => 'es_enfermedad', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Enfermedad',
   'body' => "En caso de enfermedad de un integrante, el Grupo lo comunica al Promotor sin demora. Si por ello la actuación no es posible, decaen tanto la obligación de actuar como la de pagar, sin que proceda indemnización alguna por ninguna de las partes."],

  ['bkey' => 'es_fuerza_mayor', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Fuerza mayor',
   'body' => "Si la actuación deviene imposible por fuerza mayor —entre otras, fenómenos naturales, resolución de la autoridad o siniestro—, decaen las obligaciones de ambas partes sin indemnización. Se devuelven las cantidades ya abonadas; cada parte soporta los gastos que ya no pudo evitar.",
   'hinweis' => "Sin una cláusula propia rige la regla general, que ante una suspensión acordada por la autoridad no suele dar derecho a caché. Quien quiera otra cosa ha de escribirla aquí."],

  // ---------- Obligaciones del Promotor ----------
  ['bkey' => 'es_tecnica_promotor', 'gruppe' => 'veranstalter', 'wahl' => 'Técnica', 'an' => 1,
   'label' => 'Técnica: la aporta el Promotor',
   'body' => "El Promotor facilita un escenario practicable con suministro eléctrico suficiente, así como equipo de sonido e iluminación adecuados al recinto. Durante el montaje, la prueba de sonido y la actuación hay una persona localizable capaz de manejar el equipo."],

  ['bkey' => 'es_tecnica_grupo', 'gruppe' => 'veranstalter', 'wahl' => 'Técnica',
   'label' => 'Técnica: la aporta el Grupo',
   'body' => "El Promotor facilita el recinto y el suministro eléctrico. El Grupo aporta el equipo de sonido e iluminación en una medida adecuada al lugar. El Promotor comunica el aforo previsto al menos cuatro semanas antes y permite una visita previa si se solicita."],

  ['bkey' => 'es_rider', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'El rider forma parte del contrato',
   'body' => "El rider técnico del Grupo forma parte de este contrato. El Promotor acuerda con antelación cualquier desviación."],

  ['bkey' => 'es_licencias', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Licencias, seguridad y seguro',
   'body' => "El Promotor obtiene las licencias y autorizaciones administrativas necesarias, responde de la seguridad del público y del escenario, atiende a la prevención de riesgos laborales de los músicos y dispone de un seguro de responsabilidad civil que cubra el espectáculo."],

  ['bkey' => 'es_responsabilidad', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Responsabilidad sobre personas y equipo',
   'body' => "Mientras el Grupo permanezca en el lugar de la actuación, el Promotor responde de la seguridad de sus integrantes y auxiliares, así como del equipo e instrumentos introducidos. De los daños causados por el propio Grupo responde este."],

  ['bkey' => 'es_camerino', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Camerino',
   'body' => "El Promotor pone a disposición del Grupo un camerino con cierre y posibilidad de calefacción."],

  ['bkey' => 'es_bebida', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Bebida',
   'body' => "El Promotor facilita gratuitamente al Grupo y a sus auxiliares bebida en cantidad razonable durante el montaje, la prueba de sonido y la actuación."],

  ['bkey' => 'es_comida', 'gruppe' => 'veranstalter',
   'label' => 'Comida caliente',
   'body' => "El Promotor facilita una comida caliente por jornada al Grupo y a sus auxiliares. Número: ____, de ellas vegetarianas: ____."],

  ['bkey' => 'es_alojamiento', 'gruppe' => 'veranstalter',
   'label' => 'Alojamiento',
   'body' => "El Promotor asume el alojamiento con desayuno de ____ personas en ____ habitaciones individuales y ____ dobles. El establecimiento se comunica al Grupo a más tardar una semana antes."],

  ['bkey' => 'es_aire_libre', 'gruppe' => 'veranstalter',
   'label' => 'Al aire libre',
   'body' => "Si la actuación se celebra al aire libre, el escenario y la mesa de mezclas están cubiertos y protegidos de la intemperie. El Promotor garantiza un suelo llano y resistente."],

  ['bkey' => 'es_promocion', 'gruppe' => 'veranstalter',
   'label' => 'Promoción',
   'body' => "El Promotor promociona la actuación en una medida razonable y menciona al Grupo en todos los anuncios con la grafía que este le haya facilitado."],

  ['bkey' => 'es_invitaciones', 'gruppe' => 'veranstalter',
   'label' => 'Invitaciones',
   'body' => "El Grupo puede disponer de una lista de invitados de dos entradas por integrante, entregada al Promotor antes de la apertura de puertas."],

  ['bkey' => 'es_grabaciones', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Grabaciones de sonido e imagen',
   'body' => "Las grabaciones de sonido, fotografía, cine o vídeo de la prueba de sonido y de la actuación requieren, más allá del uso privado del público, la autorización previa del Grupo. Las grabaciones que el Promotor pretenda emplear en su propia publicidad exigen acuerdo aparte."],

  // ---------- Obligaciones y derechos del Grupo ----------
  ['bkey' => 'es_horarios', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Cumplimiento de horarios',
   'body' => "El Grupo cumple los horarios acordados de montaje, prueba de sonido y actuación."],

  ['bkey' => 'es_programa', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Libertad de repertorio',
   'body' => "El Grupo configura libremente su repertorio. Las peticiones particulares del Promotor requieren acuerdo aparte."],

  ['bkey' => 'es_sustitucion', 'gruppe' => 'kuenstler',
   'label' => 'Sustitución de un integrante',
   'body' => "Si falta un integrante concreto, el Grupo puede sustituirlo por otro de nivel equivalente, sin que de ello nazcan derechos para el Promotor."],

  ['bkey' => 'es_material_promo', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Material de promoción',
   'body' => "El Grupo facilita gratuitamente al Promotor información, texto de prensa y fotografías para anunciar la actuación. Los derechos sobre ese material siguen siendo del Grupo; su uso se limita a la promoción de esta actuación."],

  // ---------- Disposiciones finales ----------
  ['bkey' => 'es_nulidad', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Nulidad parcial',
   'body' => "Si alguna cláusula de este contrato resultara nula o inaplicable, las demás conservan su validez. Las partes la sustituirán por otra admisible que se aproxime lo más posible a lo económicamente querido."],

  ['bkey' => 'es_por_escrito', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Sin acuerdos verbales',
   'body' => "No existen acuerdos verbales al margen de este contrato. Sus modificaciones y añadidos se harán por escrito, bastando el correo electrónico."],

  ['bkey' => 'es_jurisdiccion', 'gruppe' => 'schluss',
   'label' => 'Ley aplicable y jurisdicción',
   'body' => "Este contrato se rige por la ley española. A falta de acuerdo, las partes se someten a los juzgados y tribunales que resulten competentes."],

  ['bkey' => 'es_firmas', 'gruppe' => 'fuss', 'fest' => 1, 'an' => 1,
   'label' => 'Firmas',
   'body' => "Lugar, fecha: ......................        Lugar, fecha: {heute}\n\n\n__________________________                __________________________\nEl Promotor                               El Grupo"],
];
