<?php
declare(strict_types=1);

final class WebPush {
    private static array $recipients=[];
    private const KEY_GUARD="<?php http_response_code(404); exit; __halt_compiler();\n";

    public static function afterNotification(Database $db,int $userId): void {
        if(PHP_SAPI==='cli' || (!is_file(self::keyPath()) && !is_file(self::legacyKeyPath()))) return;
        if(!self::$recipients) {
            register_shutdown_function(static function() use($db) {
                global $config;
                if($db->pdo->inTransaction()) return;
                try {
                    $keys=self::keys();
                    if(!$keys || !$db->all("SHOW TABLES LIKE 'browser_push_subscriptions'")) return;
                    session_write_close();
                    if(function_exists('fastcgi_finish_request')) fastcgi_finish_request();
                    self::deliverPending($db,$config,$keys,4,array_keys(self::$recipients));
                } catch(Throwable $error) {
                    error_log('Browser push delivery failed: '.$error->getMessage());
                }
            });
        }
        self::$recipients[$userId]=true;
    }

    public static function deliverPending(Database $db,array $config,array $keys,int $limit=50,array $recipients=[]): void {
        $lock='daloy_push_'.substr(hash('sha256',$config['dsn']),0,32);
        if(!(int)$db->one('SELECT GET_LOCK(?,0) AS acquired',[$lock])['acquired']) return;
        try {
            $recipientFilter=$recipients?' AND s.user_id IN ('.implode(',',array_fill(0,count($recipients),'?')).')':'';
            $targets=$db->all('SELECT s.endpoint_hash,s.endpoint,s.user_id,MAX(n.id) AS newest
                FROM browser_push_subscriptions s JOIN users u ON u.id=s.user_id AND u.active=1
                JOIN notifications n ON n.user_id=s.user_id AND n.id>s.last_notification_id AND n.read_at IS NULL
                WHERE (s.retry_at IS NULL OR s.retry_at<=?)'.$recipientFilter.'
                GROUP BY s.endpoint_hash,s.endpoint,s.user_id ORDER BY newest LIMIT '.(int)$limit,[now(),...$recipients]);
            foreach($targets as $target) {
                $owner=[$target['endpoint_hash'],$target['user_id']];
                $status=self::send($target['endpoint'],$keys,$config['web_push_subject']??'');
                if($status>=200 && $status<300) {
                    $db->run('UPDATE browser_push_subscriptions SET last_notification_id=GREATEST(last_notification_id,?),retry_at=NULL WHERE endpoint_hash=? AND user_id=?',[$target['newest'],...$owner]);
                } elseif(in_array($status,[404,410],true)) {
                    $db->run('DELETE FROM browser_push_subscriptions WHERE endpoint_hash=? AND user_id=?',$owner);
                } else {
                    $db->run('UPDATE browser_push_subscriptions SET retry_at=? WHERE endpoint_hash=? AND user_id=?',[date('Y-m-d H:i:s',time()+300),...$owner]);
                    error_log("Browser push delivery postponed (HTTP $status).");
                }
            }
        } finally {
            $db->one('SELECT RELEASE_LOCK(?)',[$lock]);
        }
    }

    public static function keyPath(): string { return ROOT.'/storage/web-push-key.php'; }
    public static function legacyKeyPath(): string { return ROOT.'/storage/web-push.pem'; }

    public static function saveKey(string $pem): void {
        $path=self::keyPath();
        $contents=self::KEY_GUARD.$pem;
        umask(0077);
        $file=@fopen($path,'xb');
        if(!$file) throw new RuntimeException('The push key already exists or storage is unavailable.');
        $written=fwrite($file,$contents);
        fclose($file);
        if($written!==strlen($contents)) {
            unlink($path);
            throw new RuntimeException('Could not save the browser push signing key.');
        }
    }

    public static function encode(string $bytes): string {
        return rtrim(strtr(base64_encode($bytes),'+/','-_'),'=');
    }

    public static function keys(): ?array {
        if(is_file(self::keyPath())) {
            $contents=file_get_contents(self::keyPath());
            if($contents===false || !str_starts_with($contents,self::KEY_GUARD)) throw new RuntimeException('The browser push signing key is invalid.');
            $pem=substr($contents,strlen(self::KEY_GUARD));
        } elseif(is_file(self::legacyKeyPath())) {
            $pem=file_get_contents(self::legacyKeyPath());
        } else return null;
        $private=$pem?openssl_pkey_get_private($pem):false;
        $details=$private?openssl_pkey_get_details($private):false;
        if(($details['ec']['curve_name']??'')!=='prime256v1') throw new RuntimeException('The browser push signing key is invalid.');
        $point="\x04".str_pad($details['ec']['x'],32,"\0",STR_PAD_LEFT).str_pad($details['ec']['y'],32,"\0",STR_PAD_LEFT);
        return ['private'=>$private,'public'=>self::encode($point)];
    }

    public static function origin(string $endpoint): ?string {
        if(strlen($endpoint)>2048 || preg_match('/[\x00-\x20\x7f\\\\]/',$endpoint) || !filter_var($endpoint,FILTER_VALIDATE_URL)) return null;
        $url=parse_url($endpoint);
        $host=strtolower($url['host']??'');
        if(($url['scheme']??'')!=='https' || ($url['port']??443)!==443 || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) return null;
        $path=$url['path']??'';
        $prefix=['fcm.googleapis.com'=>'/fcm/send/','updates.push.services.mozilla.com'=>'/wpush/','web.push.apple.com'=>'/'];
        if(isset($prefix[$host]) && str_starts_with($path,$prefix[$host]) && strlen($path)>strlen($prefix[$host])) return 'https://'.$host;
        if($host==='fcm.googleapis.com' && str_starts_with($path,'/wp/') && strlen($path)>4) return 'https://'.$host;
        if(preg_match('/^[a-z0-9.-]+\.push\.apple\.com$/D',$host) && strlen($path)>1) return 'https://'.$host;
        if(preg_match('/^[a-z0-9.-]+\.notify\.windows\.com$/D',$host) && strlen($path)>1) return 'https://'.$host;
        return null;
    }

    public static function ensureSchema(Database $db): void {
        if($db->all("SHOW TABLES LIKE 'browser_push_subscriptions'") && $db->all("SHOW TABLES LIKE 'browser_push_test_limits'")) return;
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS browser_push_subscriptions (
            endpoint_hash CHAR(64) PRIMARY KEY,
            endpoint VARCHAR(2048) NOT NULL,
            user_id INTEGER NOT NULL,
            last_notification_id INTEGER NOT NULL,
            retry_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS browser_push_test_limits (
            user_id INTEGER PRIMARY KEY,
            last_test_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
    }

    public static function authorization(string $origin,array $keys,string $subject=''): string {
        $claims=['aud'=>$origin,'exp'=>time()+3600];
        if($subject!=='') $claims['sub']=$subject;
        $jwt=self::encode('{"typ":"JWT","alg":"ES256"}').'.'.self::encode(json_encode($claims,JSON_THROW_ON_ERROR));
        if(!openssl_sign($jwt,$signature,$keys['private'],OPENSSL_ALGO_SHA256)) throw new RuntimeException('Could not sign the browser push request.');
        // OpenSSL emits an ASN.1 sequence of two integers. ES256 JWTs use fixed-width r and s.
        $offset=2;
        $raw='';
        for($index=0;$index<2;$index++) {
            if(($signature[$offset]??'')!=="\x02") throw new RuntimeException('Unexpected browser push signature.');
            $length=ord($signature[$offset+1]);
            $part=ltrim(substr($signature,$offset+2,$length),"\0");
            $raw.=str_pad($part,32,"\0",STR_PAD_LEFT);
            $offset+=2+$length;
        }
        return 'vapid t='.$jwt.'.'.self::encode($raw).', k='.$keys['public'];
    }

    public static function send(string $endpoint,array $keys,string $subject): int {
        $origin=self::origin($endpoint);
        if(!$origin) return 410;
        $host=parse_url($origin,PHP_URL_HOST);
        $address=null;
        foreach(dns_get_record($host,DNS_A|DNS_AAAA)?:[] as $record) {
            $candidate=$record['ip']??$record['ipv6']??'';
            if(self::isPublicAddress($candidate)) { $address=$candidate; break; }
        }
        if(!$address) return 0;
        $pinnedAddress=str_contains($address,':')?'['.$address.']':$address;
        $request=curl_init($endpoint);
        curl_setopt_array($request,[
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>'', CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_PROXY=>'', CURLOPT_RESOLVE=>[$host.':443:'.$pinnedAddress],
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>6,
            CURLOPT_HTTPHEADER=>['Authorization: '.self::authorization($origin,$keys,$subject),'TTL: 3600','Topic: daloy-inbox'],
        ]);
        curl_exec($request);
        return (int)curl_getinfo($request,CURLINFO_RESPONSE_CODE);
    }

    public static function isPublicAddress(string $address): bool {
        if(defined('FILTER_FLAG_GLOBAL_RANGE')) {
            if(!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_GLOBAL_RANGE)) return false;
            $packed=inet_pton($address);
            if(strlen($packed)===4) return ord($packed[0])<224;
            return (ord($packed[0])&0xe0)===0x20 && !str_starts_with($packed,"\x20\x02");
        }
        if(!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return false;
        [$first,$second,$third]=array_map('intval',explode('.',$address));
        return $first<224
            && !($first===100 && $second>=64 && $second<=127)
            && !($first===192 && $second===0)
            && !($first===198 && (in_array($second,[18,19],true) || ($second===51 && $third===100)))
            && !($first===203 && $second===0 && $third===113);
    }
}
