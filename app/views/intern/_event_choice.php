<?php
// Der Block „Zur Wahl" (#443): eine Anfrage mit mehreren möglichen Daten, an
// der Stelle ihres frühesten Datums. Je Datum der Stand der Antworten und der
// Knopf, mit dem man es nimmt. Die Karten der einzelnen Daten stehen darunter
// wie jeder andere Termin; dort antwortet man, kommentiert und sieht alles
// Weitere.
//
// Hervorgehoben wird nur ein Datum, an dem wirklich alle ja gesagt haben, die
// gefragt sind. Entschieden wird trotzdem von Hand: Bei einem Gig hat der
// Veranstalter das letzte Wort, und die Band meldet ihm nur, was geht.
//
// Vorher setzen:
//   $wahl — ein Eintrag aus choiceByEvent (Pflicht)
//   $ev   — das früheste Datum der Auswahl (Pflicht)
//   $user — wer die Seite ansieht (Pflicht)
$wahlDarf = perm_allows($user, 'termine', 'write');
?>
<section class="card choice-block">
  <h3><span class="badge ev-choice"><?= e(t('ev_choice')) ?></span> <?= e($ev['title']) ?></h3>
  <ul class="choice-dates">
    <?php foreach ($wahl['dates'] as $wd): ?>
      <li class="choice-date<?= $wd['all_yes'] ? ' ev-choice-all' : '' ?>" data-ev="<?= (int) $wd['id'] ?>">
        <a href="#ev<?= (int) $wd['id'] ?>"><?= fmt_date($wd['date']) ?></a>
        <?php // Ein eigener Teil, damit zusage.js nach einer Antwort genau ihn
              // austauschen kann: Die Formulare daneben bleiben, wie sie sind,
              // samt ihrer Rückfrage vor dem Wählen. ?>
        <span class="choice-stand">
          <span class="att att-yes">✔ <?= (int) $wd['yes'] ?></span>
          <span class="att att-maybe">? <?= (int) $wd['maybe'] ?></span>
          <span class="att att-no">✘ <?= (int) $wd['no'] ?></span>
          <?php if ($wd['open']): ?>
            <span class="muted small"><?= e(str_replace('%1', (string) $wd['open'], t('ev_choice_open'))) ?></span>
          <?php endif; ?>
          <?php if ($wd['all_yes']): ?><strong class="small"><?= e(t('ev_choice_all')) ?></strong><?php endif; ?>
        </span>
        <?php // Gleich hier antworten (#444), statt jede Karte darunter einzeln
              // aufzuklappen. data-stay: im Hintergrund abschicken und die Zeile
              // stehen lassen — anders als in der Übersicht ist sie danach
              // nicht erledigt, man will die Antwort sehen und ändern können. ?>
        <?php $wdMein = (string) ($wd['mine'] ?? ''); ?>
        <form class="inline attendance" data-vote data-stay data-own-date="0" action="/intern/termine/<?= (int) $wd['id'] ?>/zusage" method="post"><?= csrf_field() ?>
          <button name="status" value="yes" class="chip<?= $wdMein === 'yes' ? ' chip-yes' : '' ?>"><?= e(t('att_yes')) ?></button>
          <button name="status" value="maybe" class="chip<?= $wdMein === 'maybe' ? ' chip-maybe' : '' ?>"><?= e(t('att_maybe')) ?></button>
          <button name="status" value="no" class="chip<?= $wdMein === 'no' ? ' chip-no' : '' ?>"><?= e(t('att_no')) ?></button>
        </form>
        <?php if ($wahlDarf): ?>
          <form class="inline" method="post" action="/intern/termine/<?= (int) $wd['id'] ?>/waehlen"
                data-confirm="<?= e(t('ev_choice_take_confirm')) ?>"><?= csrf_field() ?>
            <button class="btn btn-tiny"><?= e(t('ev_choice_take')) ?></button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
