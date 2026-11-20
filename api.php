<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php'; require __DIR__.'/app/Auth.php'; start_session();
try {
    $db=new Database($config); $user=(new Auth($db))->user(); if(!$user) throw new DomainException('Sign in to continue.',401);
    if($user['must_change_password']) throw new DomainException('Change your temporary password first.',403);
    $service=new Service($db,$user);
    switch($_GET['action']??'') {
        case 'browser_push':
            if($_SERVER['REQUEST_METHOD']!=='POST') throw new DomainException('POST required.',405);
            csrf_check();
            $pushKeys=WebPush::keys();
            if(!$pushKeys) throw new DomainException('Browser notifications are unavailable.',503);
            $endpoint=$_POST['endpoint']??null;
            if(!is_string($endpoint) || !WebPush::origin($endpoint)) throw new DomainException('This browser’s push provider is unsupported.',422);
            WebPush::ensureSchema($db);
            $hash=hash('sha256',$endpoint);
            $mode=$_POST['mode']??'';
            if($mode==='enable') {
                $db->transaction(function() use($db,$user,$hash,$endpoint) {
                    $existing=$db->one('SELECT user_id FROM browser_push_subscriptions WHERE endpoint_hash=?',[$hash]);
                    if($existing) {
                        if((int)$existing['user_id']!==(int)$user['id']) throw new DomainException('This browser is already registered to another account. Disable its alerts from that account first.',409);
                        return;
                    }
                    $devices=(int)$db->one('SELECT COUNT(*) AS total FROM browser_push_subscriptions WHERE user_id=?',[$user['id']])['total'];
                    if($devices>=10) throw new DomainException('This account has reached its browser notification limit.',429);
                    $latest=(int)$db->one('SELECT COALESCE(MAX(id),0) AS latest FROM notifications WHERE user_id=?',[$user['id']])['latest'];
                    $db->run('INSERT INTO browser_push_subscriptions (endpoint_hash,endpoint,user_id,last_notification_id) VALUES (?,?,?,?)',[$hash,$endpoint,$user['id'],$latest]);
                });
            } elseif($mode==='disable') {
                $db->run('DELETE FROM browser_push_subscriptions WHERE endpoint_hash=? AND user_id=?',[$hash,$user['id']]);
            } elseif($mode==='test') {
                $subscription=$db->one('SELECT endpoint FROM browser_push_subscriptions WHERE endpoint_hash=? AND user_id=?',[$hash,$user['id']]);
                if(!$subscription) throw new DomainException('Enable alerts on this device first.',403);
                $testAt=now();
                $allowed=$db->run('INSERT INTO browser_push_test_limits (user_id,last_test_at) VALUES (?,?)
                    ON DUPLICATE KEY UPDATE last_test_at=IF(last_test_at<=?,VALUES(last_test_at),last_test_at)',[$user['id'],$testAt,date('Y-m-d H:i:s',time()-30)]);
                if(!$allowed) throw new DomainException('Wait 30 seconds before sending another test alert.',429);
                session_write_close();
                $status=WebPush::send($subscription['endpoint'],$pushKeys,$config['web_push_subject']??'');
                if(in_array($status,[404,410],true)) {
                    $db->run('DELETE FROM browser_push_subscriptions WHERE endpoint_hash=? AND user_id=?',[$hash,$user['id']]);
                    throw new DomainException('This device subscription expired. Select Enable to subscribe again.',410);
                }
                if($status<200 || $status>=300) throw new DomainException('The server could not deliver the test alert to the browser provider.',502);
            } elseif($mode!=='status') throw new DomainException('Invalid browser notification action.',422);
            json_out(['enabled'=>(bool)$db->one('SELECT endpoint_hash FROM browser_push_subscriptions WHERE endpoint_hash=? AND user_id=?',[$hash,$user['id']])]);
        case 'github_updates':
            $service->requireRole(['admin']);
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
