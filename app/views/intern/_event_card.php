<?php
// Die Karte eines Termins — Kopf, Zeiten, Gepäck, Rückmeldungen, Anhänge,
// „mitnehmen", Kommentare und das Bearbeiten-Formular.
//
// Einmal hier, weil die Übersicht denselben Ausschnitt zeigt wie die
// Terminliste: „Nächste Termine" ist nichts anderes als ein Teil dieser Liste,
// und zwei Fassungen derselben Karte laufen über kurz oder lang auseinander
// (#277).
//
// Vorher setzen: $ev und alles, was event_view_data() liefert.
// Freiwillig: $evFold — dann wird die Karte zusammenklappbar und ihr Kopf zur
// Zusammenfassung. Die Übersicht braucht das, damit sie wieder in einen Blick
// passt; die Terminliste zeigt weiter alles offen (#320). $evOpen bestimmt,
// welche eine offen startet.
?>
  <?php
    $venue = $ev['venue_id'] && isset($venueMap[$ev['venue_id']]) ? $venueMap[$ev['venue_id']] : null;
    // Eine Fassung der Karte, zwei Hüllen: <section> offen, <details> gefaltet.
    // Der Name gruppiert sie — ein Browser lässt dann nur eine offen.
    $evFold = !empty($evFold);
    $evTag = $evFold ? 'details' : 'section';
    $kopfTag = $evFold ? 'summary' : 'div';
  ?>
  <?php // „acc" schaltet die Notlösung für Browser ohne name-Gruppierung frei.
        // data-seen meldet dem Server, dass der Termin aufgeklappt wurde — erst
        // dann ist er wirklich angesehen (#321). ?>
  <?php // Der Anker macht die Karte adressierbar: Eine Mitteilung führt damit
        // zu dem Termin, um den es geht, statt an den Anfang der Liste (#322).
        // Zugeklappt macht accordion.js sie beim Sprung auf. ?>
  <<?= $evTag ?> id="ev<?= (int) $ev['id'] ?>"
     class="card event-card acc <?= $ev['status'] === 'abgesagt' ? 'muted' : '' ?>"
     <?= $evFold ? 'name="dashev"' : '' ?><?= $evFold && !empty($evOpen) ? ' open' : '' ?>
     data-seen="event:<?= (int) $ev['id'] ?>" data-token="<?= e(csrf_token()) ?>">
    <?php require BASE_DIR . '/app/views/intern/_event_kopf.php'; ?>
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

    <?php require BASE_DIR . '/app/views/intern/_event_gaeste.php'; ?>

    <?php $attachFiles = $filesByEvent[$ev['id']] ?? []; $attachType = 'event'; $attachId = $ev['id']; require BASE_DIR . '/app/views/_dateien.php'; ?>

    <?php // Alles zu diesem Termin aufs Gerät holen — Setlist, Noten, Rider,
          // Patchliste. Ohne JavaScript steht der Knopf nicht da: er hat ohne
          // Service Worker keine Wirkung, und ein Knopf, der nichts tut, ist
          // schlimmer als keiner. ?>
    <p class="muted small" data-offlinegig="/intern/termine/<?= (int) $ev['id'] ?>/offline" hidden
       data-offlinecheck="<?= e(json_encode(event_offline_check($ev), JSON_UNESCAPED_SLASHES)) ?>"
       data-offlineready="<?= e(t('off_ready')) ?>" data-offlinetake="⭳ <?= e(t('off_take')) ?>"
       data-offlinebusy="<?= e(t('off_busy')) ?>" data-offlinedone="<?= e(t('off_done')) ?>"
       data-offlinesome="<?= e(t('off_some')) ?>" data-offlinefailed="<?= e(t('off_failed')) ?>">
      <button type="button" class="btn btn-small" data-offlinestart>⭳ <?= e(t('off_take')) ?></button>
      <span data-offlinestate></span>
    </p>

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

    <?php // Was jemand nicht darf, wird ihm nicht angeboten: Der Server weist
          // das Speichern ohnehin ab, und ein Formular, das erst nach dem
          // Ausfüllen „keine Berechtigung" sagt, ist eine Falle (#279). ?>
    <?php if (!perm_allows($user, 'termine', 'write')): ?>
    <?php elseif ($ev['date'] < date('Y-m-d')): ?>
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
        <label data-eventfield="support"><?= e(t('ev_support')) ?><input name="support_act" maxlength="255" value="<?= e($ev['support_act']) ?>" placeholder="<?= e(t('ev_support_ph')) ?>"></label>
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
        <?php if (public_page_active()): ?>
        <fieldset class="span2 pubfields" data-eventfield="public">
          <legend><?= e(t('ev_public_display')) ?></legend>
          <label class="checkbox"><input type="checkbox" name="is_public" value="1" <?= $ev['is_public'] ? 'checked' : '' ?>> <?= e(t('ev_show_on_site_short')) ?></label>
          <label><?= e(t('ev_public_title')) ?><input name="public_title" value="<?= e($ev['public_title']) ?>" placeholder="<?= e(t('ev_public_title_ph')) ?>"></label>
          <label><?= e(t('ev_public_link')) ?><input name="public_link" value="<?= e($ev['public_link']) ?>"></label>
          <label><?= e(t('ev_public_info')) ?><input name="public_info" value="<?= e($ev['public_info']) ?>"></label>
        </fieldset>
        <?php else: ?>
          <?php // Weggelassen heißt nicht gelöscht: Ein fehlendes Kästchen ist ein
                // fehlendes Feld im Formular, und das Speichern würde den Termin
                // stillschweigend von der Website nehmen. Die Werte fahren
                // unsichtbar mit und stehen wieder da, wenn jemand die
                // öffentliche Seite zurückschaltet (#288). ?>
          <?php if ($ev['is_public']): ?><input type="hidden" name="is_public" value="1"><?php endif; ?>
          <input type="hidden" name="public_title" value="<?= e($ev['public_title']) ?>">
          <input type="hidden" name="public_link" value="<?= e($ev['public_link']) ?>">
          <input type="hidden" name="public_info" value="<?= e($ev['public_info']) ?>">
        <?php endif; ?>
        <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
      </form>
      <form method="post" action="/intern/termine/<?= $ev['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>" class="inline"><?= csrf_field() ?>
        <button class="btn btn-danger btn-small"><?= e(t('ev_delete')) ?></button>
      </form>
    </details>
    <?php endif; ?>
  </<?= $evTag ?>>
