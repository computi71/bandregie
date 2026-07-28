<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('songs_title')) ?></h1>

<details class="card collapsible" <?= $edit || !$songs ? 'open' : '' ?>>
  <summary><?= $edit ? '✏️ „' . e($edit['title']) . '" ' . e(t('song_edit_suffix')) : '➕ ' . e(t('song_new')) ?></summary>
  <form method="post" action="<?= $edit ? '/intern/songs/' . $edit['id'] . '/update' : '/intern/songs' ?>" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('title_lbl')) ?><input name="title" value="<?= e($edit['title'] ?? '') ?>" required></label>
    <label><?= e(t('song_original')) ?><input name="artist" value="<?= e($edit['artist'] ?? '') ?>" placeholder="<?= e(t('song_original_ph')) ?>"></label>
    <label><?= e(t('song_composer')) ?><input name="composer" value="<?= e($edit['composer'] ?? '') ?>" placeholder="<?= e(t('song_composer_ph')) ?>"></label>
    <label><?= e(t('song_gema')) ?><input name="gema_werknr" value="<?= e($edit['gema_werknr'] ?? '') ?>" placeholder="<?= e(t('song_gema_ph')) ?>"></label>
    <label><?= e(t('song_keylbl')) ?><input name="song_key" value="<?= e($edit['song_key'] ?? '') ?>" placeholder="z. B. Am"></label>
    <label><?= e(t('song_tempo')) ?><input name="tempo" value="<?= e($edit['tempo'] ?? '') ?>" placeholder="z. B. 120 BPM"></label>
    <label><?= e(t('song_len')) ?><input name="duration" value="<?= $edit && $edit['duration_sec'] ? floor($edit['duration_sec'] / 60) . ':' . str_pad((string) ($edit['duration_sec'] % 60), 2, '0', STR_PAD_LEFT) : '' ?>" placeholder="3:45"></label>
    <label><?= e(t('status')) ?>
      <select name="status">
        <?php foreach (SONG_STATUS as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= ($edit['status'] ?? 'vorschlag') === $val ? 'selected' : '' ?>><?= e(song_status_label($val)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2" placeholder="<?= e(t('song_notes_ph')) ?>"><?= e($edit['notes'] ?? '') ?></textarea></label>
    <div class="span2 row-buttons">
      <button class="btn btn-primary"><?= e($edit ? t('save') : t('song_add')) ?></button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="/intern/songs"><?= e(t('cancel')) ?></a><?php endif; ?>
    </div>
  </form>
  <?php if ($edit): ?>
    <?php $attachFiles = $songFiles ?? []; $attachType = 'song'; $attachId = $edit['id']; require BASE_DIR . '/app/views/_dateien.php'; ?>
  <?php endif; ?>
</details>

<div class="card">
  <p class="muted small"><?= e(t('songs_usable_hint')) ?> <?= e(t('rate_hint')) ?></p>
  <table class="table">
    <thead><tr><th><?= e(t('title_lbl')) ?></th><th><?= e(t('songs_col_original')) ?></th><th><?= e(t('song_keylbl')) ?></th><th><?= e(t('song_tempo')) ?></th><th><?= e(t('songs_col_len')) ?></th><th><?= e(t('status')) ?></th><th><?= e(t('songs_col_rating')) ?></th><th><?= e(t('songs_col_uses')) ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($songs as $song): ?>
        <tr class="<?= in_array($song['status'], ['archiv', 'abgewiesen'], true) ? 'muted' : '' ?>">
          <td><strong><?= e($song['title']) ?></strong><?php if ($song['notes']): ?><div class="muted small"><?= e($song['notes']) ?></div><?php endif; ?></td>
          <td><?= e($song['artist'] ?: t('own_song')) ?></td>
          <td><?= e($song['song_key'] ?: '–') ?></td>
          <td><?= e($song['tempo'] ?: '–') ?></td>
          <td><?= fmt_duration($song['duration_sec']) ?></td>
          <td><span class="badge status-<?= e($song['status']) ?>"><?= e(song_status_label($song['status'])) ?></span></td>
          <td class="rating">
            <?php $r = $ratings[$song['id']] ?? null; ?>
            <form method="post" action="/intern/songs/<?= $song['id'] ?>/bewerten" class="inline stars"><?= csrf_field() ?>
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button name="rating" value="<?= $i ?>" title="<?= $i ?>/5"
                  class="star <?= (int) ($r['mine'] ?? 0) >= $i ? 'star-on' : '' ?>">★</button>
              <?php endfor; ?>
              <?php if (!empty($r['mine'])): ?>
                <button name="rating" value="0" class="star star-clear" title="<?= e(t('rate_clear')) ?>">✕</button>
              <?php endif; ?>
            </form>
            <div class="muted small">
              <?= $r && $r['votes'] ? e($r['avg_rating']) . ' · ' . (int) $r['votes'] . ' ' . e((int) $r['votes'] === 1 ? t('rate_vote') : t('rate_votes')) : e(t('rate_none')) ?>
            </div>
          </td>
          <td title="<?= e(t('songs_uses_title')) ?>">📋 <?= $song['setlist_count'] ?><?= $song['played_count'] > 0 ? ' · 🎤 ' . $song['played_count'] : '' ?></td>
          <td class="row-buttons">
            <a class="btn btn-tiny" href="/intern/songs/<?= $song['id'] ?>/edit">✏️</a>
            <?php if ($song['played_count'] == 0): ?>
              <form class="inline" method="post" action="/intern/songs/<?= $song['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$songs): ?><p class="muted center"><?= e(t('songs_none')) ?></p><?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
