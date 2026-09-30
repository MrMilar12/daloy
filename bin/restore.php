<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php'; require ROOT.'/app/Backup.php';
try {
    if(empty($argv[1])) throw new RuntimeException('Usage: php bin/restore.php path/to/backup.zip — configure an EMPTY database first.');
    Backup::restore(new Database($config),$argv[1]); echo "Database and attachments restored. Sign in using the accounts in the backup.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
