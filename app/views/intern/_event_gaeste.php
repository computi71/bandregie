<?php
// Gäste an diesem Abend (#294): wer gebucht ist, ob er zugesagt hat, nach
// dem Abend die Sterne — und für die, die buchen dürfen, das Formular. Nur
// bei Gigs: zur Probe lädt man keinen Tontechniker ein.
//
// Vorher setzen: $ev, $user, $guestsByEvent, $guestList, $guestRatings.
?>
<?php $gaeste = $guestsByEvent[$ev['id']] ?? []; $darfBuchen = perm_allows($user, 'gaeste', 'write') && $ev['date'] >= date('Y-m-d') && !is_demo(); ?>
<?php if (perm_allows($user, 'gaeste') && ($gaeste || ($darfBuchen && $ev['type'] === 'gig'))): ?>
  <div class="guests small">
    <?php foreach ($gaeste as $g): ?>
      <?php if ($g['status'] === 'storniert') continue; ?>
      <div class="guest-row">
        🎟 <strong><?= e($g['guest_name']) ?></strong><?= $g['function_name'] !== '' ? ' · ' . e($g['function_name']) : '' ?>
        <span class="badge <?= $g['status'] === 'zugesagt' ? 'public' : ($g['status'] === 'abgesagt' ? 'ev-abgesagt' : '') ?>"><?= e(guest_status_label($g['status'])) ?></span>
        <?php if ($g['status'] === 'angefragt'): ?>
          <span class="muted"><?= $g['invited_at'] ? e(t('guest_invited')) . ' ' . e(date('d.m. H:i', strtotime($g['invited_at']))) : e(t('guest_not_invited')) ?></span>
        <?php elseif ($g['answered_at']): ?>
          <span class="muted"><?= e(t('guest_answered')) ?> <?= e(date('d.m. H:i', strtotime($g['answered_at']))) ?></span>
        <?php endif; ?>
        <?php // Nach dem Abend die Sterne (#294): je Mitglied eine Stimme, der Schnitt
              // daneben. Nur für zugesagte Einsätze vergangener Termine. ?>
        <?php $gr = $guestRatings[(int) $g['id']] ?? null; ?>
        <?php if ($g['status'] === 'zugesagt' && $ev['date'] < date('Y-m-d')): ?>
          <span class="muted" title="<?= e(t('guest_rating')) ?>"><?= $gr ? e(guest_stars($gr['avg'])) . ' ' . e((string) $gr['avg']) . ' (' . (int) $gr['n'] . ')' : e(t('guest_rating_none')) ?></span>
          <?php if (perm_allows($user, 'gaeste', 'write') && !is_demo()): ?>
            <details class="inline">
              <summary class="btn btn-tiny btn-ghost">★ <?= e(t('guest_rate')) ?></summary>
              <form method="post" action="/intern/gaeste/buchung/<?= (int) $g['id'] ?>/bewerten" class="inline rating"><?= csrf_field() ?>
                <span class="muted"><?= e(t('guest_rating_hint')) ?></span>
                <?php for ($s = 1; $s <= 5; $s++): ?><label class="checkbox"><input type="radio" name="stars" value="<?= $s ?>" <?= ($gr['mine'] ?? 0) === $s ? 'checked' : '' ?>> <?= str_repeat('★', $s) ?></label><?php endfor; ?>
                <input name="comment" maxlength="255" placeholder="<?= e(t('guest_rating_comment_ph')) ?>">
                <button class="btn btn-tiny"><?= e(t('save')) ?></button>
              </form>
            </details>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($darfBuchen && $g['status'] !== 'abgesagt'): ?>
          <?php // Der Link steht hier für den Fall, dass der Gast keine Mail hat
                // oder sie nicht ankam — er ist sein Schlüssel, also nur für die,
                // die auch buchen dürfen. ?>
          <a class="badge link" href="/gast/<?= e($g['token']) ?>" target="_blank" rel="noopener">🔗 Link</a>
          <?php if ($g['guest_email'] !== '' && $g['status'] === 'angefragt'): ?>
            <form class="inline" method="post" action="/intern/gaeste/buchung/<?= (int) $g['id'] ?>/erneut"><?= csrf_field() ?><button class="btn btn-tiny btn-ghost">✉ <?= e(t('guest_invite_resend')) ?></button></form>
          <?php endif; ?>
          <?php // Ohne Mail — oder zusätzlich: die Einladung vom eigenen Handy aus,
                // WhatsApp oder SMS mit fertigem Text. Öffnet nur die App; gesendet
                // wird dort (#299). ?>
          <?php if ($g['status'] === 'angefragt'): ?>
            <?php foreach (guest_share_links($g) as $weg => $url): ?>
              <a class="badge link" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= $weg === 'whatsapp' ? '💬' : '📱' ?> <?= e(t('guest_send_' . $weg)) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
          <form class="inline" method="post" action="/intern/gaeste/buchung/<?= (int) $g['id'] ?>/stornieren" data-confirm="<?= e(t('guest_cancel_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger" title="<?= e(t('guest_cancel')) ?>">✕</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($darfBuchen && $ev['type'] === 'gig'): ?>
      <details class="subsection">
        <summary>🎟 <?= e(t('guest_book')) ?></summary>
        <form method="post" action="/intern/gaeste/buchen" class="form-grid"><?= csrf_field() ?>
          <input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
          <label><?= e(t('inav_gaeste')) ?>
            <select name="guest_id"><option value="">– <?= e(t('guest_book_new')) ?> –</option>
              <?php foreach ($guestList as $gl): ?><option value="<?= (int) $gl['id'] ?>"><?= e($gl['name']) ?><?= $gl['function_name'] !== '' ? ' (' . e($gl['function_name']) . ')' : '' ?></option><?php endforeach; ?>
            </select>
          </label>
          <label><?= e(t('guest_function')) ?><input name="function_name" placeholder="<?= e(t('guest_book_function_ph')) ?>"></label>
          <label><?= e(t('name')) ?> (<?= e(t('guest_book_new')) ?>)<input name="new_name"></label>
          <label><?= e(t('email')) ?> (<?= e(t('guest_book_new')) ?>)<input type="email" name="new_email"></label>
          <label><?= e(t('phone')) ?> (<?= e(t('guest_book_new')) ?>)<input name="new_phone" maxlength="60"></label>
          <label><?= e(t('mem_mobile')) ?> (<?= e(t('guest_book_new')) ?>)<input name="new_mobile" maxlength="60"></label>
          <p class="muted small span2"><?= e(t('guest_contact_hint')) ?></p>
          <div class="span2 row-buttons"><button class="btn btn-small">✉ <?= e(t('guest_invite_send')) ?></button></div>
        </form>
      </details>
    <?php endif; ?>
  </div>
<?php endif; ?>
