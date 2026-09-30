<?php
declare(strict_types=1);
final class Backup {
    public const TABLES=['users','districts','municipalities','locations','duty_types','deployment_reasons','shifts','schedules','activities','deployments','leave_records','availability_records','notifications','audit_logs','login_attempts','system_settings','nurse_locations'];
    public static function create(Database $db,string $destination): void {
        if(is_file($destination)) throw new RuntimeException('Destination already exists. Choose a new backup filename.');
        $data=['version'=>1,'created_at'=>now(),'tables'=>[],'files'=>[]]; $files=[];
        $db->pdo->beginTransaction();
        try {
            // All tables are InnoDB: this transaction captures a consistent database snapshot.
            foreach(self::TABLES as $table) $data['tables'][$table]=$db->all("SELECT * FROM $table");
            foreach($data['tables']['users'] as $u) if($u['photo']) $files[$u['photo']]=true;
            foreach($data['tables']['leave_records'] as $r) if($r['attachment']) $files[$r['attachment']]=true;
            $db->pdo->commit();
        } catch(Throwable $e) { $db->pdo->rollBack(); throw $e; }
        $zip=new ZipArchive(); if($zip->open($destination,ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new RuntimeException('Cannot create backup archive.');
        try {
            foreach(array_keys($files) as $file) {
                if(!preg_match('/^[a-f0-9]{48}\.(jpg|png|pdf)$/D',$file) || !is_file(ROOT.'/storage/uploads/'.$file)) throw new RuntimeException('A referenced upload is missing: '.$file);
                $path=ROOT.'/storage/uploads/'.$file; $data['files'][$file]=hash_file('sha256',$path); $zip->addFile($path,'uploads/'.$file);
            }
            $zip->addFromString('data.json',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
            if(!$zip->close()) throw new RuntimeException('Backup could not be finalized.');
        } catch(Throwable $e) { @$zip->close(); if(is_file($destination)) unlink($destination); throw $e; }
    }
    public static function restore(Database $db,string $source): void {
        if($db->all('SHOW TABLES')) throw new RuntimeException('Restore requires a completely empty database. Existing data will never be overwritten.');
        $zip=new ZipArchive(); if($zip->open($source)!==true) throw new RuntimeException('Cannot read backup archive.');
        try {
            $raw=$zip->getFromName('data.json'); if($raw===false || strlen($raw)>100*1024*1024) throw new RuntimeException('Invalid backup manifest.');
            $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(($data['version']??0)===1 && array_keys($data['tables']??[])===array_slice(self::TABLES,0,-1)) $data['tables']['nurse_locations']=[];
            if(($data['version']??0)!==1 || array_keys($data['tables']??[])!==self::TABLES) throw new RuntimeException('Unsupported backup format.');
            foreach($data['files']??[] as $file=>$hash) {
                if(!preg_match('/^[a-f0-9]{48}\.(jpg|png|pdf)$/D',$file)) throw new RuntimeException('Unsafe upload filename in archive.');
                $bytes=$zip->getFromName('uploads/'.$file); if($bytes===false || !hash_equals($hash,hash('sha256',$bytes))) throw new RuntimeException('Attachment checksum mismatch.');
            }
            foreach(explode(';',file_get_contents(ROOT.'/database/schema.sql')) as $sql) if(trim($sql)!=='') $db->pdo->exec($sql);
            $db->pdo->beginTransaction();
            try {
                foreach(self::TABLES as $table) {
                    $columns=array_column($db->all("SHOW COLUMNS FROM $table"),'Field');
                    foreach($data['tables'][$table] as $row) {
                        if(array_diff(array_keys($row),$columns)) throw new RuntimeException('Unexpected columns in backup.');
                        $db->insert($table,$row);
                    }
                }
                $uploadDir=ROOT.'/storage/uploads'; if(!is_dir($uploadDir)) mkdir($uploadDir,0700,true);
                foreach($data['files']??[] as $file=>$hash) {
                    $path=$uploadDir.'/'.$file;
                    if(is_file($path)) { if(!hash_equals($hash,hash_file('sha256',$path))) throw new RuntimeException('An existing attachment conflicts with this backup.'); continue; }
                    if(file_put_contents($path,$zip->getFromName('uploads/'.$file))===false) throw new RuntimeException('Cannot restore attachment.');
                }
                $db->pdo->commit();
            } catch(Throwable $e) { $db->pdo->rollBack(); throw $e; }
        } finally { $zip->close(); }
    }
}
