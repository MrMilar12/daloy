<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') ini_set('display_errors','0');
define('ROOT', dirname(__DIR__));
$configPath = ROOT . '/config.local.php';
// Isolated test servers can supply a separate config; Apache never reads this override.
if(in_array(PHP_SAPI,['cli','cli-server'],true) && getenv('DALOY_CONFIG_FILE')) $configPath=getenv('DALOY_CONFIG_FILE');
$config = is_file($configPath) ? require $configPath : require ROOT . '/config.example.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Manila');
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Service.php';
function e($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function asset_url(string $file): string { return 'assets/'.$file.'?v='.filemtime(ROOT.'/assets/'.$file); }
function json_out(array $data, int $status = 200): never {
    http_response_code($status); header('Content-Type: application/json'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) throw new DomainException('Your form expired. Refresh the page and try again.', 419);
}
function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
function start_session(): void {
    global $config;
    $sessionPath=$config['session_path']??ROOT.'/storage/sessions';
    if(!is_dir($sessionPath)) mkdir($sessionPath,0700,true);
    session_save_path($sessionPath);
    ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
    session_name('daloy_session');
    session_set_cookie_params(['httponly'=>true, 'secure'=>$config['secure_cookies'] || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'samesite'=>'Lax', 'path'=>'/']);
    if(!session_start()) throw new RuntimeException('The session storage directory must be writable.');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
    if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > $config['session_timeout']) {
        $_SESSION = []; session_regenerate_id(true);
    }
    $_SESSION['last_active'] = time();
}
