<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !getenv('DALOY_CONFIG_FILE')) exit(2);
require dirname(__DIR__).'/app/bootstrap.php';
if(!preg_match('/dbname=daloy_test_[a-f0-9]{10}(?:;|$)/',$config['dsn'])) exit(2);
try {
    $db=new Database($config); $actor=$db->one('SELECT * FROM users WHERE id=?',[(int)$argv[1]]);
    $service=new Service($db,$actor); $service->deploy(['activity_id'=>(int)$argv[2],'nurse_id'=>(int)$argv[3]]); echo 'assigned';
} catch(DomainException $e) { echo 'blocked:'.$e->getCode(); } catch(Throwable $e) { fwrite(STDERR,(string)$e); exit(1); }
