<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/AuroraSchools.php';
try {
    $db=new Database($config);
    $count=import_aurora_schools($db);
    echo "Imported $count Aurora school locations. Existing records were preserved.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
