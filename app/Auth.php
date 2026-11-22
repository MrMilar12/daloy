<?php
declare(strict_types=1);
final class Auth {
    public function __construct(private Database $db) {}
    public function user(): ?array {
        if(empty($_SESSION['user_id'])) return null;
        $u=$this->db->one('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['user_id']]);
        if(!$u || !hash_equals($_SESSION['credential_version']??'',hash('sha256',$u['password_hash']))) { unset($_SESSION['user_id']); return null; } return $u;
    }
    public function login(string $email,string $password,string $ip): void {
        $email=mb_strtolower(trim($email)); $identity=hash('sha256',$email); $ipHash=hash('sha256',$ip); $since=date('Y-m-d H:i:s',time()-900);
        $attempts=$this->db->one('SELECT COUNT(*) n FROM login_attempts WHERE succeeded=0 AND created_at>? AND (identity_hash=? OR ip_hash=?)',[$since,$identity,$ipHash]);
        if($attempts['n']>=10) throw new DomainException('Too many sign-in attempts. Please try again in 15 minutes.',429);
        $u=$this->db->one('SELECT * FROM users WHERE email=?',[$email]);
        $ok=password_verify($password,$u['password_hash']??'$2y$10$abcdefghijklmnopqrstuu0U8XQmLcTvlksUlK.xfJGvrFWFcPJTm');
        $ok=$ok && $u && $u['active'];
        $this->db->insert('login_attempts',['identity_hash'=>$identity,'ip_hash'=>$ipHash,'succeeded'=>$ok?1:0,'created_at'=>now()]);
        if(!$ok) throw new DomainException('The email or password is incorrect, or the account is inactive.',422);
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['credential_version']=hash('sha256',$u['password_hash']); $_SESSION['csrf']=bin2hex(random_bytes(32));
        (new Service($this->db,$u))->audit('Login','users',(int)$u['id'],null,null);
    }
    public function logout(): void { $_SESSION=[]; session_regenerate_id(true); }
}
