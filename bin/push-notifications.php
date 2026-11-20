<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';

try {
    if(in_array('--protect-key',$argv,true)) {
        $legacy=WebPush::legacyKeyPath();
        if(!is_file($legacy)) throw new RuntimeException('There is no legacy push key to migrate.');
        $pem=file_get_contents($legacy);
        $oldKey=$pem?openssl_pkey_get_private($pem):false;
        if(!$oldKey) throw new RuntimeException('The legacy push key is invalid.');
        if(!is_file(WebPush::keyPath())) WebPush::saveKey($pem);
        $newKeys=WebPush::keys();
        if(openssl_pkey_get_details($oldKey)['key']!==openssl_pkey_get_details($newKeys['private'])['key']) throw new RuntimeException('The protected key does not match the legacy key.');
        if(!unlink($legacy)) throw new RuntimeException('Could not remove the legacy key from storage.');
        echo "Browser push key protected. Existing subscriptions can continue using it.\n";
        exit;
    }
    if(in_array('--init',$argv,true)) {
        if(is_file(WebPush::legacyKeyPath())) throw new RuntimeException('Run --protect-key to migrate the existing signing key.');
        $key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
        if(!$key || !openssl_pkey_export($key,$pem)) throw new RuntimeException('Could not generate the browser push signing key.');
        WebPush::saveKey($pem);
        echo "Browser push key created.\n";
        exit;
    }
    $keys=WebPush::keys();
    if(!$keys) throw new RuntimeException('Generate the push signing key with --init first.');
    $db=new Database($config);
    WebPush::ensureSchema($db);
    WebPush::deliverPending($db,$config,$keys);
} catch(Throwable $error) {
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}
