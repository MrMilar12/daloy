<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php'; require ROOT.'/app/Backup.php';
try {
    $dir=ROOT.'/storage/backups'; if(!is_dir($dir)) mkdir($dir,0700,true);
    $file=$argv[1]??$dir.'/daloy-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.zip';
    Backup::create(new Database($config),$file); echo "Backup created: $file\nContains personal data. Store it in an access-controlled, encrypted backup location.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
