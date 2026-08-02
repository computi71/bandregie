<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🔑 <?= e(t('totp_title')) ?></h1>

<?php // Frisch erzeugte Rückwege stehen ganz oben und vor allem anderen: Sie
      // werden genau einmal gezeigt, und wer sie überliest, hat sie verloren. ?>
<?php if ($codes): ?>
  <div class="card warn">
    <h2>📄 <?= e(t('totp_codes_title')) ?></h2>
    <p><?= e(t('totp_codes_hint')) ?></p>
    <ul class="task-list" style="font-family:monospace;font-size:1.1rem">
      <?php foreach ($codes as $code): ?><li><?= e($code) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($aktiv): ?>
  <div class="card">
    <p><strong>✅ <?= e(str_replace('%1', fmt_date($seit), t('totp_active_since'))) ?></strong></p>
    <p class="muted small"><?= e(t('totp_passkey_note')) ?></p>
    <p class="<?= $uebrig > 0 ? 'muted' : 'warn' ?> small">
      <?= e($uebrig > 0 ? str_replace('%1', (string) $uebrig, t('totp_codes_left')) : t('totp_codes_none_left')) ?>
    </p>

    <?php // Neue Rückwege nur gegen einen aktuellen Code: Sonst wäre eine
          // offen stehengelassene Sitzung der Weg, sich selbst zehn frische
          // Schlüssel auszustellen. ?>
    <form method="post" action="/intern/zwei-faktor" class="stack"><?= csrf_field() ?>
      <input type="hidden" name="tat" value="codes">
      <p class="muted small"><?= e(t('totp_codes_new_hint')) ?></p>
      <label><?= e(t('totp_code_label')) ?>
        <input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="10" spellcheck="false">
      </label>
      <button class="btn btn-small"><?= e(t('totp_codes_new')) ?></button>
    </form>

    <?php if (!$erzwungen): ?>
      <form method="post" action="/intern/zwei-faktor" class="inline" style="margin-top:0.8rem"
            data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="tat" value="delete">
        <button class="btn btn-tiny btn-danger"><?= e(t('totp_remove')) ?></button>
      </form>
    <?php else: ?>
      <p class="muted small">🔒 <?= e(t('totp_cannot_remove')) ?></p>
    <?php endif; ?>
    <p class="muted small"><a href="/intern/profil"><?= e(t('mem_my_profile')) ?> →</a></p>
  </div>

<?php else: ?>
  <?php if ($erzwungen): ?>
    <p class="warn"><?= e(t('totp_forced_intro')) ?></p>
    <?php // Der Rückweg für den, der es eingeschaltet hat: Ohne zweiten Faktor
          // kommt auch ein Admin nur noch hierher, also muss der Schalter hier
          // erreichbar sein und nicht in den Einstellungen, die zu sind. ?>
    <?php if (($user['role'] ?? '') === 'admin'): ?>
      <form method="post" action="/intern/einstellungen/zwei-faktor" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="totp_mode" value="optional">
        <button class="btn btn-tiny btn-ghost"><?= e(t('totp_forced_undo')) ?></button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
  <div class="card">
    <p class="muted"><?= e(t('totp_hint')) ?></p>
    <p class="muted small"><?= e(t('totp_setup_app')) ?></p>
    <p class="muted small"><?= e(t('totp_setup_scan')) ?></p>

    <?php // Auf dem Handy kann der QR-Code nicht helfen: Die App liegt auf
          // demselben Bildschirm, der ihn anzeigt. Die otpauth-Adresse als
          // Verweis löst das — ein Tippen öffnet die App, und sie legt das
          // Konto selbst an. Auf dem Rechner passiert nichts, wenn dort keine
          // Authenticator-App registriert ist; deshalb steht das im Text.
          // Der Verweis steht zuerst, weil die Einrichtung am Handy der Fall
          // ist, in dem der Weg darunter gar nicht funktioniert. ?>
    <p class="muted small"><?= e(t('totp_setup_here')) ?></p>
    <p><a class="btn btn-primary" href="<?= e($uri) ?>"><?= e(t('totp_setup_open_app')) ?></a></p>

    <p class="muted small"><?= e(t('totp_setup_other')) ?></p>
    <?php // Der QR-Code entsteht hier im Haus (app/qr.php) und nicht bei einem
          // Dienst im Netz: Er trägt das Geheimnis im Klartext, und fremd
          // gerendert wäre der zweite Faktor verschickt, bevor er wirkt. ?>
    <div style="margin:1rem 0"><?= qr_svg($uri, 5) ?></div>

    <details>
      <summary><?= e(t('totp_manual_title')) ?></summary>
      <p class="muted small"><?= e(t('totp_manual_hint')) ?></p>
      <p><code style="font-size:1.1rem;letter-spacing:0.1em"><?= e(trim(chunk_split($geheim, 4, ' '))) ?></code></p>
    </details>

    <p class="muted small" style="margin-top:0.8rem"><?= e(t('totp_setup_confirm')) ?></p>
    <form method="post" action="/intern/zwei-faktor" class="stack"><?= csrf_field() ?>
      <input type="hidden" name="tat" value="confirm">
      <label><?= e(t('totp_code_label')) ?>
        <input name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="10" spellcheck="false">
      </label>
      <button class="btn btn-primary"><?= e(t('totp_confirm')) ?></button>
    </form>
    <p class="muted small"><?= e(t('totp_passkey_note')) ?></p>
  </div>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
