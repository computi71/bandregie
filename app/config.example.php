<?php
// Copy this file to config.php and fill in your database credentials.
// Create the database and user first (Plesk: "Databases", or via CLI).
return [
  'db_host' => 'localhost',
  'db_name' => 'bandroadie',
  'db_user' => 'bandroadie',
  'db_pass' => 'YOUR-PASSWORD-HERE',

  // Encryption key for backups and attachments at rest (GDPR Art. 32).
  // Generate one with:  php app/backup.php key
  //
  // Keep it here and nowhere else. It must NOT live in the database — a key
  // stored there would travel inside every backup it is meant to protect.
  // Lose it and the encrypted backups are gone with it, so write it down
  // where the band keeps its other credentials.
  //
  // Leave it empty and everything still works, unencrypted.
  'data_key' => '',

  // Set this only on a public demo whose credentials are published.
  //
  // The installation then refuses everything a later visitor cannot undo:
  // changing any password, creating or deleting accounts, changing a login
  // address or a role, and sending mail. Everything else stays open — events,
  // songs, setlists, treasury, equipment — because that is what a demo is for.
  //
  // It lives here and not in the settings on purpose: in a demo every visitor
  // is the admin, and a switch in the settings would be the first thing to go.
  //
  // 'is_demo' => true,
];
