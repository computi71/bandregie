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
        <span class="att att-yes">✔ <?= (int) $wd['yes'] ?></span>
        <span class="att att-maybe">? <?= (int) $wd['maybe'] ?></span>
        <span class="att att-no">✘ <?= (int) $wd['no'] ?></span>
        <?php if ($wd['open']): ?>
          <span class="muted small"><?= e(str_replace('%1', (string) $wd['open'], t('ev_choice_open'))) ?></span>
        <?php endif; ?>
        <?php if ($wd['all_yes']): ?><strong class="small"><?= e(t('ev_choice_all')) ?></strong><?php endif; ?>
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
