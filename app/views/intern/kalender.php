<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>📅 <?= e(t('cal_title')) ?></h1>
<p class="muted"><?= e(t('cal_intro')) ?></p>

<div class="card">
  <h2><?= e(t('cal_your_link')) ?></h2>
  <p><a href="<?= e($webcalUrl) ?>" class="btn btn-primary">📲 <?= e(t('cal_open_app')) ?></a>
  <span class="muted small">— <?= e(t('cal_open_hint')) ?></span></p>
  <p class="muted small"><?= e(t('cal_copy_manual')) ?></p>
  <p><code id="ical-link"><?= e($icalUrl) ?></code>
  <button class="btn btn-small" data-copy="ical-link" data-copied="<?= e(t('copied')) ?>"><?= e(t('copy')) ?></button></p>
  <p class="warn"><?= e(t('cal_token_warn')) ?></p>
</div>

<div class="card">
  <h2><?= e(t('cal_setup')) ?></h2>

  <details class="subsection" open>
    <summary><strong>📱 iPhone / iPad</strong></summary>
    <ol>
      <li><?= e(t('cal_ios_step1')) ?></li>
      <li><?= e(t('cal_ios_step2')) ?></li>
      <li><?= e(t('cal_ios_step3')) ?></li>
    </ol>
  </details>

  <details class="subsection">
    <summary><strong>🤖 Android / Google</strong></summary>
    <ol>
      <li><?= e(t('cal_and_step1')) ?></li>
      <li><?= e(t('cal_and_step2')) ?></li>
      <li><?= e(t('cal_and_step3')) ?></li>
      <li><?= e(t('cal_and_step4')) ?></li>
    </ol>
  </details>

  <details class="subsection">
    <summary><strong>💻 Outlook</strong></summary>
    <ol>
      <li><?= e(t('cal_out_step1')) ?></li>
      <li><?= e(t('cal_out_step2')) ?></li>
    </ol>
  </details>

  <details class="subsection">
    <summary><strong>🦅 Thunderbird</strong></summary>
    <ol>
      <li><?= e(t('cal_tb_step1')) ?></li>
      <li><?= e(t('cal_tb_step2')) ?></li>
    </ol>
  </details>

  <p class="muted small"><?= e(t('cal_note')) ?></p>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
