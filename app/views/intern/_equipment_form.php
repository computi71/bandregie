<?php
/**
 * Bearbeiten-Formular für ein Gerät. Erwartet $formEq (Datensatz), $members,
 * $items und $user. Wird an zwei Stellen gebraucht: aufgeklappt bei
 * eigenständigen Geräten und im Dialog bei Bestandteilen.
 */
// Fremdes Eigentum bleibt lesbar, aber unveränderlich — die Felder sind
// gesperrt, entscheidend ist aber die Prüfung in der Route.
$eqMayOwn = eq_may_edit_owner_fields($formEq, $user);
$eqLock = $eqMayOwn ? '' : 'disabled';
// Was jemand für sein Gerät bezahlt hat, steht auch nicht in einem gesperrten
// Feld — ein gesperrtes Feld verbirgt nichts, es zeigt nur grau an.
$eqSeePrice = eq_may_see_price($formEq, $user);
?>
<form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
  <label><?= e(t('name')) ?><input name="name" value="<?= e($formEq['name']) ?>" required></label>
  <label><?= e(t('eq_cat')) ?>
    <select name="category"><?php foreach (EQ_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>" <?= $formEq['category'] === $val ? 'selected' : '' ?>><?= e(eq_category_label($val)) ?></option><?php endforeach; ?></select>
  </label>
  <label data-eqinherit><?= e(t('eq_owner')) ?>
    <select name="owner_id" <?= $eqLock ?>><option value=""><?= e(t('eq_owner_band')) ?></option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>" <?= (int) $formEq['owner_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
  </label>
  <?php // Die Vorschlagsliste steht einmal oben auf der Seite. ?>
  <label data-eqinherit><?= e(t('eq_location')) ?><input name="location" list="eq-locations" value="<?= e($formEq['location']) ?>"></label>
  <label><?= e(t('eq_parent')) ?>
    <?php
      // Sich selbst oder einen eigenen Bestandteil als übergeordnetes Gerät zu
      // wählen ergäbe eine Schleife — die Liste lässt beides gar nicht erst zu.
      $eqBlocked = [(int) $formEq['id'], ...eq_descendants((int) $formEq['id'], $items)];
    ?>
    <select name="parent_id" <?= $eqLock ?>><option value=""><?= e(t('eq_parent_none')) ?></option>
      <?php foreach ($items as $other): ?>
        <?php if (in_array((int) $other['id'], $eqBlocked, true)) continue; ?>
        <option value="<?= $other['id'] ?>" <?= (int) ($formEq['parent_id'] ?? 0) === (int) $other['id'] ? 'selected' : '' ?>><?= e($other['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= e(t('eq_slot')) ?><input name="slot" list="eq-slots" value="<?= e($formEq['slot'] ?? '') ?>" placeholder="<?= e(t('eq_slot_ph')) ?>"></label>
  <p class="muted span2" data-eqhint hidden><?= e(t('eq_inherit_hint')) ?></p>
  <?php if (!$eqMayOwn): ?>
    <p class="muted small span2">🔒 <?= e(t('eq_owner_locked')) ?></p>
  <?php endif; ?>
  <?php if ($eqSeePrice): ?>
    <label><?= e(t('eq_purchased')) ?><input type="date" name="purchased_on" value="<?= e($formEq['purchased_on'] ?? '') ?>" <?= $eqLock ?>></label>
    <?php // Textfeld statt type="number", sonst geht das Komma verloren.
          // Angezeigt wird deshalb auch in der Schreibweise des Landes. ?>
    <label><?= e(t('eq_price')) ?><input name="price" inputmode="decimal" placeholder="0,00" value="<?= $formEq['price_cents'] !== null ? e(number_format((int) $formEq['price_cents'] / 100, 2, ',', '.')) : '' ?>" <?= $eqLock ?>></label>
    <?php // Neben Datum und Preis, weil es zur Anschaffung gehört und dieselbe
          // Sichtbarkeitsregel hat: Was ein Gerät gekostet hat und in welchem
          // Zustand es kam, geht dieselben Leute etwas an. ?>
    <?php // Artikelnummer und Beleg gehören zur Anschaffung und stehen deshalb
          // hier. Die Nummer gilt je Gerät, die Rechnung wird nur ausgewählt —
          // erfasst wird sie einmal in der Rechnungsliste. ?>
    <label><?= e(t('inv_article_no')) ?><input name="article_no" value="<?= e($formEq['article_no'] ?? '') ?>" <?= $eqLock ?>></label>
    <?php // Menge nur für Kleinteile und Meterware. Echte Geräte bleiben bei 1
          // und bekommen je Stück ihren eigenen Eintrag (#185). ?>
    <label><?= e(t('eq_quantity')) ?>
      <input name="quantity" inputmode="numeric" value="<?= (int) ($formEq['quantity'] ?? 1) ?>" <?= $eqLock ?>>
      <span class="muted small"><?= e(t('eq_quantity_hint')) ?></span>
    </label>
    <label><?= e(t('inv_pick')) ?>
      <select name="invoice_id" <?= $eqLock ?>>
        <option value=""><?= e(t('inv_pick_none')) ?></option>
        <?php foreach ($invoices ?? [] as $invOpt): ?>
          <option value="<?= (int) $invOpt['id'] ?>" <?= (int) ($formEq['invoice_id'] ?? 0) === (int) $invOpt['id'] ? 'selected' : '' ?>><?= e(invoice_label($invOpt)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="span2"><?= e(t('eq_acquired')) ?>
      <select name="acquired_as" <?= $eqLock ?>>
        <option value=""><?= e(t('eq_acquired_unknown')) ?></option>
        <?php foreach (array_keys(EQ_ACQUIRED) as $eqAcq): ?>
          <option value="<?= e($eqAcq) ?>" <?= ($formEq['acquired_as'] ?? '') === $eqAcq ? 'selected' : '' ?>><?= e(eq_acquired_label($eqAcq)) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="muted small"><?= e(t('eq_acquired_hint')) ?></span>
    </label>
    <label class="span2"><?= e(t('eq_afa_years')) ?>
      <input name="afa_years" inputmode="numeric" placeholder="<?= (int) tax_afa_years_for($formEq['category']) ?>" value="<?= $formEq['afa_years'] !== null ? (int) $formEq['afa_years'] : '' ?>" <?= $eqLock ?>>
      <span class="muted small"><?= e(t('eq_afa_hint')) ?></span>
    </label>
  <?php else: ?>
    <p class="muted small span2">🔒 <?= e(t('eq_price_hidden')) ?></p>
  <?php endif; ?>
  <label class="checkbox span2"><input type="checkbox" name="is_standard" value="1" <?= $formEq['is_standard'] ? 'checked' : '' ?>> 📦 <?= e(t('eq_standard')) ?></label>
  <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e($formEq['notes']) ?></textarea></label>
  <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
</form>
<?php
// Aufteilen bietet sich an, wenn eine Zeile für mehrere gleiche Geräte steht.
// Bei Geräten mit Bestandteilen geht es nicht — siehe Route.
$eqHasParts = (bool) array_filter($items, fn($i) => (int) ($i['parent_id'] ?? 0) === (int) $formEq['id']);
$eqQtyHint = eq_quantity_hint($formEq);
?>
<?php
// Kauf und Abgang in der Kasse. Was schon gebucht ist, steht als Verweis da —
// zweimal buchen macht aus einem Kauf keine zwei.
$eqBooked = $bookingsByEq[(int) $formEq['id']] ?? [];
$eqBoughtAlready = (bool) array_filter($eqBooked, fn($b) => $b['type'] === 'ausgabe');
?>
<?php if ($eqSeePrice && perm_allows($user, 'kasse')): ?>
  <details class="subsection">
    <summary>💰 <?= e(t('eqb_title')) ?><?= $eqBooked ? ' (' . count($eqBooked) . ')' : '' ?></summary>

    <?php if ($eqBooked): ?>
      <ul class="task-list">
        <?php foreach ($eqBooked as $b): ?>
          <li>
            <span class="badge"><?= e(fmt_date($b['date'])) ?></span>
            <strong><?= $b['type'] === 'einnahme' ? '+' : '−' ?><?= e(fmt_money((int) $b['amount_cents'])) ?></strong>
            <span class="muted small">
              <?= e($b['description']) ?>
              <?= $b['private_for'] !== null ? ' · 🔒 ' . e(t('fin_private')) : '' ?>
            </span>
            <a class="btn btn-tiny" href="/intern/kasse?jahr=<?= (int) substr($b['date'], 0, 4) ?>"><?= e(t('eqb_show')) ?> →</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (!$eqBoughtAlready && $formEq['price_cents'] !== null): ?>
      <form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/kauf" class="inline"><?= csrf_field() ?>
        <label><?= e(t('eqb_payer')) ?>
          <select name="payer">
            <?php if (perm_allows($user, 'kasse', 'write')): ?><option value="band"><?= e(t('eqb_payer_band')) ?></option><?php endif; ?>
            <?php if ((int) ($formEq['owner_id'] ?? 0) === (int) $user['id']): ?><option value="privat"><?= e(t('eqb_payer_private')) ?></option><?php endif; ?>
          </select>
        </label>
        <button class="btn btn-small"><?= e(sprintf(t('eqb_book_purchase'), fmt_money((int) $formEq['price_cents']))) ?></button>
      </form>
      <p class="muted small"><?= e(t('eqb_hint')) ?></p>
    <?php elseif ($formEq['price_cents'] === null): ?>
      <p class="muted small"><?= e(t('eqb_needs_price')) ?></p>
    <?php endif; ?>

    <?php if (empty($formEq['disposed_on'])): ?>
      <details>
        <summary><?= e(t('eqb_dispose')) ?></summary>
        <p class="muted small"><?= e(t('eqb_dispose_hint')) ?></p>
        <form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/abgang" class="form-grid"><?= csrf_field() ?>
          <label><?= e(t('eqb_proceeds')) ?><input name="amount" inputmode="decimal" placeholder="0,00"></label>
          <label><?= e(t('date')) ?><input type="date" name="date" value="<?= date('Y-m-d') ?>"></label>
          <label><?= e(t('eqb_payer_gets')) ?>
            <select name="payer">
              <?php if (perm_allows($user, 'kasse', 'write')): ?><option value="band"><?= e(t('eqb_payer_band')) ?></option><?php endif; ?>
              <?php if ((int) ($formEq['owner_id'] ?? 0) === (int) $user['id']): ?><option value="privat"><?= e(t('eqb_payer_private')) ?></option><?php endif; ?>
            </select>
          </label>
          <button class="btn btn-small span2"><?= e(t('eqb_dispose')) ?></button>
        </form>
      </details>
    <?php else: ?>
      <p class="muted small">📦 <?= e(sprintf(t('eqb_disposed_on'), fmt_date($formEq['disposed_on']))) ?></p>
      <form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/reaktivieren" class="inline"><?= csrf_field() ?>
        <button class="btn btn-tiny"><?= e(t('eqb_reactivate')) ?></button>
      </form>
    <?php endif; ?>
  </details>
<?php endif; ?>

<?php if ($eqMayOwn && !$eqHasParts): ?>
  <details class="subsection">
    <summary><?= e(t('eq_split')) ?><?= $eqQtyHint ? ' ' . e(sprintf(t('eq_split_found'), $eqQtyHint)) : '' ?></summary>
    <p class="muted small"><?= e(t('eq_split_hint')) ?></p>
    <form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/aufteilen" class="inline"><?= csrf_field() ?>
      <label><?= e(t('eq_count')) ?><input type="number" name="count" value="<?= $eqQtyHint ?: 2 ?>" min="2" max="99"></label>
      <button class="btn btn-small"><?= e(t('eq_split')) ?></button>
    </form>
  </details>
<?php endif; ?>
<form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>" class="inline"><?= csrf_field() ?>
  <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
</form>
