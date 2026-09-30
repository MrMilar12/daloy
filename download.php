<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php'; require __DIR__.'/app/Auth.php'; start_session();
try {
    $db=new Database($config); $user=(new Auth($db))->user(); if(!$user || $user['must_change_password']) throw new DomainException('Sign in and activate your account first.',403);
    $service=new Service($db,$user); $type=$_GET['type']??'';
    if($type==='leave') { $r=$service->record('leave_records',(int)($_GET['id']??0)); $service->own((int)$r['nurse_id']); $name=$r['attachment']; }
    elseif($type==='photo') { $r=$service->record('users',(int)($_GET['id']??0)); $service->own((int)$r['id']); $name=$r['photo']; }
    else throw new DomainException('Unknown file type.',404);
    if(!$name || !preg_match('/^[a-f0-9]{48}\.(jpg|png|pdf)$/D',$name) || !is_file(ROOT.'/storage/uploads/'.$name)) throw new DomainException('File not found.',404);
    $path=ROOT.'/storage/uploads/'.$name; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    header('Content-Type: '.$mime); header('Content-Length: '.filesize($path)); header('Content-Disposition: '.($type==='photo'?'inline':'attachment').'; filename="daloy-'.$type.'-'.(int)$r['id'].'.'.pathinfo($name,PATHINFO_EXTENSION).'"'); readfile($path);
} catch(DomainException $e) { http_response_code($e->getCode()?:403); echo e($e->getMessage()); }
catch(Throwable $e) { error_log((string)$e); http_response_code(500); echo 'Unable to retrieve file.'; }
