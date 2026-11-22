<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php'; require __DIR__.'/app/Auth.php'; start_session();
try {
    $db=new Database($config); $user=(new Auth($db))->user(); if(!$user) throw new DomainException('Sign in to continue.',401);
    if($user['must_change_password']) throw new DomainException('Change your temporary password first.',403);
    $service=new Service($db,$user);
    switch($_GET['action']??'') {
        case 'github_updates':
            $service->requireRole(['admin']);
            require_once __DIR__.'/app/GitHubUpdater.php';
            try { $check=(new GitHubUpdater($db,ROOT))->check(); }
            catch(RuntimeException $e) { throw new DomainException($e->getMessage(),502); }
            $check['checked_at']=date('c');
            json_out($check);
        case 'github_update_apply':
            $service->requireRole(['admin']);
            if($_SERVER['REQUEST_METHOD']!=='POST') throw new DomainException('POST required.',405);
            csrf_check();
            if(!password_verify((string)($_POST['password']??''),$user['password_hash'])) throw new DomainException('Confirm your administrator password to install updates.',422);
            require_once __DIR__.'/app/GitHubUpdater.php';
            $updater=new GitHubUpdater($db,ROOT);
            $sha=(string)($_POST['latest']??'');
            try { $check=$updater->check(); }
            catch(RuntimeException $e) { throw new DomainException($e->getMessage(),502); }
            if(!preg_match('/^[a-f0-9]{40}$/',$sha) || $sha!==$check['latest'] || !$check['available']) throw new DomainException('Check for updates again before installing.',409);
            try { $result=$updater->apply($check); }
            catch(RuntimeException $e) { throw new DomainException($e->getMessage(),502); }
            foreach($db->all('SELECT id FROM users WHERE active=1 AND id<>?',[$user['id']]) as $recipient) {
                $db->insert('notifications',['user_id'=>$recipient['id'],'title'=>'System updated','message'=>'DALOY was updated successfully. Please sign in again if asked.','link'=>'?page=notifications','created_at'=>now()]);
            }
            json_out($result);
        case 'conflicts':
            $id=(int)($_GET['nurse_id']??$user['id']); $service->own($id); [$start,$end]=$service->window($_GET);
            $exclude=(int)($_GET['exclude_id']??0); if($exclude) $service->own((int)$service->record('schedules',$exclude)['nurse_id']);
            json_out(['conflicts'=>$service->conflicts($id,$start,$end,'schedules',$exclude)]);
        case 'candidates': json_out(['candidates'=>$service->candidates((int)($_GET['activity_id']??0))]);
        default: throw new DomainException('Unknown API action.',404);
    }
} catch(DomainException $e) { json_out(['error'=>$e->getMessage()],$e->getCode()?:422); }
catch(Throwable $e) { error_log((string)$e); json_out(['error'=>'Unable to process the request.'],500); }
