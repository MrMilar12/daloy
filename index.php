<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/Auth.php';
start_session();
if(is_file(ROOT.'/storage/update-maintenance.json') && ($_GET['page']??'')!=='health') { http_response_code(503); ?><!doctype html><meta charset="utf-8"><title>System maintenance</title><style>body{font:16px system-ui;background:#f5f8fc;color:#17344a;display:grid;place-items:center;min-height:100vh}main{background:white;padding:40px;border-radius:18px;text-align:center;box-shadow:0 10px 30px #17344a18}h1{margin-top:0;color:#b91f36}</style><main><h1>System maintenance</h1><p>DALOY is applying an update. Please try again shortly.</p></main><?php exit; }
try { $db=new Database($config); $installed=$db->one("SELECT setting_value FROM system_settings WHERE setting_key='organization'"); if(!$installed) throw new RuntimeException('Not installed'); }
catch(Throwable $e) { http_response_code(503); require __DIR__.'/app/setup.php'; exit; }
$auth=new Auth($db); $user=$auth->user(); $page=(string)($_GET['page']??'dashboard'); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        csrf_check(); $action=(string)($_POST['action']??'');
        if($action==='login') {
            $auth->login((string)($_POST['email']??''),(string)($_POST['password']??''),$_SERVER['REMOTE_ADDR']??'unknown');
            redirect($auth->user()['must_change_password']?'?page=activate':'?page=dashboard');
        }
        if(!$user) throw new DomainException('Please sign in again.',401);
        $service=new Service($db,$user);
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
$allowed=['dashboard','schedule','calendar','availability','deployment','leave','notifications','profile'];
if($service->manager()) $allowed=array_merge($allowed,['nurses','locations','activities','reports']);
if($user['role']==='admin') $allowed=array_merge($allowed,['users','configuration','audit','settings']);
if(!in_array($page,$allowed,true)) { http_response_code(403); $error='You do not have access to that page.'; $page='dashboard'; }
require __DIR__.'/app/View.php';
try { ob_start(); require __DIR__.'/app/pages.php'; $content=prepare_page_views(ob_get_contents()); ob_end_clean(); }
catch(DomainException $e) { ob_end_clean(); http_response_code($e->getCode()?:422); $content='<div class="empty">'.e($e->getMessage()).'</div>'; }
catch(Throwable $e) { ob_end_clean(); error_log((string)$e); http_response_code(500); $content='<div class="empty">This page could not be loaded. Please contact your administrator.</div>'; }
require __DIR__.'/app/layout.php';
