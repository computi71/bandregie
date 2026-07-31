<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('mem_my_profile')) ?></h1>

<div class="card">
  <div class="row-buttons" style="margin-bottom:0.8rem">
    <?php if ($profile['avatar_file']): ?>
      <img class="avatar avatar-big" src="/uploads/<?= e($profile['avatar_file']) ?>" alt="<?= e($profile['name']) ?>">
      <form class="inline" method="post" action="/intern/profil/avatar/delete"><?= csrf_field() ?><button class="btn btn-tiny btn-danger"><?= e(t('prof_avatar_remove')) ?></button></form>
    <?php else: ?>
      <span class="avatar avatar-big avatar-placeholder"><?= e(mb_substr($profile['name'], 0, 1)) ?></span>
      <span class="muted small"><?= e(t('prof_no_avatar')) ?></span>
    <?php endif; ?>
  </div>
  <form method="post" action="/intern/profil" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('stage_name')) ?><input name="stage_name" value="<?= e($profile['stage_name']) ?>" placeholder="<?= e(t('prof_stage_name_ph')) ?>"></label>
    <p class="muted small span2"><?= e(t('mem_name_hint')) ?></p>
    <label><?= e(t('mem_first_name')) ?><input name="first_name" value="<?= e($profile['first_name'] ?? '') ?>" required></label>
    <label><?= e(t('mem_last_name')) ?><input name="last_name" value="<?= e($profile['last_name'] ?? '') ?>"></label>
    <label><?= e(t('phone')) ?><input name="phone" value="<?= e($profile['phone'] ?? '') ?>"></label>
    <label><?= e(t('mem_mobile')) ?><input name="mobile" value="<?= e($profile['mobile'] ?? '') ?>"></label>
    <label><?= e(t('instrument')) ?><input name="instrument" value="<?= e($profile['instrument']) ?>" placeholder="<?= e(t('mem_instrument_ph')) ?>"></label>
    <?php // Mit der Adresse meldet man sich an; in der Demo gilt sie für alle
          // Besucher und bleibt deshalb stehen. Die Route übernimmt sie ohnehin
          // nicht — hier steht nur, warum das Feld nicht reagiert. ?>
    <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($profile['email']) ?>" required <?= is_demo() ? 'readonly' : '' ?>>
      <?php if (is_demo()): ?><span class="muted small">🔒 <?= e(t('demo_locked_hint')) ?></span><?php endif; ?>
    </label>
    <label><?= e(t('prof_lang')) ?>
      <select name="pref_lang">
        <?php foreach (LANGS as $code => $name): ?>
          <option value="<?= $code ?>" <?= ($profile['pref_lang'] ?? 'de') === $code ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="span2"><?= e(t('prof_avatar_lbl')) ?><input type="file" name="avatar" accept="image/*"></label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
  <p class="muted small"><?= e(t('prof_pw_hint')) ?> <a href="/intern/mitglieder"><?= e(t('mem_title')) ?> →</a></p>
</div>
<?php // Offline: je Mitglied, denn das Telefon ist persönlich. Wer nur singt,
      // braucht die Patchliste nicht — und Noten sind das Schwergewicht. ?>
<details class="card acc" name="profilacc">
  <summary>📴 <?= e(t('off_areas')) ?></summary>
  <p class="muted small"><?= e(t('off_areas_hint')) ?></p>
  <form method="post" action="/intern/offline/bereiche"><?= csrf_field() ?>
    <?php $offMein = offline_scope($profile); ?>
    <fieldset class="gear-picker">
      <?php foreach (OFFLINE_AREAS as $offArea): ?>
        <label class="checkbox">
          <input type="checkbox" name="areas[]" value="<?= e($offArea) ?>"
                 <?= in_array($offArea, $offMein, true) ? 'checked' : '' ?>>
          <?= e(t('off_area_' . $offArea)) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <p class="muted small" data-offlineuse></p>
    <button class="btn btn-primary btn-small"><?= e(t('save')) ?></button>
  </form>
  <p class="muted small"><?= e(t('off_areas_when')) ?></p>
</details>

<?php // Anmeldung über Anbieter (#97): nur zeigen, wenn welche konfiguriert
      // sind — sonst gäbe es hier Knöpfe ins Leere. Verknüpfen führt über den
      // Anbieter (mit ?link=1 im signierten state), Trennen ist ein POST. ?>
<?php if ($oauthProviders = oauth_enabled()): ?>
<details class="card acc" name="profilacc">
  <summary>🔑 <?= e(t('prof_identities')) ?></summary>
  <p class="muted small"><?= e(t('prof_identities_hint')) ?></p>
  <?php foreach ($oauthProviders as $key => $p): ?>
    <div class="row-buttons" style="margin-bottom:0.4rem">
      <strong><?= e($p['name']) ?></strong>
      <?php if (isset($identities[$key])): ?>
        <span class="muted small"><?= e(str_replace('%1', $identities[$key], t('prof_identity_as'))) ?></span>
        <form class="inline" method="post" action="/intern/profil/identity/<?= e($key) ?>/delete"><?= csrf_field() ?>
          <button class="btn btn-tiny btn-ghost"><?= e(t('prof_identity_unlink')) ?></button>
        </form>
      <?php else: ?>
        <a class="btn btn-tiny" href="/auth/<?= e($key) ?>?link=1"><?= e(t('prof_identity_link')) ?></a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</details>
<?php endif; ?>

<?php // Push-Mitteilungen (#24): Themen gelten kontoweit, das Abo je Gerät.
      // push.js blendet den Geräte-Teil ohne Browser-Unterstützung aus —
      // stiller Rückfall statt Knopf ins Leere. ?>
<?php if (push_available()): ?>
<details class="card acc" name="profilacc" data-push
         data-push-key="<?= e(push_public_key()) ?>" data-push-token="<?= e(csrf_token()) ?>">
  <summary>🔔 <?= e(t('prof_push')) ?></summary>
  <p class="muted small"><?= e(t('prof_push_hint')) ?></p>
  <form method="post" action="/intern/profil/push-topics"><?= csrf_field() ?>
    <?php $meineThemen = array_filter(explode(',', (string) ($profile['push_topics'] ?? ''))); ?>
    <fieldset class="gear-picker">
      <?php foreach (PUSH_TOPICS as $pushTopic): ?>
        <label class="checkbox">
          <input type="checkbox" name="topics[]" value="<?= e($pushTopic) ?>"
                 <?= in_array($pushTopic, $meineThemen, true) ? 'checked' : '' ?>>
          <?= e(t('push_topic_' . $pushTopic)) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <button class="btn btn-primary btn-small"><?= e(t('save')) ?></button>
  </form>
  <div class="row-buttons" style="margin-top:0.6rem">
    <button type="button" class="btn btn-small" data-push-enable hidden><?= e(t('prof_push_enable')) ?></button>
    <button type="button" class="btn btn-ghost btn-small" data-push-disable hidden><?= e(t('prof_push_disable')) ?></button>
  </div>
  <p class="muted small" data-push-ios hidden>📲 <?= e(t('prof_push_ios')) ?></p>
  <p class="warn" data-push-denied hidden><?= e(t('prof_push_denied')) ?></p>
</details>
<?php endif; ?>

<?php require BASE_DIR . '/app/views/_footer.php'; ?>
