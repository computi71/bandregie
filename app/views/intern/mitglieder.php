<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('mem_title')) ?></h1>

<?php if (is_demo()): ?>
  <p class="card muted small">🔒 <?= e(t('demo_locked_hint')) ?></p>
<?php endif; ?>

<?php if ($user['role'] === 'admin' && !is_demo()): ?>
<details class="card collapsible">
  <summary>➕ <?= e(t('mem_new')) ?></summary>
  <form method="post" action="/intern/mitglieder" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('mem_first_name')) ?><input name="first_name" required></label>
    <label><?= e(t('mem_last_name')) ?><input name="last_name"></label>
    <label><?= e(t('email')) ?><input type="email" name="email" required></label>
    <label><?= e(t('instrument')) ?><input name="instrument" placeholder="<?= e(t('mem_instrument_ph')) ?>"></label>
    <label><?= e(t('role')) ?>
      <select name="role"><option value="member"><?= e(t('role_member')) ?></option><option value="ersatz"><?= e(t('role_ersatz')) ?></option><option value="admin"><?= e(t('role_admin')) ?></option></select>
    </label>
    <p class="muted small span2"><?= e(t('mem_invite_hint')) ?></p>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>
<?php endif; ?>

<?php foreach ($members as $m): ?>
  <section class="card">
    <div class="event-head">
      <?php if ($m['avatar_file']): ?>
        <img class="avatar" src="/uploads/<?= e($m['avatar_file']) ?>" alt="<?= e($m['name']) ?>">
      <?php else: ?>
        <span class="avatar avatar-placeholder"><?= e(mb_substr($m['name'], 0, 1)) ?></span>
      <?php endif; ?>
      <strong><?= e($m['stage_name'] ?: $m['name']) ?></strong>
      <?php if ($m['stage_name']): ?><span class="muted">(<?= e($m['name']) ?>)</span><?php endif; ?>
      <?= (int) $m['id'] === (int) $user['id'] ? '<span class="badge">' . e(t('mem_you')) . '</span>' : '' ?>
      <?php if ($m['instrument']): ?><span class="muted">🎸 <?= e($m['instrument']) ?></span><?php endif; ?>
      <?php if (!empty($m['mobile'])): ?><span class="muted">📱 <?= e($m['mobile']) ?></span><?php endif; ?>
      <?php if (!empty($m['phone'])): ?><span class="muted">📞 <?= e($m['phone']) ?></span><?php endif; ?>
      <?php if (!empty($m['substitute_for_name'])): ?><span class="badge">🔁 <?= e(t('mem_substitute_for')) ?> <?= e($m['substitute_for_name']) ?></span><?php endif; ?>
      <span class="badge <?= $m['role'] === 'admin' ? 'public' : '' ?>"><?= e(t('role_' . ($m['role'] === 'ersatz' ? 'ersatz' : ($m['role'] === 'admin' ? 'admin' : 'member')))) ?></span>
      <?php if (perm_allows($m, 'kasse', 'write')): ?><span class="badge">💰 <?= e(t('fin_badge')) ?></span><?php endif; ?>
      <span class="row-buttons">
        <?php // Von der Person zu ihren Bildern (#203): Der Link zeigt die
              // Galerie gefiltert auf dieses Mitglied — nur wenn es Bilder gibt,
              // ein Knopf ins Leere verspricht sonst etwas, das nicht da ist. ?>
        <?php $mFotos = (int) row('SELECT COUNT(*) n FROM photo_people WHERE user_id = ?', [(int) $m['id']])['n']; ?>
        <?php if ($mFotos > 0): ?><a class="btn btn-tiny btn-ghost" href="/intern/fotos?person=<?= (int) $m['id'] ?>">📷 <?= e(t('photo_person_photos')) ?> (<?= $mFotos ?>)</a><?php endif; ?>
        <?php if ((int) $m['id'] === (int) $user['id']): ?><a class="btn btn-tiny" href="/intern/profil">✏️ <?= e(t('mem_my_profile')) ?></a><?php endif; ?>
        <?php if (((int) $m['id'] === (int) $user['id'] || $user['role'] === 'admin') && !is_demo()): ?>
          <details class="inline-details">
            <summary class="btn btn-tiny">🔑 <?= e(t('mem_password')) ?></summary>
            <form method="post" action="/intern/mitglieder/<?= $m['id'] ?>/passwort" class="comment-form"><?= csrf_field() ?>
              <input type="password" name="password" minlength="8" required placeholder="<?= e(t('mem_new_pw')) ?>"
                     data-strength data-labels="<?= e(t('pw_weak')) ?>|<?= e(t('pw_medium')) ?>|<?= e(t('pw_strong')) ?>|<?= e(t('pw_very_strong')) ?>">
              <button class="btn btn-tiny"><?= e(t('mem_set')) ?></button>
            </form>
          </details>
        <?php endif; ?>
        <?php // Zweiten Faktor zurücksetzen (#169): der einzige Weg zurück,
              // wenn Handy und Rückwege zugleich verloren sind. Nur sichtbar,
              // wenn dieses Mitglied wirklich einen hat — ein Knopf, der
              // nichts tut, sieht aus wie ein Knopf, der nicht funktioniert. ?>
        <?php if ($user['role'] === 'admin' && totp_active_for($m) && !is_demo()): ?>
          <form class="inline" method="post" action="/intern/mitglieder/<?= $m['id'] ?>/zwei-faktor" data-confirm="<?= e(t('totp_reset_member')) ?>"><?= csrf_field() ?><button class="btn btn-tiny">🔑✖</button></form>
        <?php endif; ?>
        <?php // Zugangsdaten erneut schicken (#273): für den Fall, dass die
              // Willkommensmail nie ankam. Nicht für das eigene Konto — man
              // würde sich damit selbst das Passwort nehmen. ?>
        <?php if ($user['role'] === 'admin' && (int) $m['id'] !== (int) $user['id'] && !is_demo()): ?>
          <form class="inline" method="post" action="/intern/mitglieder/<?= $m['id'] ?>/zugangsdaten" data-confirm="<?= e(t('mem_send_access_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-tiny">✉ <?= e(t('mem_send_access')) ?></button></form>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin' && (int) $m['id'] !== (int) $user['id'] && !is_demo()): ?>
          <form class="inline" method="post" action="/intern/mitglieder/<?= $m['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
        <?php endif; ?>
      </span>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
      <details class="subsection">
        <summary>✏️ <?= e(t('mem_edit_admin')) ?></summary>
        <?php $mFull = row('SELECT * FROM users WHERE id = ?', [$m['id']]); ?>
        <form method="post" action="/intern/mitglieder/<?= $m['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
          <label><?= e(t('stage_name')) ?><input name="stage_name" value="<?= e($mFull['stage_name']) ?>"></label>
          <p class="muted small span2"><?= e(t('mem_name_hint')) ?></p>
          <label><?= e(t('mem_first_name')) ?><input name="first_name" value="<?= e($mFull['first_name'] ?? '') ?>" required></label>
          <label><?= e(t('mem_last_name')) ?><input name="last_name" value="<?= e($mFull['last_name'] ?? '') ?>"></label>
          <label><?= e(t('phone')) ?><input name="phone" value="<?= e($mFull['phone'] ?? '') ?>"></label>
          <label><?= e(t('mem_mobile')) ?><input name="mobile" value="<?= e($mFull['mobile'] ?? '') ?>"></label>
          <label><?= e(t('instrument')) ?>
            <input name="instrument" value="<?= e($mFull['instrument']) ?>" list="instrument-list">
            <span class="muted small"><?= e($instruments ? t('mem_instrument_pick') : t('mem_instrument_free')) ?></span>
          </label>
          <?php // Figur und Bühnenzugehörigkeit gehören zum Bühnenplan und stehen
                // deshalb hier bei der Verwaltung — im eigenen Profil steht
                // dasselbe, aber dort setzt es nur, wer sich selbst einloggt (#187). ?>
          <label><?= e(t('stage_figure')) ?>
            <select name="stage_figure">
              <?php foreach (STAGE_FIGURES as $sfKey => $sfSym): ?>
                <option value="<?= e($sfKey) ?>" <?= (string) ($mFull['stage_figure'] ?? '') === (string) $sfKey ? 'selected' : '' ?>>
                  <?= $sfSym ?> <?= e(t('stage_figure_' . ($sfKey === '' ? 'auto' : $sfKey))) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="muted small"><?= e(t('stage_figure_hint')) ?></span>
          </label>
          <label class="checkbox"><input type="checkbox" name="on_stage" value="1" <?= (int) ($mFull['on_stage'] ?? 1) === 1 ? 'checked' : '' ?>> 🎭 <?= e(t('prof_on_stage')) ?></label>
          <label><?= e(t('mem_substitute_for')) ?>
            <select name="substitute_for">
              <option value=""><?= e(t('mem_substitute_none')) ?></option>
              <?php foreach ($members as $other): ?>
                <?php if ((int) $other['id'] === (int) $m['id']) continue; ?>
                <option value="<?= $other['id'] ?>" <?= (int) ($mFull['substitute_for'] ?? 0) === (int) $other['id'] ? 'selected' : '' ?>><?= e($other['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><?= e(t('mem_substitute_rank')) ?>
            <input type="number" name="substitute_rank" min="0" max="99" value="<?= (int) ($mFull['substitute_rank'] ?? 0) ?>">
            <span class="muted small"><?= e(t('mem_substitute_rank_hint')) ?></span>
          </label>
          <?php // Nur wo die Kasse überhaupt geführt wird, ist die Frage
                // sinnvoll — sonst erklärt der Schalter etwas, das nirgends
                // vorkommt. Aushilfen sind ohnehin nie beteiligt. ?>
          <?php if (perm_allows($user, 'kasse') && ($mFull['role'] ?? '') !== 'ersatz'): ?>
            <label class="checkbox span2">
              <input type="checkbox" name="profit_share" value="1" <?= (int) ($mFull['profit_share'] ?? 1) === 1 ? 'checked' : '' ?>>
              <?= e(t('mem_profit_share')) ?>
            </label>
            <p class="muted small span2"><?= e(t('mem_profit_share_hint')) ?></p>
          <?php endif; ?>
          <?php // In der Demo bleiben Adresse und Rolle stehen — die Route
                // übernimmt sie ohnehin nicht, und ein Feld, das sich tippen
                // lässt und dann nichts tut, ist schlimmer als ein gesperrtes. ?>
          <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($mFull['email']) ?>" required <?= is_demo() ? 'readonly' : '' ?>></label>
          <label><?= e(t('role')) ?>
            <select name="role" <?= (int) $m['id'] === (int) $user['id']
              ? 'disabled title="' . e(t('mem_own_role')) . '"'
              : (is_demo() ? 'disabled title="' . e(t('demo_locked_hint')) . '"' : '') ?>>
              <option value="member" <?= $mFull['role'] === 'member' ? 'selected' : '' ?>><?= e(t('role_member')) ?></option>
              <option value="admin" <?= $mFull['role'] === 'admin' ? 'selected' : '' ?>><?= e(t('role_admin')) ?></option>
              <option value="ersatz" <?= $mFull['role'] === 'ersatz' ? 'selected' : '' ?>><?= e(t('role_ersatz')) ?></option>
            </select>
          </label>
          <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
        </form>
      </details>
      <?php
        // Bei einem Admin steht nur zur Wahl, was auch ein Admin nicht von
        // selbst hat — die Kasse. Sonst wäre sie an niemanden zu vergeben,
        // der die Band verwaltet.
        $permIsAdmin = $mFull['role'] === 'admin';
        $permModuleList = $permIsAdmin ? PERM_EXPLICIT_MODULES : array_keys(PERM_MODULES);
      ?>
        <details class="subsection">
          <summary>🔑 <?= e(t('perm_title')) ?></summary>
          <p class="muted small"><?= e($permIsAdmin ? t('perm_admin_all') : t('perm_intro')) ?></p>
          <?php $mine = $perms[(int) $m['id']] ?? []; ?>
          <form method="post" action="/intern/rechte/<?= $m['id'] ?>"><?= csrf_field() ?>
            <ul class="perm-list">
              <?php foreach ($permModuleList as $mod): ?>
                <?php $canRead = (int) ($mine[$mod]['can_read'] ?? 0); $canWrite = (int) ($mine[$mod]['can_write'] ?? 0); ?>
                <li>
                  <span class="perm-name"><?= e(t('inav_' . $mod)) ?></span>
                  <label class="checkbox"><input type="checkbox" name="perm[<?= $mod ?>][read]" value="1" <?= $canRead ? 'checked' : '' ?>> <?= e(t('perm_read')) ?></label>
                  <label class="checkbox"><input type="checkbox" name="perm[<?= $mod ?>][write]" value="1" <?= $canWrite ? 'checked' : '' ?>> <?= e(t('perm_write')) ?></label>
                </li>
              <?php endforeach; ?>
            </ul>
            <div class="row-buttons">
              <button class="btn btn-primary"><?= e(t('save')) ?></button>
              <?php if (!$permIsAdmin): ?>
                <button class="btn btn-ghost" name="template" value="member"><?= e(t('perm_template')) ?>: <?= e(t('perm_tpl_member')) ?></button>
                <button class="btn btn-ghost" name="template" value="ersatz"><?= e(t('perm_template')) ?>: <?= e(t('perm_tpl_ersatz')) ?></button>
              <?php endif; ?>
            </div>
            <?php if (!$permIsAdmin): ?><p class="muted small"><?= e(t('perm_tpl_hint')) ?></p><?php endif; ?>
          </form>
        </details>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<datalist id="instrument-list">
  <?php foreach ($instruments as $i): ?><option value="<?= e($i) ?>"><?php endforeach; ?>
</datalist>
<script src="<?= e(asset('/assets/strength.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
