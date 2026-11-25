<?php
/* Command line / cron: php app/bin/backup.php
   Makes one full site backup archive in the private data folder (moriwella_data/backups). The admin Backups tab runs this in the background.
   Copyright (c) JSW Software Designs */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/lib.php'; require __DIR__ . '/../src/backup.php';
if (backup_status()['state'] !== 'running') backup_set(['state' => 'running', 'started' => time(), 'message' => 'Starting']);
try { $n = backup_run(); echo date('c') . " backup ok: $n\n"; }
catch (Throwable $e) { backup_set(['state' => 'error', 'message' => $e->getMessage()]); echo date('c') . ' backup FAILED: ' . $e->getMessage() . "\n"; exit(1); }
