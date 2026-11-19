<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';

try {
    if(in_array('--init',$argv,true)) {
        $path=WebPush::keyPath();
        $key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
        if(!$key || !openssl_pkey_export($key,$pem)) throw new RuntimeException('Could not generate the browser push signing key.');
        umask(0077);
        $file=@fopen($path,'xb');
        if(!$file) throw new RuntimeException('The push key already exists or storage is unavailable.');
        if(fwrite($file,$pem)!==strlen($pem)) {
            fclose($file);
            unlink($path);
            throw new RuntimeException('Could not save the browser push signing key.');
        }
        fclose($file);
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
