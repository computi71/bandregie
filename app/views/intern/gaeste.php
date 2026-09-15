<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php // Die Kontaktliste der Gäste (#294): wer schon einmal dabei war, mit
      // seinen Einsätzen. Gebucht wird an der Terminkarte — hier wird gepflegt. ?>
<h1>🎟 <?= e(t('guest_title')) ?></h1>
<p class="muted"><?= e(t('guest_intro')) ?></p>

<?php if (perm_allows($user, 'gaeste', 'write')): ?>
<details class="card collapsible">
  <summary>➕ <?= e(t('guest_new')) ?></summary>
  <form method="post" action="/intern/gaeste" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('name')) ?><input name="name" required maxlength="190"></label>
    <label><?= e(t('guest_function')) ?><input name="function_name" maxlength="120" placeholder="<?= e(t('guest_function_ph')) ?>"></label>
    <label><?= e(t('email')) ?><input type="email" name="email" maxlength="190"></label>
    <label><?= e(t('phone')) ?><input name="phone" maxlength="60"></label>
    <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"></textarea></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>
<?php endif; ?>

<?php if (!$guests): ?>
  <p class="muted"><?= e(t('guest_none')) ?></p>
<?php endif; ?>

<?php foreach ($guests as $g): ?>
  <?php $einsaetze = $bookingsByGuest[(int) $g['id']] ?? []; $gezaehlt = array_filter($einsaetze, fn($b) => $b['status'] === 'zugesagt'); ?>
  <?php // Der Schnitt über alle bewerteten Einsätze — das ist die Zahl, die man
        // sucht, wenn man den Namen nach einem Jahr wieder liest. ?>
  <?php $gSum = 0; $gN = 0; foreach ($einsaetze as $b) { if ($r = $ratings[(int) $b['id']] ?? null) { $gSum += $r['sum']; $gN += $r['n']; } } ?>
  <section class="card">
    <div class="event-head">
      <strong><?= e($g['name']) ?></strong>
      <?php if ($g['function_name'] !== ''): ?><span class="muted"><?= e($g['function_name']) ?></span><?php endif; ?>
      <span class="badge"><?= count($gezaehlt) ?> <?= e(t('guest_bookings')) ?></span>
      <?php if ($gN): ?><span class="badge" title="<?= e(t('guest_rating')) ?>"><?= e(guest_stars($gSum / $gN)) ?> <?= e((string) round($gSum / $gN, 1)) ?> (<?= $gN ?>)</span><?php endif; ?>
    </div>
    <p class="muted small">
      <?php if ($g['email'] !== ''): ?>✉ <a href="mailto:<?= e($g['email']) ?>"><?= e($g['email']) ?></a><?php endif; ?>
      <?php if ($g['phone'] !== ''): ?> · 📞 <a href="tel:<?= e(preg_replace('~[^+\d]~', '', $g['phone'])) ?>"><?= e($g['phone']) ?></a><?php endif; ?>
    </p>
    <?php if (trim((string) $g['notes']) !== ''): ?><p class="prewrap muted small"><?= e($g['notes']) ?></p><?php endif; ?>

    <?php if ($einsaetze): ?>
      <ul class="small">
        <?php foreach ($einsaetze as $b): ?>
          <li>
            <?= e(fmt_date($b['date'])) ?> · <a href="/intern/termine?alle=1"><?= e($b['title']) ?></a>
            <?= $b['function_name'] !== '' ? ' · ' . e($b['function_name']) : '' ?>
            <span class="badge <?= $b['status'] === 'zugesagt' ? 'public' : ($b['status'] === 'abgesagt' ? 'ev-abgesagt' : '') ?>"><?= e(guest_status_label($b['status'])) ?></span>
            <?php if ($b['status'] === 'zugesagt' && strtotime($b['access_until']) > time()): ?>
              <span class="muted"><?= e(t('guest_link_until')) ?> <?= e(date('d.m. H:i', strtotime($b['access_until']))) ?></span>
            <?php endif; ?>
            <?php if ($r = $ratings[(int) $b['id']] ?? null): ?>
              <span class="muted" title="<?= e(implode(' · ', array_map(fn($x) => $x['name'] . ': ' . $x['stars'] . '★' . ($x['comment'] !== '' ? ' – ' . $x['comment'] : ''), $r['rows']))) ?>"><?= e(guest_stars($r['avg'])) ?> <?= e((string) $r['avg']) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted small"><?= e(t('guest_no_bookings')) ?></p>
    <?php endif; ?>

    <?php if (perm_allows($user, 'gaeste', 'write')): ?>
      <details class="subsection">
        <summary>✏️ <?= e(t('edit')) ?></summary>
        <form method="post" action="/intern/gaeste/<?= (int) $g['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
          <label><?= e(t('name')) ?><input name="name" value="<?= e($g['name']) ?>" required maxlength="190"></label>
          <label><?= e(t('guest_function')) ?><input name="function_name" value="<?= e($g['function_name']) ?>" maxlength="120"></label>
          <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($g['email']) ?>" maxlength="190"></label>
          <label><?= e(t('phone')) ?><input name="phone" value="<?= e($g['phone']) ?>" maxlength="60"></label>
          <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e((string) $g['notes']) ?></textarea></label>
          <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
        </form>
        <?php if (!$einsaetze): ?>
          <form method="post" action="/intern/gaeste/<?= (int) $g['id'] ?>/delete" data-confirm="<?= e(t('guest_delete_confirm')) ?>" class="inline"><?= csrf_field() ?>
            <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
          </form>
        <?php endif; ?>
      </details>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
