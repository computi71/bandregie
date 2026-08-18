<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('venues_title')) ?></h1>

<details class="card collapsible" <?= $venues ? '' : 'open' ?>>
  <summary>➕ <?= e(t('venues_new')) ?></summary>
  <form method="post" action="/intern/orte" class="form-grid"><?= csrf_field() ?>
    <?php // Adressblock in der Reihenfolge, in der eine Adresse geschrieben wird:
          // Name, dann PLZ und Ort in einer Zeile, dann Straße und Hausnummer.
          // Die PLZ ist ein eigenes Feld, weil die Adress-Suche sie als PLZ fragt
          // und nicht als Wort im Fließtext (#249). ?>
    <label class="span2"><?= e(t('name')) ?><input name="name" required placeholder="<?= e(t('venues_name_ph')) ?>"></label>
    <label><?= e(t('postcode')) ?><input name="postcode" inputmode="numeric" maxlength="20"></label>
    <label><?= e(t('city')) ?><input name="city"></label>
    <label class="span2"><?= e(t('address')) ?><textarea name="address" rows="2"></textarea></label>
    <?php $geoLat = $geoLng = ''; require BASE_DIR . '/app/views/intern/_geofield.php'; ?>
    <label><?= e(t('contact_person')) ?><input name="contact_name"></label>
    <label><?= e(t('email')) ?><input type="email" name="contact_email"></label>
    <label><?= e(t('phone')) ?><input name="contact_phone"></label>
    <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2" placeholder="<?= e(t('venues_notes_ph')) ?>"></textarea></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>

<?php foreach ($venues as $v): ?>
  <section class="card">
    <div class="event-head">
      <strong><?= e($v['name']) ?></strong>
      <?php $ortZeile = trim($v['postcode'] . ' ' . $v['city']); ?>
      <?php if ($ortZeile !== ''): ?><span class="muted">📍 <?= e($ortZeile) ?></span><?php endif; ?>
      <?php if ($v['contact_name']): ?><span class="muted">👤 <?= e($v['contact_name']) ?></span><?php endif; ?>
      <?php if ($v['contact_phone']): ?><span class="muted">📞 <?= e($v['contact_phone']) ?></span><?php endif; ?>
      <?php if ($v['contact_email']): ?><a class="muted" href="mailto:<?= e($v['contact_email']) ?>">✉ <?= e($v['contact_email']) ?></a><?php endif; ?>
    </div>
    <?php if ($v['address']): ?><p class="prewrap muted small"><?= e($v['address']) ?></p><?php endif; ?>
    <?php // Navi-Link: am Handy öffnet route.js die native Karten-App (Apple Karten
          // bzw. die eingestellte Android-App), am Desktop den Web-Link. ?>
    <?php $naviDest = venue_dest($v); ?>
    <?php if ($naviDest !== ''): ?><p><a class="btn btn-ghost btn-small navi-link" data-navi="<?= e($naviDest) ?>" href="<?= e(navi_web($naviDest)) ?>" target="_blank" rel="noopener">🧭 <?= e(t('geo_navigate')) ?></a></p><?php endif; ?>
    <?php if ($v['notes']): ?><p class="prewrap muted"><?= e($v['notes']) ?></p><?php endif; ?>
    <?php $venueEvents = $eventsByVenue[$v['id']] ?? []; ?>
    <?php if ($venueEvents): ?>
      <div class="subsection">
        <strong class="muted small"><?= e(t('venues_events_here')) ?></strong>
        <ul class="event-list">
          <?php foreach ($venueEvents as $ev): ?>
            <li>
              <span class="event-date"><?= fmt_date($ev['date']) ?></span>
              <?= $ev['date'] < $today ? '🔒' : '' ?>
              <span class="badge <?= e($ev['type']) ?>"><?= e(event_type_label($ev['type'])) ?></span>
              <?= e($ev['title']) ?>
              <?php if ($ev['setlist_id']): ?>
                <a class="badge link" href="/intern/setlists/<?= $ev['setlist_id'] ?>"><?= e(t('ev_setlist')) ?>: <?= e($ev['setlist_name']) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php $attachFiles = $filesByVenue[$v['id']] ?? []; $attachType = 'venue'; $attachId = $v['id']; require BASE_DIR . '/app/views/_dateien.php'; ?>
    <details class="subsection">
      <summary>✏️ <?= e(t('edit')) ?></summary>
      <form method="post" action="/intern/orte/<?= $v['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
        <label class="span2"><?= e(t('name')) ?><input name="name" value="<?= e($v['name']) ?>" required></label>
        <label><?= e(t('postcode')) ?><input name="postcode" inputmode="numeric" maxlength="20" value="<?= e($v['postcode']) ?>"></label>
        <label><?= e(t('city')) ?><input name="city" value="<?= e($v['city']) ?>"></label>
        <label class="span2"><?= e(t('address')) ?><textarea name="address" rows="2"><?= e($v['address']) ?></textarea></label>
        <?php $geoLat = $v['lat']; $geoLng = $v['lng']; require BASE_DIR . '/app/views/intern/_geofield.php'; ?>
        <label><?= e(t('contact_person')) ?><input name="contact_name" value="<?= e($v['contact_name']) ?>"></label>
        <label><?= e(t('email')) ?><input type="email" name="contact_email" value="<?= e($v['contact_email']) ?>"></label>
        <label><?= e(t('phone')) ?><input name="contact_phone" value="<?= e($v['contact_phone']) ?>"></label>
        <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e($v['notes']) ?></textarea></label>
        <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
      </form>
      <form method="post" action="/intern/orte/<?= $v['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>" class="inline"><?= csrf_field() ?>
        <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
      </form>
    </details>
  </section>
<?php endforeach; ?>
<?php if (!$venues): ?><p class="muted center"><?= e(t('venues_none')) ?></p><?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
