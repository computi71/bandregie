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
];
