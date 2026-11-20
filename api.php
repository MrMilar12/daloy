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
            if($_SERVER['REQUEST_METHOD']!=='POST') throw new DomainException('POST required.',405);
            csrf_check();
            require_once __DIR__.'/app/GitHubUpdater.php';
            $updater=new GitHubUpdater($db,ROOT);
            try { $check=$updater->check(); }
            catch(RuntimeException $e) { throw new DomainException($e->getMessage(),502); }
            $check['notification_created']=$check['available']?$updater->notifyAdminsForUpdate($check['latest']):false;
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
            if(!preg_match('/^[a-f0-9]{40}$/',$sha) || $sha!==$check['latest']) throw new DomainException('Check for updates again before installing.',409);
            try { $result=$updater->apply($check); }
            catch(RuntimeException $e) { throw new DomainException($e->getMessage(),502); }
            $service->notifyActiveUsers('System updated','The latest DALOY update installed successfully.');
            json_out($result);
        case 'system_update_notifications':
            $after=max(0,(int)($_GET['after']??0));
            if($after===0) {
                $notifications=$db->all("SELECT id,title,message,created_at FROM notifications WHERE user_id=? AND title='System updated' AND read_at IS NULL ORDER BY id DESC LIMIT 1",[$user['id']]);
            } else {
                $notifications=$db->all("SELECT id,title,message,created_at FROM notifications WHERE user_id=? AND title='System updated' AND read_at IS NULL AND id>? ORDER BY id ASC LIMIT 50",[$user['id'],$after]);
            }
            $unreadCount=(int)$db->one('SELECT COUNT(*) AS count FROM notifications WHERE user_id=? AND read_at IS NULL',[$user['id']])['count'];
            json_out(['notifications'=>$notifications,'unread_count'=>$unreadCount]);
        case 'notifications_recent':
            $notifications=$db->all('SELECT id,title,message,link,created_at,read_at FROM notifications WHERE user_id=? AND read_at IS NULL ORDER BY created_at DESC,id DESC LIMIT 8',[$user['id']]);
            foreach($notifications as &$notification) {
                $notification['link'] = notification_link($notification['link']);
                $notification['created_at']=date(DATE_ATOM,strtotime($notification['created_at']));
            }
            unset($notification);
            $unreadCount=(int)$db->one('SELECT COUNT(*) AS count FROM notifications WHERE user_id=? AND read_at IS NULL',[$user['id']])['count'];
            json_out(['notifications'=>$notifications,'unread_count'=>$unreadCount]);
        case 'notification_read':
            if($_SERVER['REQUEST_METHOD']!=='POST') throw new DomainException('POST required.',405);
            csrf_check();
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
            if(!$id || !$db->one('SELECT id FROM notifications WHERE id=? AND user_id=?',[$id,$user['id']])) throw new DomainException('Notification not found.',404);
            $db->run('UPDATE notifications SET read_at=COALESCE(read_at,?) WHERE id=? AND user_id=?',[now(),$id,$user['id']]);
            $unreadCount=(int)$db->one('SELECT COUNT(*) AS count FROM notifications WHERE user_id=? AND read_at IS NULL',[$user['id']])['count'];
            json_out(['unread_count'=>$unreadCount]);
        case 'conflicts':
            $id=(int)($_GET['nurse_id']??$user['id']); $service->own($id); [$start,$end]=$service->window($_GET);
            $exclude=(int)($_GET['exclude_id']??0); if($exclude) $service->own((int)$service->record('schedules',$exclude)['nurse_id']);
            json_out(['conflicts'=>$service->conflicts($id,$start,$end,'schedules',$exclude)]);
        case 'candidates': json_out(['candidates'=>$service->candidates((int)($_GET['activity_id']??0))]);
        default: throw new DomainException('Unknown API action.',404);
    }
} catch(DomainException $e) { json_out(['error'=>$e->getMessage()],$e->getCode()?:422); }
catch(Throwable $e) { error_log((string)$e); json_out(['error'=>'Unable to process the request.'],500); }
