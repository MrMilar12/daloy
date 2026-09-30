<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $db=new Database($config);
    foreach(explode(';',file_get_contents(ROOT.'/database/schema.sql')) as $sql) {
        if(str_starts_with(trim($sql),'CREATE TABLE IF NOT EXISTS nurse_locations')) $db->pdo->exec($sql);
    }
    echo "Multiple school assignments enabled. Existing home assignments are preserved.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
