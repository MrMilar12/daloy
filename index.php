<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/Auth.php';
require __DIR__.'/app/Schema.php';
start_session();
if(is_file(ROOT.'/storage/update-maintenance.json') && ($_GET['page']??'')!=='health') { http_response_code(503); ?><!doctype html><meta charset="utf-8"><title>System maintenance</title><style>body{font:16px system-ui;background:#f5f8fc;color:#17344a;display:grid;place-items:center;min-height:100vh}main{background:white;padding:40px;border-radius:18px;text-align:center;box-shadow:0 10px 30px #17344a18}h1{margin-top:0;color:#b91f36}</style><main><h1>System maintenance</h1><p>DALOY is applying an update. Please try again shortly.</p></main><?php exit; }
try { $db=new Database($config); ensure_custom_destination_schema($db); $db->run("CREATE TABLE IF NOT EXISTS registration_requests (id INTEGER PRIMARY KEY AUTO_INCREMENT,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',reviewed_by INTEGER NULL,reviewed_at DATETIME NULL,created_at DATETIME NOT NULL)"); $installed=$db->one("SELECT setting_value FROM system_settings WHERE setting_key='organization'"); if(!$installed) throw new RuntimeException('Not installed'); }
catch(Throwable $e) { http_response_code(503); require __DIR__.'/app/setup.php'; exit; }
$auth=new Auth($db); $user=$auth->user(); $page=(string)($_GET['page']??'dashboard'); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        csrf_check(); $action=(string)($_POST['action']??'');
        if($action==='login') {
            $auth->login((string)($_POST['email']??''),(string)($_POST['password']??''),$_SERVER['REMOTE_ADDR']??'unknown');
            redirect($auth->user()['must_change_password']?'?page=activate':'?page=dashboard');
        }
        if($action==='register') {
            if($user) redirect('?page=dashboard');
            $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??'');
            if(mb_strlen($name)<3 || mb_strlen($name)>120) throw new DomainException('Enter your full name.',422);
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.',422);
            if(strlen($password)<12 || !preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/\d/',$password)) throw new DomainException('Password must be at least 12 characters and include uppercase, lowercase, and a number.',422);
            if($db->one('SELECT id FROM users WHERE email=?',[$email])) throw new DomainException('An account with this email already exists.',409);
            $requestId=$db->insert('registration_requests',['name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'status'=>'pending','created_at'=>now()]);
            foreach($db->all("SELECT id FROM users WHERE role='admin' AND active=1") as $admin) $db->insert('notifications',['user_id'=>$admin['id'],'title'=>'New registration request','message'=>$name.' requested access to DALOY. Review the pending request before creating an account.','link'=>'?page=users','created_at'=>now()]);
            $_SESSION['flash']='Registration submitted. An administrator must activate your account before you can sign in.'; redirect('./');
        }
        if(!$user) throw new DomainException('Please sign in again.',401);
        $service=new Service($db,$user);
        if($action==='register_review') {
            $service->requireRole(['admin']); $request=$db->one("SELECT * FROM registration_requests WHERE id=? AND status='pending'",[(int)($_POST['request_id']??0)]); if(!$request) throw new DomainException('Registration request is no longer pending.',404);
            if(($_POST['decision']??'')==='approve') { $db->insert('users',['name'=>$request['name'],'email'=>$request['email'],'password_hash'=>$request['password_hash'],'role'=>'nurse','active'=>1,'must_change_password'=>1,'designation'=>'Nurse','employment_status'=>'Permanent','created_at'=>now()]); $db->run('DELETE FROM registration_requests WHERE id=?',[(int)$request['id']]); }
            else { $db->update('registration_requests',(int)$request['id'],['status'=>'rejected','reviewed_by'=>$user['id'],'reviewed_at'=>now()]); }
            $_SESSION['flash']=($_POST['decision']??'')==='approve'?'Registration approved. The requester may now sign in with the temporary password.':'Registration request rejected.'; redirect('?page=users');
        }
        if($action==='logout') { $service->audit('Logout','users',(int)$user['id'],null,null); $auth->logout(); redirect('./'); }
        if($user['must_change_password'] && !in_array($action,['activate','profile'],true)) throw new DomainException('Set your new password to finish account setup. Your workspace will then open.',403);
        require __DIR__.'/app/Upload.php'; $file=null;
        try {
            switch($action) {
                case 'schedule': $service->schedule($_POST); break;
                case 'activity': $service->activity($_POST); break;
                case 'deploy': $service->deploy($_POST); break;
                case 'acknowledge': $service->acknowledge($_POST); break;
                case 'cancel': $service->cancel($_POST); break;
                case 'leave': $file=receive_upload('attachment'); $service->leave($_POST,$file); break;
                case 'review_leave': $service->reviewLeave($_POST); break;
                case 'availability': $service->availability($_POST); break;
                case 'user': $service->saveUser($_POST); break;
                case 'catalog': $service->catalog($_POST); break;
                case 'settings': $service->settings($_POST); break;
                case 'activate':
                    if(!$user['must_change_password']) throw new DomainException('Your account is already set up. Change your password from My profile.',409);
                    $service->profile(array_merge($_POST,['phone'=>$user['phone']]));
                    session_regenerate_id(true);
                    $_SESSION['credential_version']=hash('sha256',$service->record('users',(int)$user['id'])['password_hash']);
                    $_SESSION['flash']='Your account is ready. Welcome to DALOY.';
                    redirect('?page=dashboard');
                case 'profile':
                    $file=receive_upload('photo',true); $service->profile($_POST,$file); session_regenerate_id(true);
                    $_SESSION['credential_version']=hash('sha256',$service->record('users',(int)$user['id'])['password_hash']);
                    // Also complete activation for a form opened before the dedicated setup screen existed.
                    if($user['must_change_password']) { $_SESSION['flash']='Your account is ready. Welcome to DALOY.'; redirect('?page=dashboard'); }
                    break;
                case 'read_notifications': $db->run('UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL',[now(),$user['id']]); break;
                case 'read_notification': $db->run('UPDATE notifications SET read_at=? WHERE id=? AND user_id=? AND read_at IS NULL',[now(),(int)($_POST['id']??0),$user['id']]); break;
                default: throw new DomainException('Unknown action.',422);
            }
        } catch(Throwable $e) { if($file && is_file(ROOT.'/storage/uploads/'.$file)) unlink(ROOT.'/storage/uploads/'.$file); throw $e; }
        $_SESSION['flash']='Changes saved successfully.';
        $return=(string)($_POST['return']??'?page='.$page); if(!preg_match('/^\?page=[a-z_]+(?:&[a-z_]+=[a-zA-Z0-9_-]+)*$/D',$return)) $return='?page=dashboard'; redirect($return);
    } catch(DomainException $e) { $error=$e->getMessage(); http_response_code(in_array($e->getCode(),[401,403,404,409,419,422,429])?$e->getCode():422); }
    catch(PDOException $e) { error_log((string)$e); $error=$e->getCode()==='23000'?'That email, employee number, or configuration name is already in use.':'The change could not be saved. Please try again.'; http_response_code(422); }
    catch(Throwable $e) { error_log((string)$e); $error='The change could not be saved. Check the server log or contact your administrator.'; http_response_code(500); }
    $user=$auth->user();
}
if(!$user) { require __DIR__.'/app/login.php'; exit; }
$service=new Service($db,$user);
if($user['must_change_password']) {
    // Never display the workspace navigation while silently replacing its destinations.
    if($_SERVER['REQUEST_METHOD']==='GET' && $page!=='activate') redirect('?page=activate');
    require __DIR__.'/app/activate.php';
    exit;
}
if($page==='activate') redirect('?page=dashboard');
$allowed=['dashboard','schedule','calendar','availability','deployment','deployment-history','leave','notifications','profile'];
if($service->manager()) $allowed=array_merge($allowed,['nurses','locations','activities','reports']);
if($user['role']==='admin') $allowed=array_merge($allowed,['users','configuration','audit','settings']);
if(!in_array($page,$allowed,true)) { http_response_code(403); $error='You do not have access to that page.'; $page='dashboard'; }
require __DIR__.'/app/View.php';
try { ob_start(); require __DIR__.'/app/pages.php'; $content=prepare_page_views(ob_get_contents()); ob_end_clean(); }
catch(DomainException $e) { ob_end_clean(); http_response_code($e->getCode()?:422); $content='<div class="empty">'.e($e->getMessage()).'</div>'; }
catch(Throwable $e) { ob_end_clean(); error_log((string)$e); http_response_code(500); $content='<div class="empty">This page could not be loaded. Please contact your administrator.</div>'; }
require __DIR__.'/app/layout.php';
