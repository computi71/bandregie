# Tasks: several assignees, a quorum, and links — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A task can be assigned to several members with a settable number of completions before it counts as done, and can point at any other entry the application knows about.

**Architecture:** Two new tables (`task_assignees`, `task_links`) and one column (`tasks.required_done`). `tasks.status` stays but becomes derived: one function, `task_status_apply()`, recomputes and writes it, and no route writes it directly. Links validate their `kind` against `ITEM_KINDS` from #331, which requires lifting the per-kind visibility check out of the `/intern/gesehen` route into one shared function.

**Tech Stack:** PHP 8.3+, MySQL/MariaDB, no framework. Front controller `httpdocs/index.php`, helpers in `app/bootstrap.php`, marks in `app/marks.php`, schema and migrations in `app/schema.php`, German UI strings in `app/strings/de.php`, views in `app/views/intern/`.

**Spec:** `docs/superpowers/specs/2026-09-23-tasks-assignees-and-links-design.md`

## Global Constraints

- **No test framework exists.** Verification is `bin/*-pruefen.php` scripts run on staging over SSH. This plan's red/green cycle is: add the check, push, run it on staging and watch it fail, implement, push, run it and watch it pass.
- **Staging changes only by `git pull`.** Never `scp` or edit files there. Staging: `ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13`, path `/var/www/bandroadie-oss`.
- **Check scripts run as the web user:** `sudo -u www-data php bin/…`. Run as anybody else, the session file php-fpm needs is unreadable and every page answers 302.
- **Work on the branch `tasks-assignees`.** It exists and carries the spec. Merge to `main` only after staging is green; `VERSION` is bumped at the merge, not on the branch.
- **Commit messages in English**, saying why rather than what. No `Co-Authored-By` lines.
- **UI strings are German** and live in `app/strings/de.php`. Never inline a user-visible string in a view.
- **Every output escaped with `e()`, every query parameterised.** CSRF is checked centrally for every POST at `httpdocs/index.php:78`; a form only needs `csrf_field()`.
- **Migrations only add.** Guard every `ALTER` with `column_exists()`, every table with `CREATE TABLE IF NOT EXISTS`. Update the `CREATE TABLE` for fresh installs **and** add the `ALTER` for existing ones, or the two paths diverge.
- **Version digits:** Part 1 and Part 2 are new behaviour → second digit (2.13.0 → 2.14.0 → 2.15.0).

---

### Task 1: The schema, and the one function that owns `tasks.status`

**Files:**
- Modify: `app/schema.php` — the `tasks` `CREATE TABLE` (around line 100), the marks migration loop (around line 722), and a new migration block
- Modify: `app/bootstrap.php` — new function `task_status_apply()`, placed next to `open_items_count()` (around line 4102)
- Test: `bin/aufgaben-pruefen.php` (create)

**Interfaces:**
- Consumes: `q()`, `row()`, `rows()` from `app/bootstrap.php`
- Produces: `task_status_apply(int $taskId, bool $darfOeffnen = false): string` — recomputes and writes `tasks.status`, returns the written status (`'offen'` or `'erledigt'`, or `''` if the task does not exist). Tables `task_assignees(task_id, user_id, done_at)` and column `tasks.required_done`.

- [ ] **Step 1: Write the failing check**

Create `bin/aufgaben-pruefen.php`:

```php
<?php
// Zuständige, Quorum und Verknüpfungen von Aufgaben durchspielen (#334, #335).
//
// Das Projekt hat keine Testsammlung; diese Datei ist die Absicherung für den
// Teil, dessen Fehler man nicht sieht: Eine Aufgabe, die bei „2 von 3" hängen
// bleibt, sieht aus wie eine Aufgabe, die noch niemand gemacht hat.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver.
// Als Webnutzer starten, sonst gehört die Sitzungsdatei dem falschen Konto:
//
//   sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/aufgaben-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-56s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

$leute = rows('SELECT * FROM users ORDER BY id LIMIT 3');
if (count($leute) < 3) { fwrite(STDERR, "Drei Konten werden gebraucht\n"); exit(2); }
[$A, $B, $C] = $leute;

/** Eine Testaufgabe mit Zuständigen anlegen. */
$aufgabe = static function (array $wer, int $verlangt) use ($A): int {
    q("INSERT INTO tasks (title, notes, due_date, status, created_by, required_done)
       VALUES ('ZZ Pruefaufgabe', '', '', 'offen', ?, ?)", [(int) $A['id'], $verlangt]);
    $id = (int) $GLOBALS['db']->lastInsertId();
    foreach ($wer as $u) {
        q('INSERT INTO task_assignees (task_id, user_id) VALUES (?,?)', [$id, (int) $u['id']]);
    }
    return $id;
};
/** Abhaken für ein Konto. */
$hake = static function (int $id, array $u): void {
    q('UPDATE task_assignees SET done_at = NOW(3) WHERE task_id = ? AND user_id = ?',
      [$id, (int) $u['id']]);
};
$stand = static fn(int $id): string => (string) row('SELECT status FROM tasks WHERE id = ?', [$id])['status'];
$weg = static function (int $id): void {
    q('DELETE FROM task_assignees WHERE task_id = ?', [$id]);
    q('DELETE FROM tasks WHERE id = ?', [$id]);
    item_forget('task', $id);
};

// -------------------------------------------- 1. Ein Zuständiger, wie bisher
$t1 = $aufgabe([$A], 0);
$pruefe('einer zustaendig: vor dem Haken offen', task_status_apply($t1) === 'offen');
$hake($t1, $A);
$pruefe('einer zustaendig: ein Haken erledigt', task_status_apply($t1) === 'erledigt');
$weg($t1);

// -------------------------------------------------- 2. Quorum nicht erreicht
$t2 = $aufgabe([$A, $B, $C], 3);
$hake($t2, $A);
$hake($t2, $B);
$pruefe('zwei von drei bei verlangt 3: offen', task_status_apply($t2) === 'offen');
$hake($t2, $C);
$pruefe('drei von drei bei verlangt 3: erledigt', task_status_apply($t2) === 'erledigt');
$weg($t2);

// ------------------------------------------------------- 3. Quorum erreicht
$t3 = $aufgabe([$A, $B, $C], 2);
$hake($t3, $A);
$pruefe('einer von drei bei verlangt 2: offen', task_status_apply($t3) === 'offen');
$hake($t3, $B);
$pruefe('zwei von drei bei verlangt 2: erledigt', task_status_apply($t3) === 'erledigt');
$weg($t3);

// ----------------------------------------------- 4. Null heisst „alle"
$t4 = $aufgabe([$A, $B, $C], 0);
$hake($t4, $A);
$hake($t4, $B);
$pruefe('verlangt 0 mit drei: zwei genuegen nicht', task_status_apply($t4) === 'offen');
$hake($t4, $C);
$pruefe('verlangt 0 mit drei: alle drei erledigen', task_status_apply($t4) === 'erledigt');
$weg($t4);

// -------------------------- 5. Verlangt mehr als Zustaendige: gedeckelt
$t5 = $aufgabe([$A], 3);
$hake($t5, $A);
$pruefe('verlangt 3, nur einer zustaendig: erledigt', task_status_apply($t5) === 'erledigt');
$weg($t5);

// ------------- 6. Neuberechnung darf schliessen, aber nicht wieder oeffnen
$t6 = $aufgabe([$A, $B], 2);
$hake($t6, $A);
$hake($t6, $B);
task_status_apply($t6);
q('DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?', [$t6, (int) $B['id']]);
$pruefe('erledigt, Zustaendiger entfernt: bleibt erledigt',
    task_status_apply($t6) === 'erledigt' && $stand($t6) === 'erledigt');
$pruefe('erledigt, ausdruecklich zurueckgenommen: wieder offen',
    task_status_apply($t6, true) === 'offen' && $stand($t6) === 'offen');
$weg($t6);

// --------- 7. Entfernen kann eine haengende Aufgabe schliessen
$t7 = $aufgabe([$A, $B, $C], 0);
$hake($t7, $A);
$hake($t7, $B);
task_status_apply($t7);
$pruefe('drei zustaendig, zwei fertig: haengt', $stand($t7) === 'offen');
q('DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?', [$t7, (int) $C['id']]);
$pruefe('Dritter entfernt: jetzt erledigt', task_status_apply($t7) === 'erledigt');
$weg($t7);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
```

- [ ] **Step 2: Push it and watch it fail on staging**

```bash
git add bin/aufgaben-pruefen.php
git commit -m "Add the check for assignees and the quorum before the code exists (#334)"
git push origin tasks-assignees
```

Then:

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten'
```

Expected: a fatal error — `Table 'task_assignees' doesn't exist` or `Call to undefined function task_status_apply()`. That failure is the point: it proves the check reaches the code.

- [ ] **Step 3: Add the table and the column to `app/schema.php`**

Update the `CREATE TABLE` for fresh installs — find `CREATE TABLE IF NOT EXISTS tasks (` and add the column:

```php
  "CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    notes TEXT,
    assigned_to INT NULL,
    due_date VARCHAR(10) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'offen',
    required_done TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
```

Add the new table next to it:

```php
  // Wer für eine Aufgabe zuständig ist, und ob er sie getan hat (#334). Das
  // Häkchen sitzt hier und nicht an der Aufgabe: Sonst hakt einer für alle ab,
  // und niemand sieht, wer wirklich etwas getan hat.
  "CREATE TABLE IF NOT EXISTS task_assignees (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    done_at DATETIME(3) NULL,
    PRIMARY KEY (task_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
```

And the migration for existing installations, after the marks migration block:

```php
// Aufgaben bekommen mehrere Zuständige und ein Quorum (#334).
if (!column_exists('tasks', 'required_done')) {
  $db->exec('ALTER TABLE tasks ADD COLUMN required_done TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status');
}
// Das bisherige assigned_to wird eine Zeile in task_assignees. Die Spalte
// bleibt stehen und wird nicht mehr gelesen: Eine Spalte zu löschen ist die
// eine Migration, die man nicht zurücknehmen kann.
//
// done_at aus dem bisherigen Stand: Eine erledigte Aufgabe war von ihrem
// einen Zuständigen erledigt, und ohne das stünde nach dem Update jede
// abgehakte Aufgabe wieder offen.
if (!setting('migr_task_assignees')) {
  $db->exec("INSERT IGNORE INTO task_assignees (task_id, user_id, done_at)
             SELECT id, assigned_to, IF(status = 'erledigt', created_at, NULL)
               FROM tasks WHERE assigned_to IS NOT NULL");
  set_setting('migr_task_assignees', '1');
}
```

Also add `task_assignees` to the demo reset order in `app/demo.php` (the `$order` array around line 1123), before `tasks`, so a demo reset does not leave orphan rows.

- [ ] **Step 4: Add `task_status_apply()` to `app/bootstrap.php`**

Place it immediately before `open_items_count()`:

```php
/**
 * Den Stand einer Aufgabe aus den Häkchen ableiten und schreiben (#334).
 *
 * tasks.status bleibt eine Spalte, weil vier Stellen sie lesen — die
 * Sortierung der Liste, der Überblick, die Zahl am Symbol und die Marke. Aber
 * geschrieben wird sie nur hier, sonst rechnen vier Stellen dasselbe aus und
 * laufen auseinander.
 *
 * required_done = 0 heißt „alle": Stünde dort eine feste 3 und jemand nimmt
 * einen Zuständigen heraus, wäre die Aufgabe nie mehr erledigbar. Eine Zahl
 * größer als die Zahl der Zuständigen wird gedeckelt, aus demselben Grund.
 *
 * Niemand zuständig: Ein Häkchen genügt. Wer es setzt, wird dadurch zuständig
 * (das macht die Route), womit dieser Fall kein eigener Weg ist.
 *
 * $darfOeffnen ist der eine asymmetrische Fall. Wird ein Zuständiger entfernt,
 * geht sein Häkchen mit, und eine längst erledigte Aufgabe würde wieder
 * aufgehen — weil ein Konto gelöscht wurde, nicht weil jemand etwas vorhat.
 * Schließen darf diese Rechnung immer, öffnen nur, wenn jemand ausdrücklich
 * ein Häkchen zurückgenommen hat.
 */
function task_status_apply(int $taskId, bool $darfOeffnen = false): string {
  $t = row('SELECT status, required_done FROM tasks WHERE id = ?', [$taskId]);
  if (!$t) return '';
  $z = row('SELECT COUNT(*) AS zustaendige, COUNT(done_at) AS fertig
            FROM task_assignees WHERE task_id = ?', [$taskId]);
  $zustaendige = (int) $z['zustaendige'];
  $fertig = (int) $z['fertig'];
  $verlangt = (int) $t['required_done'];
  $noetig = $zustaendige === 0
    ? 1
    : ($verlangt > 0 ? min($verlangt, $zustaendige) : $zustaendige);
  $neu = $fertig >= $noetig ? 'erledigt' : 'offen';
  if ($neu === 'offen' && $t['status'] === 'erledigt' && !$darfOeffnen) return 'erledigt';
  if ($neu !== $t['status']) q('UPDATE tasks SET status = ? WHERE id = ?', [$neu, $taskId]);
  return $neu;
}

/**
 * Die Zuständigen einer Aufgabe samt Häkchen — für Liste und Überblick, damit
 * beide dieselbe Antwort geben.
 *
 * Rückgabe je Aufgabennummer: Liste aus [user_id, name, done_at].
 */
function task_assignees_map(array $taskIds): array {
  if (!$taskIds) return [];
  $in = implode(',', array_map('intval', $taskIds));
  $karte = [];
  foreach (rows("SELECT ta.task_id, ta.user_id, ta.done_at, u.name
                 FROM task_assignees ta JOIN users u ON u.id = ta.user_id
                 WHERE ta.task_id IN ($in) ORDER BY u.name") as $r) {
    $karte[(int) $r['task_id']][] = $r;
  }
  return $karte;
}
```

- [ ] **Step 5: Push and run the check — it must pass**

```bash
git add app/schema.php app/bootstrap.php app/demo.php
git commit -m "Let a task have several assignees, with one function owning its status (#334)"
git push origin tasks-assignees
```

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && php -l app/bootstrap.php && php -l app/schema.php && curl -sk --resolve staging.beatcuisine.de:443:127.0.0.1 -o /dev/null -w "%{http_code}\n" https://staging.beatcuisine.de/ && sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten'
```

Expected: `13 ok, 0 Fehler`. The `curl` is there to trigger the migration before the check runs.

---

### Task 2: Ticking becomes personal

**Files:**
- Modify: `httpdocs/index.php:1583-1592` — the `toggle` branch of the task route
- Modify: `bin/aufgaben-pruefen.php` — add the route-level checks

**Interfaces:**
- Consumes: `task_status_apply()` from Task 1
- Produces: `POST /intern/aufgaben/{id}/toggle` now writes `task_assignees.done_at` for the acting member and calls `task_status_apply()`

- [ ] **Step 1: Replace the toggle branch**

Current code at `httpdocs/index.php:1583`:

```php
  if (preg_match('~^/intern/aufgaben/(\d+)/(toggle|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'toggle') {
      item_update('task', (int) $m[1], static function () use ($m): void {
        q("UPDATE tasks SET status = CASE status WHEN 'offen' THEN 'erledigt' ELSE 'offen' END WHERE id = ?", [$m[1]]);
      }, (int) $me['id']);
      back('/intern/aufgaben');
    }
    item_forget('task', (int) $m[1]);
    q('DELETE FROM tasks WHERE id = ?', [$m[1]]);
    redirect('/intern/aufgaben');
  }
```

Replace with:

```php
  if (preg_match('~^/intern/aufgaben/(\d+)/(toggle|delete)$~', $path, $m) && $method === 'POST') {
    $taskNr = (int) $m[1];
    if ($m[2] === 'toggle') {
      // Das Häkchen ist persönlich (#334). Wer eine Aufgabe abhakt, für die
      // niemand zuständig war, wird dadurch zuständig: ein Zuständiger, ein
      // Häkchen, erledigt. Damit braucht der Fall keinen eigenen Weg — und die
      // Liste sagt hinterher, wer es war, was sie vorher nie konnte.
      $meinHaken = row('SELECT done_at FROM task_assignees WHERE task_id = ? AND user_id = ?',
                       [$taskNr, (int) $me['id']]);
      $zurueck = $meinHaken && $meinHaken['done_at'] !== null;
      if ($zurueck) {
        // Zurücknehmen: War ich nur durchs Abhaken zuständig, bin ich es
        // danach nicht mehr.
        row('SELECT 1 FROM tasks WHERE id = ?', [$taskNr]) && q(
          'UPDATE task_assignees SET done_at = NULL WHERE task_id = ? AND user_id = ?',
          [$taskNr, (int) $me['id']]);
      } else {
        q('INSERT INTO task_assignees (task_id, user_id, done_at) VALUES (?,?,NOW(3))
           ON DUPLICATE KEY UPDATE done_at = NOW(3)', [$taskNr, (int) $me['id']]);
      }
      // Nur das Zurücknehmen darf eine erledigte Aufgabe wieder öffnen.
      item_update('task', $taskNr, static function () use ($taskNr, $zurueck): void {
        task_status_apply($taskNr, $zurueck);
      }, (int) $me['id']);
      back('/intern/aufgaben');
    }
    item_forget('task', $taskNr);
    q('DELETE FROM task_assignees WHERE task_id = ?', [$taskNr]);
    q('DELETE FROM tasks WHERE id = ?', [$taskNr]);
    redirect('/intern/aufgaben');
  }
```

Note: `item_update()` reads the row before the callback and compares afterwards, so the mark still says "changed" only when `status` actually moved.

- [ ] **Step 2: Add the route checks to `bin/aufgaben-pruefen.php`**

The check calls the same statements the route calls rather than going through
HTTP: a POST would need the CSRF token, and reproducing the token logic in a
check script means maintaining a second copy of it. The HTTP layer is covered
by `bin/routen-pruefen.php` and by the browser step in Task 3.

Insert before the final `printf`:

```php
// -------------------- 8. Abhaken macht zustaendig, Zuruecknehmen loest es
q("INSERT INTO tasks (title, notes, due_date, status, created_by, required_done)
   VALUES ('ZZ Routenaufgabe', '', '', 'offen', ?, 0)", [(int) $A['id']]);
$t8 = (int) $GLOBALS['db']->lastInsertId();
// Was die Route tut, wenn B abhakt und niemand zustaendig war:
q('INSERT INTO task_assignees (task_id, user_id, done_at) VALUES (?,?,NOW(3))
   ON DUPLICATE KEY UPDATE done_at = NOW(3)', [$t8, (int) $B['id']]);
$pruefe('unzugewiesen abgehakt: erledigt', task_status_apply($t8) === 'erledigt');
$pruefe('unzugewiesen abgehakt: der Haken traegt einen Namen',
    (int) row('SELECT COUNT(*) n FROM task_assignees WHERE task_id = ? AND done_at IS NOT NULL',
              [$t8])['n'] === 1);
// Und wenn er ihn zuruecknimmt:
q('UPDATE task_assignees SET done_at = NULL WHERE task_id = ? AND user_id = ?', [$t8, (int) $B['id']]);
$pruefe('zurueckgenommen: wieder offen', task_status_apply($t8, true) === 'offen');
$weg($t8);
```

- [ ] **Step 3: Push, pull on staging, run both check scripts**

```bash
git add httpdocs/index.php bin/aufgaben-pruefen.php
git commit -m "Make ticking a task personal, and let ticking assign you (#334)"
git push origin tasks-assignees
```

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && php -l httpdocs/index.php && sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten && sudo -u www-data php bin/routen-pruefen.php /var/www/bandroadie-oss staging.beatcuisine.de 1 | tail -2'
```

Expected: `16 ok, 0 Fehler` and `24 Seiten, 0 Fehler`.

---

### Task 3: The form — several members, the number, and an edit route

**Files:**
- Modify: `httpdocs/index.php:1564-1581` — the GET and the create POST; add `/intern/aufgaben/{id}/update`
- Modify: `app/views/intern/aufgaben.php` — checkboxes instead of a single select, the number, per-task edit
- Modify: `app/strings/de.php:453-456` — new strings

**Interfaces:**
- Consumes: `task_assignees_map()`, `task_status_apply()` from Task 1
- Produces: `POST /intern/aufgaben/{id}/update` taking `title`, `notes`, `due_date`, `assignees[]`, `required_done`

- [ ] **Step 1: Add the strings**

In `app/strings/de.php`, replace the task block:

```php
  'task_title' => 'Aufgaben', 'task_lbl' => 'Aufgabe', 'task_ph' => 'z. B. PA für Stadtfest organisieren',
  'task_who' => 'Wer?', 'task_due' => 'Bis wann', 'task_details' => 'Details',
  'task_add' => 'Aufgabe anlegen', 'task_toggle' => 'Status wechseln',
  'task_none' => 'Keine Aufgaben — entweder sehr gut organisiert oder sehr entspannt. 🍹',
  'task_needed' => 'Wie viele müssen es tun?',
  'task_needed_all' => 'Alle Zuständigen',
  'task_progress' => '%1 von %2 erledigt',
  'task_done_by' => 'Erledigt von',
  'task_nobody' => 'Niemand zugewiesen — wer abhakt, ist zuständig',
  'task_edit' => 'Aufgabe ändern',
```

- [ ] **Step 2: Rewrite the GET route**

`httpdocs/index.php`, the `/intern/aufgaben` GET branch:

```php
  if ($path === '/intern/aufgaben' && $method === 'GET') {
    $taskOffen = items_unseen($me, 'task');
    $taskListe = rows("SELECT t.* FROM tasks t
                       ORDER BY t.status = 'erledigt', CASE WHEN t.due_date='' THEN 1 ELSE 0 END, t.due_date");
    view('intern/aufgaben', [
      'title' => t('task_title'),
      'tasks' => $taskListe,
      'assigneesByTask' => task_assignees_map(array_column($taskListe, 'id')),
      'members' => rows('SELECT id, name FROM users ORDER BY name'),
      'unseenTasks' => $taskOffen,
      'seenOnList' => ['task' => array_keys($taskOffen)],
    ]);
  }
```

The `LEFT JOIN users u ON u.id = t.assigned_to` and its `assignee` alias are gone: the column is no longer read, and the view now uses `assigneesByTask`.

- [ ] **Step 3: Rewrite the create POST and add the update route**

```php
  // Zuständige und die verlangte Zahl aus dem Formular — einmal hier, weil
  // Anlegen und Ändern dasselbe brauchen.
  $taskWer = static function () use ($me): array {
    $ids = array_values(array_unique(array_map('intval', (array) ($_POST['assignees'] ?? []))));
    // Nur echte Konten: Eine erfundene Nummer wäre ein Zuständiger, den es
    // nicht gibt, und die Aufgabe damit nie erledigbar.
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    return array_map('intval', array_column(
      rows("SELECT id FROM users WHERE id IN ($in)", $ids), 'id'));
  };
  $taskVerlangt = static fn(int $wieviele): int
    => max(0, min($wieviele, (int) ($_POST['required_done'] ?? 0)));

  if ($path === '/intern/aufgaben' && $method === 'POST') {
    if (($_POST['title'] ?? '') !== '') {
      $wer = $taskWer();
      q('INSERT INTO tasks (title, notes, due_date, created_by, required_done) VALUES (?,?,?,?,?)',
        [$_POST['title'], $_POST['notes'] ?? '', $_POST['due_date'] ?? '', $me['id'],
         $taskVerlangt(count($wer))]);
      $neu = (int) $db->lastInsertId();
      foreach ($wer as $uid) {
        q('INSERT INTO task_assignees (task_id, user_id) VALUES (?,?)', [$neu, $uid]);
      }
      item_new('task', $neu, (int) $me['id']);
    }
    redirect('/intern/aufgaben');
  }
  // Ändern gab es bisher nicht (#334): Ohne das ließen sich Zuständige und die
  // verlangte Zahl nach dem Anlegen nie mehr korrigieren.
  if (preg_match('~^/intern/aufgaben/(\d+)/update$~', $path, $m) && $method === 'POST') {
    if (!perm_allows($me, 'aufgaben', 'write')) { flash(t('fl_no_permission')); redirect('/intern/aufgaben'); }
    $taskNr = (int) $m[1];
    $wer = $taskWer();
    item_update('task', $taskNr, static function () use ($taskNr, $wer, $taskVerlangt): void {
      q('UPDATE tasks SET title = ?, notes = ?, due_date = ?, required_done = ? WHERE id = ?',
        [trim((string) ($_POST['title'] ?? '')), $_POST['notes'] ?? '', $_POST['due_date'] ?? '',
         $taskVerlangt(count($wer)), $taskNr]);
      // Häkchen der Bleibenden überleben; nur wer herausfällt, verliert seines.
      $in = $wer ? implode(',', array_fill(0, count($wer), '?')) : 'NULL';
      q("DELETE FROM task_assignees WHERE task_id = ? AND user_id NOT IN ($in)", [$taskNr, ...$wer]);
      foreach ($wer as $uid) {
        q('INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?,?)', [$taskNr, $uid]);
      }
    }, (int) $me['id']);
    // Ohne $darfOeffnen: Eine erledigte Aufgabe geht nicht auf, weil jemand
    // die Zuständigenliste angefasst hat.
    task_status_apply($taskNr);
    redirect('/intern/aufgaben');
  }
```

- [ ] **Step 4: Rewrite `app/views/intern/aufgaben.php`**

```php
<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('task_title')) ?></h1>

<?php
// Das Formular für Anlegen und Ändern ist dasselbe — einmal hier, damit eine
// neue Eingabe nicht an zwei Stellen nachgezogen werden muss.
$taskFormular = static function (array $members, ?array $task, array $wer): void {
  $gewaehlt = array_map(static fn(array $a): int => (int) $a['user_id'], $wer);
  ?>
  <label class="span2"><?= e(t('task_lbl')) ?>
    <input name="title" required placeholder="<?= e(t('task_ph')) ?>" value="<?= e($task['title'] ?? '') ?>">
  </label>
  <fieldset class="span2">
    <legend><?= e(t('task_who')) ?></legend>
    <?php foreach ($members as $mg): ?>
      <label class="inline-check">
        <input type="checkbox" name="assignees[]" value="<?= (int) $mg['id'] ?>"
               <?= in_array((int) $mg['id'], $gewaehlt, true) ? 'checked' : '' ?>>
        <?= e($mg['name']) ?>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <label><?= e(t('task_needed')) ?>
    <select name="required_done">
      <option value="0"><?= e(t('task_needed_all')) ?></option>
      <?php for ($i = 1; $i <= count($members); $i++): ?>
        <option value="<?= $i ?>" <?= (int) ($task['required_done'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label><?= e(t('task_due')) ?><input type="date" name="due_date" value="<?= e($task['due_date'] ?? '') ?>"></label>
  <label class="span2"><?= e(t('task_details')) ?><textarea name="notes" rows="2"><?= e($task['notes'] ?? '') ?></textarea></label>
  <?php
};
?>

<details class="card collapsible" <?= $tasks ? '' : 'open' ?>>
  <summary>➕ <?= e(t('task_add')) ?></summary>
  <form method="post" action="/intern/aufgaben" class="form-grid"><?= csrf_field() ?>
    <?php $taskFormular($members, null, []); ?>
    <button class="btn btn-primary span2"><?= e(t('task_add')) ?></button>
  </form>
</details>

<div class="card">
  <ul class="task-list">
    <?php foreach ($tasks as $task): ?>
      <?php
        $wer = $assigneesByTask[(int) $task['id']] ?? [];
        $fertig = array_values(array_filter($wer, static fn(array $a): bool => $a['done_at'] !== null));
        $noetig = $task['required_done'] > 0 ? min((int) $task['required_done'], count($wer)) : count($wer);
        $meinHaken = false;
        foreach ($wer as $a) {
          if ((int) $a['user_id'] === (int) $user['id'] && $a['done_at'] !== null) $meinHaken = true;
        }
      ?>
      <li class="<?= $task['status'] === 'erledigt' ? 'done' : '' ?>">
        <form class="inline" action="/intern/aufgaben/<?= (int) $task['id'] ?>/toggle" method="post"><?= csrf_field() ?>
          <button class="check" title="<?= e(t('task_toggle')) ?>"><?= $meinHaken ? '☑' : '☐' ?></button>
        </form>
        <strong><?= e($task['title']) ?></strong>
        <?= item_mark_html($unseenTasks ?? [], (int) $task['id']) ?>
        <?php if ($task['due_date']): ?><span class="muted"><?= e(t('due_until')) ?> <?= fmt_date($task['due_date']) ?></span><?php endif; ?>
        <?php if (!$wer): ?>
          <span class="muted small"><?= e(t('task_nobody')) ?></span>
        <?php else: ?>
          <span class="muted">→ <?= e(implode(', ', array_column($wer, 'name'))) ?></span>
          <?php // Die Zahl nur zeigen, wenn mehr als einer zuständig ist: Bei
                // einem sagt „1 von 1" nichts, was das Häkchen nicht schon sagt. ?>
          <?php if (count($wer) > 1): ?>
            <span class="badge"><?= e(str_replace(['%1', '%2'], [(string) count($fertig), (string) $noetig], t('task_progress'))) ?></span>
          <?php endif; ?>
          <?php if ($fertig): ?>
            <span class="muted small">✔ <?= e(t('task_done_by')) ?> <?= e(implode(', ', array_column($fertig, 'name'))) ?></span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($task['notes']): ?><div class="muted small prewrap"><?= e($task['notes']) ?></div><?php endif; ?>
        <?php if (perm_allows($user, 'aufgaben', 'write')): ?>
          <details class="subsection">
            <summary>✏️ <?= e(t('task_edit')) ?></summary>
            <form method="post" action="/intern/aufgaben/<?= (int) $task['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
              <?php $taskFormular($members, $task, $wer); ?>
              <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
            </form>
          </details>
        <?php endif; ?>
        <form class="inline" action="/intern/aufgaben/<?= (int) $task['id'] ?>/delete" method="post" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$tasks): ?><p class="muted center"><?= e(t('task_none')) ?></p><?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
```

- [ ] **Step 5: Push, pull, run the route sweep and look at the page**

```bash
git add httpdocs/index.php app/views/intern/aufgaben.php app/strings/de.php
git commit -m "Let a task be edited, and take several members with a required count (#334)"
git push origin tasks-assignees
```

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && php -l httpdocs/index.php && php -l app/views/intern/aufgaben.php && php -l app/strings/de.php && sudo -u www-data php bin/routen-pruefen.php /var/www/bandroadie-oss staging.beatcuisine.de 1 | tail -2 && sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten | tail -2'
```

Expected: `24 Seiten, 0 Fehler` and `16 ok, 0 Fehler`.

- [ ] **Step 6: Check the page in the browser**

The form, the checkboxes and the number are only real if they submit. Create a task with two assignees and `required_done = 1` through the browser pane at `https://staging.beatcuisine.de/intern/aufgaben`, tick it as one of them, and confirm the task shows as done and the other assignee's badge no longer counts it.

---

### Task 4: The badge, the overview, and `user_purge()`

**Files:**
- Modify: `app/bootstrap.php` — `open_items_count()` (around line 4102), `user_purge()` (around line 4138)
- Modify: `httpdocs/index.php:954-955` — the dashboard's task query
- Modify: `app/views/intern/dashboard.php:127` — the task loop
- Modify: `bin/aufgaben-pruefen.php` — the purge check

**Interfaces:**
- Consumes: `task_assignees_map()`, `task_status_apply()` from Task 1
- Produces: nothing new; existing signatures unchanged

- [ ] **Step 1: Add the failing check**

Insert before the final `printf` in `bin/aufgaben-pruefen.php`:

```php
// ------------------ 9. Zahl am Symbol und das Loeschen eines Kontos
$t9 = $aufgabe([$A, $B], 2);
$hake($t9, $A);
task_status_apply($t9);
$vorher = open_items_count($B);
$pruefe('offene Aufgabe zaehlt beim Zustaendigen', $vorher >= 1);
$hake($t9, $B);
task_status_apply($t9);
$pruefe('erledigte Aufgabe zaehlt nicht mehr', open_items_count($B) === $vorher - 1);
$weg($t9);

// Ein Konto faellt weg, waehrend eine Aufgabe auf ihm haengt.
$t10 = $aufgabe([$A, $B, $C], 0);
$hake($t10, $A);
$hake($t10, $B);
task_status_apply($t10);
$pruefe('drei zustaendig, zwei fertig: haengt', $stand($t10) === 'offen');
// Was user_purge() tun muss: Zeilen weg UND neu rechnen.
q('DELETE FROM task_assignees WHERE user_id = ?', [(int) $C['id']]);
task_status_apply($t10);
$pruefe('Konto weg: Aufgabe nicht mehr haengend', $stand($t10) === 'erledigt');
$weg($t10);
```

- [ ] **Step 2: Rewrite `open_items_count()`**

Replace the first line of the function body:

```php
function open_items_count(array $user): int {
  // Aufgaben, bei denen ich zuständig bin und die noch offen sind (#334). Weil
  // das Quorum die Aufgabe für alle schließt, bleibt es eine Abfrage — es gibt
  // keinen persönlichen Reststand, der davon abweichen könnte.
  $offen = (int) row("SELECT COUNT(*) c FROM tasks t
                      JOIN task_assignees ta ON ta.task_id = t.id
                      WHERE ta.user_id = ? AND t.status = 'offen'",
                     [(int) $user['id']])['c'];
  $chat = perm_allows($user, 'themen') ? array_sum(topic_unread($user)) : 0;
  return $offen + count(open_votes($user)) + $chat;
}
```

- [ ] **Step 3: Fix `user_purge()`**

The two existing lines are:

```php
  q('UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?', [$userId]);
  q('UPDATE tasks SET created_by = NULL WHERE created_by = ?', [$userId]);
```

Replace with:

```php
  // Die Zuständigkeiten gehen mit — und danach wird gerechnet (#334). Ohne den
  // zweiten Teil sitzt eine Aufgabe für immer bei „2 von 3", sobald das dritte
  // Konto weg ist, und niemand kann sie mehr abschließen.
  //
  // Die Aufgabennummern VOR dem Löschen holen; danach ist nicht mehr zu sehen,
  // welche betroffen waren.
  $betroffen = array_column(rows('SELECT task_id FROM task_assignees WHERE user_id = ?', [$userId]), 'task_id');
  q('DELETE FROM task_assignees WHERE user_id = ?', [$userId]);
  foreach ($betroffen as $taskNr) task_status_apply((int) $taskNr);
  q('UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?', [$userId]);
  q('UPDATE tasks SET created_by = NULL WHERE created_by = ?', [$userId]);
```

`assigned_to` is still cleared although nothing reads it: leaving a deleted member's id in a column is the kind of remnant that surprises whoever does read it next.

- [ ] **Step 4: Fix the dashboard**

`httpdocs/index.php`, the `'tasks' =>` line in the dashboard route — the `assignee` alias is gone, so the query and the view need the map:

```php
      'tasks' => perm_allows($me, 'aufgaben') ? rows("SELECT t.* FROM tasks t
                       WHERE t.status='offen' ORDER BY CASE WHEN t.due_date='' THEN 1 ELSE 0 END, t.due_date LIMIT 8") : [],
      'taskAssignees' => perm_allows($me, 'aufgaben')
        ? task_assignees_map(array_column(rows("SELECT id FROM tasks WHERE status='offen'"), 'id'))
        : [],
```

In `app/views/intern/dashboard.php`, inside the task loop around line 127, replace any use of `$task['assignee']` with:

```php
<?php $dWer = $taskAssignees[(int) $task['id']] ?? []; ?>
<?php if ($dWer): ?><span class="muted">→ <?= e(implode(', ', array_column($dWer, 'name'))) ?></span><?php endif; ?>
```

Read the file first and match the surrounding markup; do not paste this blind.

- [ ] **Step 5: Push and verify**

```bash
git add app/bootstrap.php httpdocs/index.php app/views/intern/dashboard.php bin/aufgaben-pruefen.php
git commit -m "Count a task for its assignees, and never leave one stuck at two of three (#334)"
git push origin tasks-assignees
```

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && php -l app/bootstrap.php && php -l httpdocs/index.php && php -l app/views/intern/dashboard.php && sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten | tail -2 && sudo -u www-data php bin/routen-pruefen.php /var/www/bandroadie-oss staging.beatcuisine.de 1 | tail -2'
```

Expected: `21 ok, 0 Fehler` and `24 Seiten, 0 Fehler`.

---

### Task 5: One visibility function, two callers

**Files:**
- Modify: `app/marks.php` — new function `item_visible()`
- Modify: `httpdocs/index.php:2315-2345` — the `/intern/gesehen` route uses it
- Modify: `bin/marken-pruefen.php` — the wiring check now checks the function, not the source text

**Interfaces:**
- Consumes: `may_see_event()`, `may_see_song()`, `may_see_setlist()`, `may_see_contract()`, `may_see_file()`, `may_see_finance()`, `may_see_topic()`, `perm_allows()` from `app/bootstrap.php`
- Produces: `item_visible(?array $user, string $kind, int $id): bool` — true only for a kind it knows and an entry that user may see. Unknown kind → false.

- [ ] **Step 1: Write `item_visible()` in `app/marks.php`**

Append after `item_mark_html()`:

```php
/**
 * Darf dieses Konto diesen Eintrag sehen? Eine Frage, eine Antwort, für jede
 * Sorte (#335).
 *
 * Die Prüfungen standen als anonyme Funktionen mitten in der Route
 * /intern/gesehen. Die Verknüpfungen einer Aufgabe brauchen genau dieselben —
 * eine verknüpfte Aufgabe darf nicht verraten, dass es einen Termin gibt, den
 * man nicht sehen darf. Zwei Kopien einer Zugriffsregel ist eine zu viel; die
 * zweite ist immer die, die nicht mitgepflegt wird.
 *
 * 'topic' steht bewusst nicht in ITEM_KINDS (der Chat zählt je Beitrag, nicht
 * je Eintrag), ist hier aber verknüpfbar und braucht deshalb seine Zeile.
 *
 * Unbekannte Sorte heißt nein. Standardmäßig zu: Was hier fehlt, wird nicht
 * angezeigt, statt stillschweigend durchzugehen.
 */
function item_visible(?array $user, string $kind, int $id): bool {
  if (!$user || $id <= 0) return false;
  return match ($kind) {
    'event'      => may_see_event($user, $id),
    'song'       => may_see_song($user, $id),
    'setlist'    => may_see_setlist($user, $id),
    'quote'      => perm_allows($user, 'angebote'),
    'contract'   => may_see_contract($user, $id),
    'file'       => ($f = row('SELECT * FROM files WHERE id = ?', [$id])) && may_see_file($user, $f),
    // Kommentar und Zusage sind so sichtbar wie ihr Termin — eine eigene Regel
    // gibt es nicht, und eine zweite wäre die nächste, die auseinanderläuft.
    'comment'    => ($k = row('SELECT event_id FROM comments WHERE id = ?', [$id]))
                    && may_see_event($user, (int) $k['event_id']),
    'attendance' => ($z = row('SELECT event_id FROM attendance WHERE id = ?', [$id]))
                    && may_see_event($user, (int) $z['event_id']),
    // Private Auslagen gehören dem Mitglied, nicht dem Bereich.
    'finance'    => may_see_finance($user, $id),
    'topic'      => may_see_topic($user, $id),
    // Der Rest sind ganze Bereiche: Wer den Bereich sehen darf, sieht jeden
    // Eintrag darin — genau wie die Liste selbst es hält.
    'venue'      => perm_allows($user, 'orte'),
    'absence'    => perm_allows($user, 'abwesenheiten'),
    'task'       => perm_allows($user, 'aufgaben'),
    'equipment'  => perm_allows($user, 'equipment'),
    'guest'      => perm_allows($user, 'gaeste'),
    'photo'      => perm_allows($user, 'fotos'),
    'media'      => perm_allows($user, 'musik'),
    'stageitem'  => perm_allows($user, 'rider'),
    'channel'    => perm_allows($user, 'rider'),
    'post'       => perm_allows($user, 'post'),
    default      => false,
  };
}
```

- [ ] **Step 2: Replace the closures in `/intern/gesehen`**

The whole `$gPruefung = [ … ][$gArt] ?? null;` block goes. The route becomes:

```php
  if ($path === '/intern/gesehen' && $method === 'POST') {
    $gArt = (string) ($_POST['art'] ?? '');
    $gNr = (int) ($_POST['nr'] ?? 0);
    header('Content-Type: application/json');
    // Eine unbekannte Sorte wird abgelehnt statt still geschluckt: Welche
    // Sorten es gibt, ist kein Geheimnis — geheim ist nur, welche Nummern
    // jemand sehen darf. Ein stilles „ok" kostete den Nächsten, der data-seen
    // benutzt, einen Tag.
    if (!isset(ITEM_KINDS[$gArt]) || !$gNr) { http_response_code(400); exit(json_encode(['ok' => false])); }
    if (item_visible($me, $gArt, $gNr)) {
      item_mark_seen($me, $gArt, $gNr);
      // … die bestehenden Kaskaden für 'event' und 'equipment' bleiben unverändert …
    }
    exit(json_encode(['ok' => true]));
  }
```

Keep the two cascade blocks (`comment`/`attendance` with an event, descendants with a piece of equipment) exactly as they are — only the visibility lookup changes.

- [ ] **Step 3: Update the wiring check in `bin/marken-pruefen.php`**

The line that greps the source is now wrong, because the map no longer exists:

```php
    $pruefe("$sorte: /intern/gesehen kennt sie", str_contains($quelle, "'$sorte' => fn(int \$nr)"));
```

Replace it with a check that tests behaviour instead of text. The trap to avoid:
a missing `match` arm falls into `default` and returns `false` — and so does a
wired kind asked about an id that does not exist. So asking about a made-up id
proves nothing. The check that bites uses a **real row and an admin account**,
which holds every permission: a wired kind must answer `true`. Kinds with no
row in this database cannot be tested that way and are reported as skipped
rather than silently passed.

Also delete the now-unused `$quelle = file_get_contents(...)` line above the
loop.

```php
// Jede Sorte in item_visible(): Ein Admin darf alles sehen, also muss eine
// vorhandene Nummer true ergeben. Ein fehlender match-Arm fällt in den default
// und liefert false — genau das findet diese Prüfung (#335).
$adminKonto = row("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
foreach (array_merge(array_keys(ITEM_KINDS), ['topic']) as $sorte) {
    $tab = $sorte === 'topic' ? 'topics' : ITEM_KINDS[$sorte]['tabelle'];
    $eine = row("SELECT id FROM `$tab` ORDER BY id LIMIT 1");
    if (!$eine) { printf("%-56s %s%s", "$sorte: item_visible() (keine Zeile da)", 'uebersprungen', PHP_EOL); continue; }
    $pruefe("$sorte: item_visible() sagt dem Admin ja",
        item_visible($adminKonto, $sorte, (int) $eine['id']) === true);
}
$pruefe('item_visible() lehnt eine unbekannte Sorte ab',
    item_visible($adminKonto, 'gibtsnicht', 1) === false);
```

- [ ] **Step 4: Push and verify both check scripts**

```bash
git add app/marks.php httpdocs/index.php bin/marken-pruefen.php
git commit -m "Ask one function whether an entry may be seen (#335)"
git push origin tasks-assignees
```

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && php -l app/marks.php && php -l httpdocs/index.php && sudo -u www-data php bin/marken-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten | tail -3 && sudo -u www-data php bin/routen-pruefen.php /var/www/bandroadie-oss staging.beatcuisine.de 1 | tail -2'
```

Expected: no failures, and the mark-clearing still works — unfold an event card in the browser and confirm the mark goes away, because this task rewired exactly that path.

---

### Task 6: Links from a task

**Files:**
- Modify: `app/schema.php` — `task_links` table
- Modify: `app/bootstrap.php` — `task_links_map()`, `item_label()`, `item_url()`
- Modify: `httpdocs/index.php` — the create and update routes store links; the GET passes them
- Modify: `app/views/intern/aufgaben.php` — the link picker and the rendered links
- Modify: `app/strings/de.php` — strings, and a label per kind
- Modify: `bin/aufgaben-pruefen.php` — the link checks

**Interfaces:**
- Consumes: `item_visible()` from Task 5, `ITEM_KINDS` from `app/marks.php`
- Produces: `task_links_map(array $taskIds, ?array $user): array` — per task id, a list of `['kind' => …, 'item_id' => …, 'label' => …, 'url' => …]`, already filtered by visibility and with unresolvable entries dropped. `item_label(string $kind, int $id): string` returns `''` when the entry is gone. `item_url(string $kind, int $id): string`.

- [ ] **Step 1: Add the failing checks**

Insert before the final `printf` in `bin/aufgaben-pruefen.php`:

```php
// ------------------------------------------- 11. Verknuepfungen
q("INSERT INTO events (type, title, date, status) VALUES ('probe','ZZ Verknuepfung','2027-04-04','bestaetigt')");
$evV = (int) $GLOBALS['db']->lastInsertId();
$t11 = $aufgabe([$A], 0);
q('INSERT INTO task_links (task_id, kind, item_id) VALUES (?,?,?)', [$t11, 'event', $evV]);
$karte = task_links_map([$t11], $A);
$pruefe('Verknuepfung wird gefunden', count($karte[$t11] ?? []) === 1);
$pruefe('Verknuepfung nennt den Titel',
    str_contains((string) ($karte[$t11][0]['label'] ?? ''), 'ZZ Verknuepfung'));
$pruefe('Verknuepfung hat eine Adresse',
    str_starts_with((string) ($karte[$t11][0]['url'] ?? ''), '/intern/'));

// Zeigt sie auf etwas Geloeschtes, wird sie uebersprungen statt zu scheitern.
q('DELETE FROM events WHERE id = ?', [$evV]);
$pruefe('verwaiste Verknuepfung wird uebersprungen', (task_links_map([$t11], $A)[$t11] ?? []) === []);

// Unbekannte Sorte gar nicht erst annehmen.
$pruefe('unbekannte Sorte ist keine Sorte', !isset(ITEM_KINDS['gibtsnicht']));
q('DELETE FROM task_links WHERE task_id = ?', [$t11]);
$weg($t11);
```

- [ ] **Step 2: Add the table**

In `app/schema.php`, next to `task_assignees`:

```php
  // Woran eine Aufgabe hängt (#335). kind wird gegen ITEM_KINDS geprüft, plus
  // 'topic' — damit ist „und was sonst noch sinnvoll ist" keine Liste, die
  // jemand raten muss, sondern eine Tabelle.
  "CREATE TABLE IF NOT EXISTS task_links (
    task_id INT NOT NULL,
    kind VARCHAR(20) NOT NULL,
    item_id INT NOT NULL,
    PRIMARY KEY (task_id, kind, item_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
```

Add `task_links` to the demo reset order in `app/demo.php` before `tasks`.

- [ ] **Step 3: Add the three helpers to `app/bootstrap.php`**

Place them after `task_assignees_map()`:

```php
/**
 * Wie ein Eintrag heißt, wenn man ihn in einer Liste nennen will (#335).
 *
 * Leerer Rückgabewert heißt „gibt es nicht mehr". Verknüpfungen werden
 * bewusst beim Lesen übersprungen statt beim Löschen aufgeräumt: Der andere
 * Weg wären neunzehn Löschstellen, von denen man eine vergisst — und eine
 * vergessene zeigt irgendwann auf eine neu vergebene Nummer, also auf das
 * Falsche statt auf nichts.
 */
function item_label(string $kind, int $id): string {
  $eine = static fn(string $sql): ?array => row($sql, [$id]);
  return match ($kind) {
    'event'      => ($r = $eine('SELECT title, date FROM events WHERE id = ?'))
                    ? $r['title'] . ' · ' . fmt_date($r['date']) : '',
    'song'       => ($r = $eine('SELECT title FROM songs WHERE id = ?')) ? $r['title'] : '',
    'setlist'    => ($r = $eine('SELECT name FROM setlists WHERE id = ?')) ? $r['name'] : '',
    'quote'      => ($r = $eine('SELECT title FROM quotes WHERE id = ?')) ? $r['title'] : '',
    'contract'   => ($r = $eine('SELECT contract_no FROM contracts WHERE id = ?')) ? $r['contract_no'] : '',
    'file'       => ($r = $eine('SELECT original_name FROM files WHERE id = ?')) ? $r['original_name'] : '',
    'venue'      => ($r = $eine('SELECT name FROM venues WHERE id = ?')) ? $r['name'] : '',
    'task'       => ($r = $eine('SELECT title FROM tasks WHERE id = ?')) ? $r['title'] : '',
    'equipment'  => ($r = $eine('SELECT name FROM equipment WHERE id = ?')) ? $r['name'] : '',
    'guest'      => ($r = $eine('SELECT name FROM guests WHERE id = ?')) ? $r['name'] : '',
    'topic'      => ($r = $eine('SELECT title FROM topics WHERE id = ?')) ? $r['title'] : '',
    'media'      => ($r = $eine('SELECT title, url FROM media_links WHERE id = ?'))
                    ? ($r['title'] ?: $r['url']) : '',
    'post'       => ($r = $eine('SELECT subject FROM post_messages WHERE id = ?'))
                    ? ($r['subject'] ?: t('post_title')) : '',
    'finance'    => ($r = $eine('SELECT description, amount_cents FROM finances WHERE id = ?'))
                    ? $r['description'] . ' · ' . fmt_money((int) $r['amount_cents']) : '',
    'photo'      => ($r = $eine('SELECT caption, filename FROM photos WHERE id = ?'))
                    ? ($r['caption'] ?: $r['filename']) : '',
    'channel'    => ($r = $eine('SELECT number, name FROM channels WHERE id = ?'))
                    ? $r['number'] . ' · ' . $r['name'] : '',
    'stageitem'  => ($r = $eine('SELECT label FROM stage_items WHERE id = ?')) ? $r['label'] : '',
    'absence'    => ($r = $eine('SELECT date_from, date_to FROM absences WHERE id = ?'))
                    ? fmt_date($r['date_from']) . ' – ' . fmt_date($r['date_to']) : '',
    'comment'    => ($r = $eine('SELECT text FROM comments WHERE id = ?'))
                    ? mb_substr($r['text'], 0, 60) : '',
    'attendance' => ($r = row('SELECT u.name FROM attendance a JOIN users u ON u.id = a.user_id
                               WHERE a.id = ?', [$id])) ? $r['name'] : '',
    default      => '',
  };
}

/**
 * Wohin ein Eintrag führt. Bereiche ohne Einzelseite führen auf ihre Liste —
 * dort steht der Eintrag vollständig, mehr gibt es nicht zu zeigen.
 */
function item_url(string $kind, int $id): string {
  return match ($kind) {
    'event', 'comment', 'attendance' => '/intern/termine#ev' . $id,
    'song'      => '/intern/songs/' . $id,
    'setlist'   => '/intern/setlists',
    'quote'     => '/intern/angebote',
    'contract'  => '/intern/vertraege',
    'file'      => '/intern/dateien',
    'venue'     => '/intern/orte',
    'absence'   => '/intern/abwesenheiten',
    'task'      => '/intern/aufgaben',
    'finance'   => '/intern/kasse',
    'equipment' => '/intern/equipment',
    'guest'     => '/intern/gaeste',
    'photo'     => '/intern/fotos',
    'media'     => '/intern/musik',
    'stageitem' => '/intern/stagerider',
    'channel'   => '/intern/kanaele',
    'post'      => '/intern/post/' . $id,
    'topic'     => '/intern/themen/' . $id,
    default     => '/intern',
  };
}

/**
 * Die Verknüpfungen mehrerer Aufgaben, fertig zum Anzeigen (#335).
 *
 * Gefiltert wird hier und nicht in der Ansicht: item_visible() ist dieselbe
 * Prüfung, die auch die Marken benutzen — eine Aufgabe darf nicht verraten,
 * dass es einen Termin gibt, den man nicht sehen darf.
 */
function task_links_map(array $taskIds, ?array $user): array {
  if (!$taskIds) return [];
  $in = implode(',', array_map('intval', $taskIds));
  $karte = [];
  foreach (rows("SELECT task_id, kind, item_id FROM task_links WHERE task_id IN ($in)") as $r) {
    $kind = (string) $r['kind'];
    $nr = (int) $r['item_id'];
    if (!item_visible($user, $kind, $nr)) continue;
    $text = item_label($kind, $nr);
    if ($text === '') continue; // gelöscht — überspringen, nicht scheitern
    $karte[(int) $r['task_id']][] = [
      'kind' => $kind, 'item_id' => $nr, 'label' => $text, 'url' => item_url($kind, $nr),
    ];
  }
  return $karte;
}
```

`event_url()` already exists and builds a link that carries the year filter, so
the card is actually on the page it lands on. Use it for `'event'` rather than
the anchor form, and keep the anchor only as the fallback when the row is gone:

```php
    'event', 'comment', 'attendance' => ($ev = row('SELECT id, date, status FROM events WHERE id = ?',
                                                   [$kind === 'event' ? $id : 0]))
                                        ? event_url($ev) : '/intern/termine#ev' . $id,
```

For `'comment'` and `'attendance'` the id belongs to the comment or the
attendance row, not the event, so resolve the event first:

```php
function item_url(string $kind, int $id): string {
  // Kommentar und Zusage führen auf ihren Termin, nicht auf sich selbst — sie
  // haben keine eigene Seite, sie stehen in der Terminkarte.
  if ($kind === 'comment' || $kind === 'attendance') {
    $tab = $kind === 'comment' ? 'comments' : 'attendance';
    $r = row("SELECT event_id FROM `$tab` WHERE id = ?", [$id]);
    return $r ? item_url('event', (int) $r['event_id']) : '/intern/termine';
  }
  if ($kind === 'event') {
    $ev = row('SELECT id, date, status FROM events WHERE id = ?', [$id]);
    return $ev ? event_url($ev) : '/intern/termine';
  }
  return match ($kind) {
```

and drop the `'event', 'comment', 'attendance'` arm from the `match`.

- [ ] **Step 4: Strings for the kind names**

In `app/strings/de.php`, add a block near the task strings:

```php
  'task_links' => 'Gehört zu',
  'task_link_add' => 'Verknüpfung hinzufügen',
  'task_link_kind' => 'Was?',
  'task_link_item' => 'Welcher Eintrag?',
  'task_link_remove' => 'Verknüpfung entfernen',
  'itemkind_event' => 'Termin', 'itemkind_song' => 'Lied', 'itemkind_setlist' => 'Setliste',
  'itemkind_quote' => 'Angebot', 'itemkind_contract' => 'Vertrag', 'itemkind_file' => 'Datei',
  'itemkind_comment' => 'Kommentar', 'itemkind_attendance' => 'Zusage',
  'itemkind_venue' => 'Ort', 'itemkind_absence' => 'Abwesenheit', 'itemkind_task' => 'Aufgabe',
  'itemkind_finance' => 'Kassenbuchung', 'itemkind_equipment' => 'Equipment',
  'itemkind_guest' => 'Gast', 'itemkind_photo' => 'Foto', 'itemkind_media' => 'Musik/Video',
  'itemkind_stageitem' => 'Stagerider', 'itemkind_channel' => 'Kanal',
  'itemkind_post' => 'Nachricht', 'itemkind_topic' => 'Thema',
```

- [ ] **Step 5: Store links in the create and update routes**

Add next to `$taskWer` in `httpdocs/index.php`:

```php
  // Verknüpfungen aus dem Formular: Paare "sorte:nummer" (#335). Eine Sorte,
  // die es nicht gibt, und ein Eintrag, den der Absender nicht sehen darf,
  // werden verworfen — sonst ließe sich über das Formular erfragen, was es
  // sonst wo gibt.
  $taskLinks = static function (int $taskNr) use ($me): void {
    q('DELETE FROM task_links WHERE task_id = ?', [$taskNr]);
    foreach ((array) ($_POST['links'] ?? []) as $paar) {
      [$kind, $nr] = array_pad(explode(':', (string) $paar, 2), 2, '');
      $nr = (int) $nr;
      $erlaubt = isset(ITEM_KINDS[$kind]) || $kind === 'topic';
      if (!$erlaubt || $nr <= 0 || !item_visible($me, $kind, $nr)) continue;
      q('INSERT IGNORE INTO task_links (task_id, kind, item_id) VALUES (?,?,?)', [$taskNr, $kind, $nr]);
    }
  };
```

Call `$taskLinks($neu);` after `item_new('task', …)` in the create route, and `$taskLinks($taskNr);` inside the update route's callback.

Add the links to the delete branch: `q('DELETE FROM task_links WHERE task_id = ?', [$taskNr]);` next to the `task_assignees` delete.

Pass them to the view — in the GET route:

```php
      'linksByTask' => task_links_map(array_column($taskListe, 'id'), $me),
      'linkable' => task_linkable($me),
```

- [ ] **Step 6: The picker's options**

Add to `app/bootstrap.php`, after `task_links_map()`:

```php
/**
 * Was sich verknüpfen lässt, nach Sorte gruppiert (#335) — für die Auswahl im
 * Formular.
 *
 * Nur Termine, Lieder, Setlisten und Themen stehen zur Auswahl: Das sind die,
 * an denen Aufgaben wirklich hängen, und eine Auswahlliste mit neunzehn
 * Gruppen und tausend Zeilen benutzt niemand. Verknüpfungen auf andere Sorten
 * bleiben gültig und werden angezeigt — sie entstehen nur nicht hier.
 */
function task_linkable(?array $user): array {
  $gruppen = [];
  if (perm_allows($user, 'termine')) {
    foreach (rows("SELECT id, title, date FROM events ORDER BY date DESC LIMIT 100") as $e) {
      if (!may_see_event($user, (int) $e['id'])) continue;
      $gruppen['event'][] = ['id' => (int) $e['id'], 'label' => $e['title'] . ' · ' . fmt_date($e['date'])];
    }
  }
  if (perm_allows($user, 'songs')) {
    foreach (rows('SELECT id, title FROM songs ORDER BY title') as $s) {
      if (!may_see_song($user, (int) $s['id'])) continue;
      $gruppen['song'][] = ['id' => (int) $s['id'], 'label' => $s['title']];
    }
  }
  if (perm_allows($user, 'setlists')) {
    foreach (rows('SELECT id, name FROM setlists ORDER BY name') as $s) {
      if (!may_see_setlist($user, (int) $s['id'])) continue;
      $gruppen['setlist'][] = ['id' => (int) $s['id'], 'label' => $s['name']];
    }
  }
  if (perm_allows($user, 'themen')) {
    foreach (rows('SELECT id, title FROM topics ORDER BY title') as $th) {
      if (!may_see_topic($user, (int) $th['id'])) continue;
      $gruppen['topic'][] = ['id' => (int) $th['id'], 'label' => $th['title']];
    }
  }
  return $gruppen;
}
```

The `LIMIT 100` on events is deliberate: a band with ten years of history would
otherwise put a thousand rows into one select, and nobody links a task to a gig
from 2019. Songs, setlists and topics stay unlimited — they are counted in
dozens, not hundreds.

**Order note:** this helper is used by Step 5 and defined here. Write this step
before running anything from Step 5, or the route calls a function that does not
exist yet.

- [ ] **Step 7: The view**

Inside `$taskFormular`, after the notes field:

```php
  <label class="span2"><?= e(t('task_links')) ?>
    <select name="links[]" multiple size="6">
      <?php $schon = array_map(static fn(array $l): string => $l['kind'] . ':' . $l['item_id'], $verknuepft); ?>
      <?php foreach ($linkable as $sorte => $eintraege): ?>
        <optgroup label="<?= e(t('itemkind_' . $sorte)) ?>">
          <?php foreach ($eintraege as $eintrag): ?>
            <?php $wert = $sorte . ':' . $eintrag['id']; ?>
            <option value="<?= e($wert) ?>" <?= in_array($wert, $schon, true) ? 'selected' : '' ?>><?= e($eintrag['label']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
    <span class="muted small"><?= e(t('task_link_add')) ?></span>
  </label>
```

`$taskFormular` gains two parameters: `array $linkable, array $verknuepft`. Update both call sites — the create form passes `$linkable, []`, the edit form passes `$linkable, $linksByTask[(int) $task['id']] ?? []`.

In the list, after the assignees:

```php
        <?php $verk = $linksByTask[(int) $task['id']] ?? []; ?>
        <?php if ($verk): ?>
          <div class="muted small">
            <?= e(t('task_links')) ?>:
            <?php foreach ($verk as $l): ?>
              <a class="badge" href="<?= e($l['url']) ?>"><?= e(t('itemkind_' . $l['kind'])) ?>: <?= e($l['label']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
```

- [ ] **Step 8: Push and verify**

```bash
git add app/schema.php app/bootstrap.php app/demo.php httpdocs/index.php app/views/intern/aufgaben.php app/strings/de.php bin/aufgaben-pruefen.php
git commit -m "Let a task point at an event, a song, a setlist or a topic (#335)"
git push origin tasks-assignees
```

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && for f in app/schema.php app/bootstrap.php httpdocs/index.php app/views/intern/aufgaben.php app/strings/de.php bin/aufgaben-pruefen.php; do php -l $f | grep -v "^No syntax"; done; curl -sk --resolve staging.beatcuisine.de:443:127.0.0.1 -o /dev/null -w "%{http_code}\n" https://staging.beatcuisine.de/ && sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten | tail -2 && sudo -u www-data php bin/routen-pruefen.php /var/www/bandroadie-oss staging.beatcuisine.de 1 | tail -2'
```

Expected: `25 ok, 0 Fehler` and `24 Seiten, 0 Fehler`.

- [ ] **Step 9: Check a restricted account sees nothing extra**

A stand-in (`role = 'ersatz'`) sees only the events on their own setlists. Link a task to an event such an account may not see, sign in as them on staging, and confirm the task shows without the link — not with a link they cannot follow, and not with an error.

---

### Task 7: The other direction, on events and songs

**Files:**
- Modify: `app/bootstrap.php` — `tasks_for_item()`
- Modify: `app/bootstrap.php` — the event list data builder (the `return [` block around line 4600) passes it
- Modify: `httpdocs/index.php` — the single-song route passes it
- Modify: `app/views/intern/_event_card.php` — a section for the tasks
- Modify: `app/views/intern/song.php` — the same
- Modify: `app/strings/de.php` — one string
- Modify: `bin/aufgaben-pruefen.php` — the reverse-direction check

**Interfaces:**
- Consumes: `task_assignees_map()` from Task 1
- Produces: `tasks_for_item(string $kind, array $itemIds): array` — per item id, a list of open tasks with `id`, `title`, `status`, and the assignee names

- [ ] **Step 1: Add the failing check**

```php
// -------------------- 12. Die Gegenrichtung: Aufgaben an einem Termin
q("INSERT INTO events (type, title, date, status) VALUES ('probe','ZZ Gegenrichtung','2027-05-05','bestaetigt')");
$evG = (int) $GLOBALS['db']->lastInsertId();
$t12 = $aufgabe([$A], 0);
q('INSERT INTO task_links (task_id, kind, item_id) VALUES (?,?,?)', [$t12, 'event', $evG]);
$amTermin = tasks_for_item('event', [$evG]);
$pruefe('Termin kennt seine offene Aufgabe', count($amTermin[$evG] ?? []) === 1);
$hake($t12, $A);
task_status_apply($t12);
$pruefe('erledigte Aufgabe steht nicht mehr am Termin',
    (tasks_for_item('event', [$evG])[$evG] ?? []) === []);
q('DELETE FROM task_links WHERE task_id = ?', [$t12]);
$weg($t12);
q('DELETE FROM events WHERE id = ?', [$evG]);
```

- [ ] **Step 2: Add `tasks_for_item()` to `app/bootstrap.php`**

```php
/**
 * Die offenen Aufgaben, die an diesen Einträgen hängen (#336).
 *
 * Nur offene: Eine erledigte Aufgabe an einem Termin ist Geschichte und gehört
 * nicht in den Kartenkopf, den man vor dem Auftritt liest.
 *
 * Die Aufgabe selbst wird nicht auf Sichtbarkeit geprüft — wer den Termin
 * sieht, sieht auch, was daran noch zu tun ist. Wer den Aufgabenbereich nicht
 * darf, bekommt die Abfrage gar nicht: Das entscheidet der Aufrufer mit
 * perm_allows(), wie überall sonst.
 */
function tasks_for_item(string $kind, array $itemIds): array {
  if (!$itemIds) return [];
  $in = implode(',', array_map('intval', $itemIds));
  $zeilen = rows("SELECT tl.item_id, t.id, t.title, t.due_date, t.required_done
                  FROM task_links tl JOIN tasks t ON t.id = tl.task_id
                  WHERE tl.kind = ? AND tl.item_id IN ($in) AND t.status = 'offen'
                  ORDER BY CASE WHEN t.due_date='' THEN 1 ELSE 0 END, t.due_date", [$kind]);
  if (!$zeilen) return [];
  $wer = task_assignees_map(array_column($zeilen, 'id'));
  $karte = [];
  foreach ($zeilen as $z) {
    $z['assignees'] = $wer[(int) $z['id']] ?? [];
    $karte[(int) $z['item_id']][] = $z;
  }
  return $karte;
}
```

- [ ] **Step 3: Pass it into the event list and the song page**

In `app/bootstrap.php`, the event list `return [` block — add:

```php
    // Was an diesem Termin noch zu tun ist (#336). Nur wer Aufgaben sehen
    // darf, bekommt die Abfrage überhaupt.
    'tasksByEvent' => perm_allows($me, 'aufgaben') ? $ohne(tasks_for_item('event', $ids)) : [],
```

In `httpdocs/index.php`, the single-song GET route (`/intern/songs/{id}`) — add to the `view()` array:

```php
      'songTasks' => perm_allows($me, 'aufgaben')
        ? (tasks_for_item('song', [(int) $songOne['id']])[(int) $songOne['id']] ?? []) : [],
```

- [ ] **Step 4: Show them**

New string in `app/strings/de.php`: `'item_open_tasks' => 'Noch zu tun',`

In `app/views/intern/_event_card.php`, after the gear/conflict block:

```php
    <?php $evAufgaben = $tasksByEvent[$ev['id']] ?? []; ?>
    <?php if ($evAufgaben): ?>
      <p class="muted small">✅ <?= e(t('item_open_tasks')) ?>:
        <?php foreach ($evAufgaben as $ta): ?>
          <a class="badge" href="/intern/aufgaben"><?= e($ta['title']) ?><?php
            if ($ta['assignees']): ?> · <?= e(implode(', ', array_column($ta['assignees'], 'name'))) ?><?php endif; ?></a>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
```

The same block in `app/views/intern/song.php`, with `$songTasks` instead of `$evAufgaben`. Read that file first and place it where the other metadata sits.

- [ ] **Step 5: Push and verify**

```bash
git add app/bootstrap.php httpdocs/index.php app/views/intern/_event_card.php app/views/intern/song.php app/strings/de.php bin/aufgaben-pruefen.php
git commit -m "Show an event and a song what is still to be done on them (#336)"
git push origin tasks-assignees
```

```bash
ssh -i /c/Users/computi.lotr/.ssh/bandroadie_deploy deploy@192.168.10.13 'cd /var/www/bandroadie-oss && git pull --quiet --ff-only && for f in app/bootstrap.php httpdocs/index.php app/views/intern/_event_card.php app/views/intern/song.php app/strings/de.php; do php -l $f | grep -v "^No syntax"; done; sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten | tail -2 && sudo -u www-data php bin/routen-pruefen.php /var/www/bandroadie-oss staging.beatcuisine.de 1 | tail -2'
```

Expected: `27 ok, 0 Fehler` and `24 Seiten, 0 Fehler`.

---

### Task 8: The help page, the rights matrix, and the release

**Files:**
- Modify: `app/strings/de.php` — the task help text
- Modify: `docs/superpowers/specs/2026-09-23-tasks-assignees-and-links-design.md` — what changed while building
- Modify: `VERSION` — at the merge, not before

- [ ] **Step 1: Find and correct the task help text**

```bash
grep -n "help_.*task\|help_aufgaben" app/strings/de.php
```

The existing text describes one assignee and a shared tick. Rewrite it to say: several members can be assigned, a number says how many have to do it, the tick is personal, the task is done for everyone once the number is reached, an unassigned task makes whoever ticks it the assignee, and a task can point at events, songs, setlists and topics — which then show it in return.

- [ ] **Step 2: Check the rights matrix**

The new route is `/intern/aufgaben/{id}/update`. Confirm three things and fix what fails:

- It is covered by `PERM_MODULES['aufgaben']` (the path prefix `/intern/aufgaben` already is), so a member without the area cannot reach it.
- It checks `perm_allows($me, 'aufgaben', 'write')` — written in Task 3, verify it is there.
- The demo is not blocked from it: tasks are not demo-locked today, so nothing to add. Confirm by ticking a task on `demo.bandregie.info` after the rollout.

- [ ] **Step 3: Record what the build changed**

Append a "What changed while building it" section to the spec, the way
`2026-09-23-marks-everywhere.md` does, naming anything that turned out
differently from the design.

- [ ] **Step 4: Merge, bump, tag, release**

Write the release notes to a file first — narrative English, saying what the
band gets and why it works that way, ending with `Closes #334`, `Closes #335`
and `Closes #336`. `--target` rejects a short SHA, hence `git rev-parse HEAD`.

```bash
git checkout main && git pull --ff-only
git merge --no-ff tasks-assignees -m "Tasks: several assignees, a quorum, and links (#334, #335, #336)"
echo "2.14.0" > VERSION
git add VERSION && git commit -m "Release 2.14.0: tasks take several members, a quorum, and links (#334, #335, #336)"
git push origin main
```

Then, with the notes in `$HOME/notes-2.14.0.md` (outside the repo, so it is not
committed):

```bash
gh release create v2.14.0 --target "$(git rev-parse HEAD)" --title v2.14.0 --notes-file "$HOME/notes-2.14.0.md"
```

- [ ] **Step 5: Roll out**

Staging first onto `main`, then the demo, then the bands, per
`bandregie-deploy-reihenfolge`. **Access to `85.215.175.209` was not available
in the session that wrote this plan** — no SSH key for demo, Beat Cuisine or
TonRausch. If that is still the case, stop after staging and say so plainly
rather than reporting a rollout that did not happen.
