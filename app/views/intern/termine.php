<?php
$memberNames = array_column($members, 'name', 'id');
require BASE_DIR . '/app/views/_header.php';
?>
<div class="page-head">
  <h1><?= e(t('inav_termine')) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="/intern/termine<?= $showPast ? '' : '?alle=1' ?>"><?= e($showPast ? t('ev_only_upcoming') : t('ev_also_past')) ?></a>
    <a class="btn btn-ghost" href="/intern/kalender">📅 <?= e(t('ev_cal_abo')) ?></a>
    <a class="btn btn-ghost" href="/intern/termine/export">⬇ <?= e(t('ev_export')) ?></a>
  </div>
</div>

<details class="card collapsible" <?= $events ? '' : 'open' ?>>
  <summary>➕ <?= e(t('ev_new')) ?></summary>
  <form method="post" action="/intern/termine" class="form-grid" data-eventfields='<?= e(json_encode(EVENT_TYPE_FIELDS)) ?>'><?= csrf_field() ?>
    <label><?= e(t('ev_type')) ?>
      <select name="type"><?php foreach (EVENT_TYPES as $val => $lbl): ?><option value="<?= $val ?>"><?= e(event_type_label($val)) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('name')) ?><input name="title" required placeholder="<?= e(t('ev_name_ph')) ?>"></label>
    <label><?= e(t('date')) ?><input type="date" name="date" required></label>
    <label><?= e(t('status')) ?>
      <select name="status"><?php foreach (EVENT_STATUS as $val => $lbl): ?><option value="<?= $val ?>"><?= e(event_status_label($val)) ?></option><?php endforeach; ?></select>
    </label>
    <label data-eventfield="times"><?= e(t('ev_meet')) ?><input type="time" name="time_meet"></label>
    <label data-eventfield="times"><?= e(t('ev_start')) ?><input type="time" name="time"></label>
    <label data-eventfield="times"><?= e(t('ev_end')) ?><input type="time" name="time_end"></label>
    <label data-eventfield="venue"><?= e(t('ev_venue')) ?>
      <select name="venue_id"><option value="">–</option><?php foreach ($venues as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['name']) ?><?= $v['city'] ? ' (' . e($v['city']) . ')' : '' ?></option><?php endforeach; ?></select>
    </label>
    <label data-eventfield="venue"><?= e(t('ev_location_free')) ?><input name="location" placeholder="<?= e(t('ev_location_free_ph')) ?>"></label>
    <label data-eventfield="setlist"><?= e(t('ev_setlist')) ?>
      <select name="setlist_id"><option value="">–</option><?php foreach ($setlists as $sl): ?><option value="<?= $sl['id'] ?>"><?= e($sl['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('ev_responsible')) ?>
      <select name="responsible_id"><option value="">–</option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
    </label>
    <?php $prodEv = null; $gearSel = []; require BASE_DIR . '/app/views/intern/_event_production.php'; ?>
    <label data-eventfield="fee"><?= e(t('ev_fee')) ?><input name="fee" placeholder="z. B. 800 €"></label>
    <label data-eventfield="fee"><?= e(t('ev_invoice')) ?><input name="invoice_no"></label>
    <label class="span2"><?= e(t('ev_notes')) ?><textarea name="notes" rows="2" placeholder="<?= e(t('ev_notes_ph')) ?>"></textarea></label>
    <fieldset class="span2 pubfields" data-eventfield="public">
      <legend><?= e(t('ev_public_display')) ?></legend>
      <label class="checkbox"><input type="checkbox" name="is_public" value="1"> <?= e(t('ev_show_on_site')) ?></label>
      <label><?= e(t('ev_public_title')) ?><input name="public_title" placeholder="<?= e(t('ev_public_title_ph')) ?>"></label>
      <label><?= e(t('ev_public_link')) ?><input name="public_link" placeholder="https://..."></label>
      <label><?= e(t('ev_public_info')) ?><input name="public_info" placeholder="<?= e(t('ev_public_info_ph')) ?>"></label>
    </fieldset>
    <button class="btn btn-primary span2"><?= e(t('dash_create_event')) ?></button>
  </form>
</details>

<?php foreach ($events as $ev): ?>
  <?php $venue = $ev['venue_id'] && isset($venueMap[$ev['venue_id']]) ? $venueMap[$ev['venue_id']] : null; ?>
  <section class="card event-card <?= $ev['status'] === 'abgesagt' ? 'muted' : '' ?>">
    <div class="event-head">
      <span class="badge <?= e($ev['type']) ?>"><?= e(event_type_label($ev['type'])) ?></span>
      <span class="badge ev-<?= e($ev['status']) ?>"><?= e(event_status_label($ev['status'])) ?></span>
      <span class="event-date"><?= fmt_date($ev['date']) ?><?= $ev['time'] ? ' · ' . e($ev['time']) . ' ' . e(t('events_oclock')) : '' ?></span>
      <strong><?= e($ev['title']) ?></strong>
      <?php if ($venue): ?><span class="muted">📍 <?= e($venue['name']) ?><?= $venue['city'] ? ', ' . e($venue['city']) : '' ?></span>
      <?php elseif ($ev['location']): ?><span class="muted">📍 <?= e($ev['location']) ?></span><?php endif; ?>
      <?php if ($ev['is_public']): ?><span class="badge public"><?= e(t('ev_public_badge')) ?></span><?php endif; ?>
      <?php if ($ev['setlist_id']): ?><a class="badge link" href="/intern/setlists/<?= $ev['setlist_id'] ?>"><?= e(t('ev_setlist')) ?></a><?php endif; ?>
    </div>
    <p class="muted small">
      <?php if ($ev['time_meet']): ?><?= e(t('ev_meet')) ?> <?= e($ev['time_meet']) ?><?php endif; ?>
      <?php if ($ev['time']): ?> · <?= e(t('ev_start')) ?> <?= e($ev['time']) ?><?php endif; ?>
      <?php if ($ev['time_end']): ?> · <?= e(t('ev_end')) ?> <?= e($ev['time_end']) ?><?php endif; ?>
      <?php if ($ev['responsible_id'] && isset($memberNames[$ev['responsible_id']])): ?> · <?= e(t('ev_responsible')) ?>: <?= e($memberNames[$ev['responsible_id']]) ?><?php endif; ?>
      <?php if ($ev['fee']): ?> · <?= e(t('ev_fee')) ?>: <?= e($ev['fee']) ?><?php endif; ?>
      <?php if ($ev['invoice_no']): ?> · <?= e(t('ev_invoice')) ?>: <?= e($ev['invoice_no']) ?><?php endif; ?>
      <?php if (!empty($ev['pa_source'])): ?> · <?= e(t('prod_pa')) ?>: <?= e(production_label($ev['pa_source'])) ?><?php endif; ?>
      <?php if (!empty($ev['light_source'])): ?> · <?= e(t('prod_light')) ?>: <?= e(production_label($ev['light_source'])) ?><?php endif; ?>
    </p>
    <?php $gear = $gearByEvent[$ev['id']] ?? []; ?>
    <?php if ($gear): ?>
      <p class="muted small">🎒 <?= e(t('ev_gear')) ?>: <?= e(implode(', ', array_column($gear, 'name'))) ?></p>
    <?php endif; ?>
    <?php if (!empty($gearConflicts[$ev['id']])): ?>
      <p class="warn">⚠ <?= e(implode(', ', $gearConflicts[$ev['id']])) ?> — <?= e(t('ev_gear_conflict')) ?></p>
    <?php endif; ?>
    <?php if (!empty($absentByEvent[$ev['id']])): ?>
      <p class="warn">⚠ <?= e(t('ev_absent_warn')) ?> <?= e(implode(', ', $absentByEvent[$ev['id']])) ?></p>
    <?php endif; ?>
    <?php if ($ev['notes']): ?><p class="prewrap muted"><?= e($ev['notes']) ?></p><?php endif; ?>

    <form class="inline attendance" action="/intern/termine/<?= $ev['id'] ?>/zusage" method="post"><?= csrf_field() ?>
      <button name="status" value="yes" class="chip <?= ($mine[$ev['id']] ?? '') === 'yes' ? 'chip-yes' : '' ?>"><?= e(t('att_yes')) ?></button>
      <button name="status" value="maybe" class="chip <?= ($mine[$ev['id']] ?? '') === 'maybe' ? 'chip-maybe' : '' ?>"><?= e(t('att_maybe')) ?></button>
      <button name="status" value="no" class="chip <?= ($mine[$ev['id']] ?? '') === 'no' ? 'chip-no' : '' ?>"><?= e(t('att_no')) ?></button>
    </form>
    <?php $att = $attendance[$ev['id']] ?? []; ?>
    <?php if ($att): ?>
      <p class="attendance-summary">
        <?php $label = ['yes' => '✔', 'maybe' => '?', 'no' => '✘']; ?>
        <?php foreach ($att as $a): ?><span class="att att-<?= e($a['status']) ?>"><?= $label[$a['status']] ?> <?= e($a['name']) ?></span><?php endforeach; ?>
      </p>
      <?php
        // Wer abgesagt hat, für den lassen sich seine Ersatzleute anfragen —
        // in ihrer Reihenfolge und mit dem, was sie schon mitgespielt haben.
        $asked = array_column($subRequests[$ev['id']] ?? [], 'user_id');
        $mayAsk = perm_allows($user, 'termine', 'write') && $ev['date'] >= date('Y-m-d');
      ?>
      <?php foreach ($att as $a): ?>
        <?php if ($a['status'] !== 'no') continue; ?>
        <?php $subs = substitutes_for((int) $a['user_id']); ?>
        <?php if (!$subs) continue; ?>
        <p class="warn small">
          🔁 <?= e(t('ev_sub_for')) ?> <strong><?= e($a['name']) ?></strong>
          <?php foreach ($subs as $sub): ?>
            <span class="sub-option">
              <?= e($sub['name']) ?>
              <span class="muted"><?= (int) $sub['proben'] ?>&nbsp;<?= e(t('ev_sub_rehearsals')) ?> · <?= (int) $sub['gigs'] ?>&nbsp;<?= e(t('ev_sub_gigs')) ?></span>
              <?php if (in_array($sub['id'], $asked)): ?>
                <span class="badge"><?= e(t('ev_sub_asked')) ?></span>
              <?php elseif ($mayAsk): ?>
                <form class="inline" method="post" action="/intern/termine/<?= $ev['id'] ?>/ersatz"><?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= $sub['id'] ?>">
                  <button class="btn btn-tiny"><?= e(t('ev_sub_ask')) ?></button>
                </form>
              <?php endif; ?>
            </span>
          <?php endforeach; ?>
        </p>
      <?php endforeach; ?>
      <?php if (!empty($subRequests[$ev['id']])): ?>
        <p class="muted small">
          🔁 <?= e(t('ev_sub_requested')) ?>
          <?php foreach ($subRequests[$ev['id']] as $req): ?>
            <span class="sub-option">
              <strong><?= e($req['name']) ?></strong>
              <?php if ($req['for_name']): ?><span class="muted"><?= e(t('mem_substitute_for')) ?> <?= e($req['for_name']) ?></span><?php endif; ?>
              <?php $lbl = ['yes' => t('att_yes'), 'no' => t('att_no'), 'maybe' => t('att_maybe')][$req['answer'] ?? ''] ?? t('ev_sub_open'); ?>
              <span class="badge <?= ($req['answer'] ?? '') === 'yes' ? 'public' : '' ?>"><?= e($lbl) ?></span>
              <?php if ($mayAsk): ?>
                <form class="inline" method="post" action="/intern/termine/<?= $ev['id'] ?>/ersatz/<?= $req['user_id'] ?>/delete"><?= csrf_field() ?>
                  <button class="btn btn-tiny btn-danger" title="<?= e(t('ev_sub_withdraw')) ?>">✕</button>
                </form>
              <?php endif; ?>
            </span>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    <?php endif; ?>

    <?php $attachFiles = $filesByEvent[$ev['id']] ?? []; $attachType = 'event'; $attachId = $ev['id']; require BASE_DIR . '/app/views/_dateien.php'; ?>

    <details class="subsection">
      <summary>💬 <?= e(t('ev_comments')) ?> (<?= count($comments[$ev['id']] ?? []) ?>)</summary>
      <ul class="comment-list">
        <?php foreach ($comments[$ev['id']] ?? [] as $c): ?>
          <li>
            <strong><?= e($c['author'] ?? t('unknown')) ?></strong>
            <span class="muted"><?= e($c['created_at']) ?></span>
            <p class="prewrap"><?= e($c['text']) ?></p>
            <?php if ((int) $c['user_id'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
              <form class="inline" action="/intern/kommentare/<?= $c['id'] ?>/delete" method="post"><?= csrf_field() ?><button class="btn btn-tiny btn-danger"><?= e(mb_strtolower(t('delete'))) ?></button></form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <form action="/intern/termine/<?= $ev['id'] ?>/kommentar" method="post" class="comment-form"><?= csrf_field() ?>
        <input name="text" placeholder="<?= e(t('ev_comment_ph')) ?>" required>
        <button class="btn btn-small"><?= e(t('send')) ?></button>
      </form>
    </details>

    <?php if ($ev['date'] < date('Y-m-d')): ?>
      <p class="muted small">🔒 <?= e(t('ev_locked')) ?></p>
    <?php else: ?>
    <details class="subsection">
      <summary>✏️ <?= e(t('edit')) ?></summary>
      <form method="post" action="/intern/termine/<?= $ev['id'] ?>/update" class="form-grid" data-eventfields='<?= e(json_encode(EVENT_TYPE_FIELDS)) ?>'><?= csrf_field() ?>
        <label><?= e(t('ev_type')) ?>
          <select name="type"><?php foreach (EVENT_TYPES as $val => $lbl): ?><option value="<?= $val ?>" <?= $ev['type'] === $val ? 'selected' : '' ?>><?= e(event_type_label($val)) ?></option><?php endforeach; ?></select>
        </label>
        <label><?= e(t('name')) ?><input name="title" value="<?= e($ev['title']) ?>" required></label>
        <label><?= e(t('date')) ?><input type="date" name="date" value="<?= e($ev['date']) ?>" required></label>
        <label><?= e(t('status')) ?>
          <select name="status"><?php foreach (EVENT_STATUS as $val => $lbl): ?><option value="<?= $val ?>" <?= $ev['status'] === $val ? 'selected' : '' ?>><?= e(event_status_label($val)) ?></option><?php endforeach; ?></select>
        </label>
        <label data-eventfield="times"><?= e(t('ev_meet')) ?><input type="time" name="time_meet" value="<?= e($ev['time_meet']) ?>"></label>
        <label data-eventfield="times"><?= e(t('ev_start')) ?><input type="time" name="time" value="<?= e($ev['time']) ?>"></label>
        <label data-eventfield="times"><?= e(t('ev_end')) ?><input type="time" name="time_end" value="<?= e($ev['time_end']) ?>"></label>
        <label data-eventfield="venue"><?= e(t('ev_venue')) ?>
          <select name="venue_id"><option value="">–</option><?php foreach ($venues as $v): ?><option value="<?= $v['id'] ?>" <?= (int) $ev['venue_id'] === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?></select>
        </label>
        <label data-eventfield="venue"><?= e(t('ev_location_free')) ?><input name="location" value="<?= e($ev['location']) ?>"></label>
        <label data-eventfield="setlist"><?= e(t('ev_setlist')) ?>
          <select name="setlist_id"><option value="">–</option><?php foreach ($setlists as $sl): ?><option value="<?= $sl['id'] ?>" <?= (int) $ev['setlist_id'] === (int) $sl['id'] ? 'selected' : '' ?>><?= e($sl['name']) ?></option><?php endforeach; ?></select>
        </label>
        <label><?= e(t('ev_responsible')) ?>
          <select name="responsible_id"><option value="">–</option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>" <?= (int) $ev['responsible_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
        </label>
        <?php
          $prodEv = $ev;
          $gearSel = array_map('intval', array_column($gearByEvent[$ev['id']] ?? [], 'id'));
          require BASE_DIR . '/app/views/intern/_event_production.php';
        ?>
        <label data-eventfield="fee"><?= e(t('ev_fee')) ?><input name="fee" value="<?= e($ev['fee']) ?>"></label>
        <label data-eventfield="fee"><?= e(t('ev_invoice')) ?><input name="invoice_no" value="<?= e($ev['invoice_no']) ?>"></label>
        <label class="span2"><?= e(t('ev_notes')) ?><textarea name="notes" rows="2"><?= e($ev['notes']) ?></textarea></label>
        <fieldset class="span2 pubfields" data-eventfield="public">
          <legend><?= e(t('ev_public_display')) ?></legend>
          <label class="checkbox"><input type="checkbox" name="is_public" value="1" <?= $ev['is_public'] ? 'checked' : '' ?>> <?= e(t('ev_show_on_site_short')) ?></label>
          <label><?= e(t('ev_public_title')) ?><input name="public_title" value="<?= e($ev['public_title']) ?>" placeholder="<?= e(t('ev_public_title_ph')) ?>"></label>
          <label><?= e(t('ev_public_link')) ?><input name="public_link" value="<?= e($ev['public_link']) ?>"></label>
          <label><?= e(t('ev_public_info')) ?><input name="public_info" value="<?= e($ev['public_info']) ?>"></label>
        </fieldset>
        <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
      </form>
      <form method="post" action="/intern/termine/<?= $ev['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')" class="inline"><?= csrf_field() ?>
        <button class="btn btn-danger btn-small"><?= e(t('ev_delete')) ?></button>
      </form>
    </details>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php if (!$events): ?><p class="muted center"><?= e(t('ev_none')) ?></p><?php endif; ?>
<script src="/assets/eventfields.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
