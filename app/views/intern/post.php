<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>✉ <?= $imArchiv ? e(t('post_archive')) : e(t('post_title')) ?></h1>
<p class="muted"><?= e(t('post_intro')) ?></p>

<?php // Was fehlt, steht oben und nicht als leere Liste: Ein Postfach, das
      // niemand eingerichtet hat, sieht sonst aus wie eines ohne Post. ?>
<?php if (!$kannLesen): ?>
  <div class="card"><p class="warn">⚠ <?= e(t('post_no_imap')) ?></p></div>
<?php elseif (!$eingerichtet): ?>
  <div class="card">
    <p class="muted"><?= e(t('post_not_set_up')) ?></p>
    <a class="btn btn-small" href="/intern/einstellungen"><?= e(t('inav_einstellungen')) ?></a>
  </div>
<?php else: ?>
  <p>
    <?php if ($imArchiv): ?>
      <a class="btn btn-ghost btn-small" href="/intern/post">← <?= e(t('post_back')) ?></a>
    <?php else: ?>
      <form method="post" action="/intern/post/abholen" class="inline"><?= csrf_field() ?>
        <button class="btn btn-small">↻ <?= e(t('post_fetch')) ?></button>
      </form>
      <?php if ($archivZahl > 0): ?>
        <a class="btn btn-ghost btn-small" href="/intern/post?archiv=1">📦 <?= e(str_replace('%1', (string) $archivZahl, t('post_archived_view'))) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </p>
<?php endif; ?>

<?php if (!$messages): ?>
  <p class="muted center"><?= e(t('post_none')) ?></p>
<?php endif; ?>

<?php foreach ($messages as $msg): ?>
  <div class="card post-item">
    <?php // Betreff und Absender führen zur Nachricht; was schon erledigt ist,
          // steht als Marke daneben — beantwortet, oder mit Termin verbunden. ?>
    <strong><a href="/intern/post/<?= (int) $msg['id'] ?>"><?= e($msg['subject'] !== '' ? $msg['subject'] : '(' . t('post_title') . ')') ?></a></strong>
    <?php if ($msg['replied_at'] !== null): ?><span class="badge">↩ <?= e(t('post_reply')) ?></span><?php endif; ?>
    <?php if ($msg['event_id'] !== null): ?>
      <a class="badge public" href="/intern/termine">📅 <?= e($msg['event_title'] ?? '') ?></a>
    <?php endif; ?>
    <p class="muted small">
      <?= e($msg['from_name'] !== '' ? $msg['from_name'] . ' · ' : '') ?><?= e($msg['from_mail']) ?>
      <?php if ($msg['sent_at']): ?> · <?= e(fmt_date(substr((string) $msg['sent_at'], 0, 10))) ?><?php endif; ?>
    </p>
    <?php // Ein Anriss, kein Brief: Die Liste soll überblicken lassen. ?>
    <p class="muted small"><?= e(mb_substr(preg_replace('~\s+~u', ' ', (string) $msg['body_text']) ?? '', 0, 180)) ?>…</p>
    <a class="btn btn-tiny" href="/intern/post/<?= (int) $msg['id'] ?>"><?= e(t('post_open')) ?></a>
  </div>
<?php endforeach; ?>

<?php require BASE_DIR . '/app/views/_footer.php'; ?>
