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
            $repository='MrMilar12/daloy'; $branch='main';
            $url='https://api.github.com/repos/'.$repository.'/commits/'.rawurlencode($branch);
            $context=stream_context_create(['http'=>['method'=>'GET','header'=>"Accept: application/vnd.github+json\r\nUser-Agent: DALOY-Updater\r\n",'timeout'=>10]]);
            $raw=@file_get_contents($url,false,$context); if($raw===false) throw new RuntimeException('GitHub could not be reached. Check HTTPS access from the server.');
            $commit=json_decode($raw,true,512,JSON_THROW_ON_ERROR); $sha=(string)($commit['sha']??''); if(!preg_match('/^[a-f0-9]{40}$/',$sha)) throw new RuntimeException('GitHub returned an invalid commit.');
            $current=(string)($db->one("SELECT setting_value FROM system_settings WHERE setting_key='github_update_sha'")['setting_value']??'');
            json_out(['repository'=>$repository,'branch'=>$branch,'current'=>$current?:'Not recorded','latest'=>$sha,'available'=>$current!==$sha,'message'=>$commit['commit']['message']??'','url'=>$commit['html_url']??('https://github.com/'.$repository.'/commit/'.$sha),'download'=>'https://github.com/'.$repository.'/archive/refs/heads/'.$branch.'.zip','checked_at'=>date('c')]);
        case 'github_update_apply':
            // Use the DALOY repository for installation updates.
            $_ENV['DALOY_UPDATE_REPOSITORY']='MrMilar12/daloy';
            $service->requireRole(['admin']); if($_SERVER['REQUEST_METHOD']!=='POST') throw new DomainException('POST required.',405); csrf_check(); if(!password_verify((string)($_POST['password']??''),$user['password_hash'])) throw new DomainException('Confirm your administrator password to install updates.',422); require_once __DIR__.'/app/GitHubUpdater.php'; $updater=new GitHubUpdater($db,ROOT); $repository='MrMilar12/daloy'; $branch='main'; $sha=preg_replace('/[^a-f0-9]/','',(string)($_POST['latest']??'')); $check=$updater->check($repository,$branch); if($sha!==$check['latest']||!$check['available']) throw new DomainException('Check for updates again before installing.',409); foreach($db->all('SELECT id FROM users WHERE active=1 AND id<>?',[$user['id']]) as $recipient) $db->insert('notifications',['user_id'=>$recipient['id'],'title'=>'System maintenance starting','message'=>'The system is being updated. Please save your work; access will resume when maintenance is complete.','link'=>'?page=notifications','created_at'=>now()]); json_out($updater->apply($check));
        case 'conflicts':
            $id=(int)($_GET['nurse_id']??$user['id']); $service->own($id); [$start,$end]=$service->window($_GET);
            $exclude=(int)($_GET['exclude_id']??0); if($exclude) $service->own((int)$service->record('schedules',$exclude)['nurse_id']);
            json_out(['conflicts'=>$service->conflicts($id,$start,$end,'schedules',$exclude)]);
        case 'candidates': json_out(['candidates'=>$service->candidates((int)($_GET['activity_id']??0))]);
        default: throw new DomainException('Unknown API action.',404);
    }
} catch(DomainException $e) { json_out(['error'=>$e->getMessage()],$e->getCode()?:422); }
catch(Throwable $e) { error_log((string)$e); json_out(['error'=>'Unable to process the request.'],500); }
