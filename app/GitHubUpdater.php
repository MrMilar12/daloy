<?php
declare(strict_types=1);
final class GitHubUpdater {
    public function __construct(private Database $db, private string $root) {}
    private function maintenance(): string { return $this->root.'/storage/update-maintenance.json'; }
    public function check(string $repository='MrMilar12/daloy', string $branch='main'): array {
        $url='https://api.github.com/repos/'.$repository.'/commits/'.rawurlencode($branch); $ctx=stream_context_create(['http'=>['header'=>"Accept: application/vnd.github+json\r\nUser-Agent: DALOY-Updater\r\n",'timeout'=>10]]); $raw=@file_get_contents($url,false,$ctx); if($raw===false) throw new RuntimeException('GitHub could not be reached.'); $c=json_decode($raw,true,512,JSON_THROW_ON_ERROR); $sha=(string)($c['sha']??''); if(!preg_match('/^[a-f0-9]{40}$/',$sha)) throw new RuntimeException('GitHub returned an invalid commit.'); $current=(string)($this->db->one("SELECT setting_value FROM system_settings WHERE setting_key='github_update_sha'")['setting_value']??''); return ['repository'=>$repository,'branch'=>$branch,'current'=>$current?:'Not recorded','latest'=>$sha,'available'=>$current!==$sha,'message'=>$c['commit']['message']??'','url'=>$c['html_url']??'https://github.com/'.$repository.'/commit/'.$sha,'download'=>'https://github.com/'.$repository.'/archive/refs/heads/'.$branch.'.zip'];
    }
    public function apply(array $update): array {
        if(is_file($this->maintenance())) throw new RuntimeException('Another update is already running.');
        $marker=['started_at'=>now(),'message'=>'DALOY is being updated. Please try again shortly.']; file_put_contents($this->maintenance(),json_encode($marker),LOCK_EX);
        $backup=$this->root.'/storage/update-backup-'.date('Ymd-His').'.zip'; $tmp=$this->root.'/storage/update-download-'.bin2hex(random_bytes(5).'.zip');
        try {
            $bytes=@file_get_contents($update['download']); if($bytes===false || strlen($bytes)>100*1024*1024) throw new RuntimeException('Update archive could not be downloaded.'); file_put_contents($tmp,$bytes,LOCK_EX); $zip=new ZipArchive(); if($zip->open($tmp)!==true) throw new RuntimeException('Update archive is invalid.');
            $allowed=[]; for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i); $name=preg_replace('#^[^/]+/#','',$name); if($name===''||str_contains($name,'..')||str_starts_with($name,'storage/')||str_starts_with($name,'config.local')||str_starts_with($name,'.git/')) continue; if(str_ends_with($name,'/')) continue; $allowed[$name]=true; }
            if(!$allowed) throw new RuntimeException('Update contains no safe application files.');
            $out=new ZipArchive(); if($out->open($backup,ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new RuntimeException('Could not create update backup.'); foreach(array_keys($allowed) as $path){$full=$this->root.'/'.$path;if(is_file($full))$out->addFile($full,$path);} $out->close();
            foreach(array_keys($allowed) as $path){$data=$zip->getFromName(preg_replace('#^[^/]+/#','',$zip->getNameIndex(array_search($path,array_map(fn($i)=>preg_replace('#^[^/]+/#','',$zip->getNameIndex($i)),range(0,$zip->numFiles-1)),true)))); if($data===false) continue; $full=$this->root.'/'.$path; if(!is_dir(dirname($full))) mkdir(dirname($full),0755,true); file_put_contents($full,$data,LOCK_EX);}
            $this->db->run("INSERT INTO system_settings(setting_key,setting_value) VALUES('github_update_sha',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[$update['latest']]); $zip->close(); unlink($tmp); unlink($this->maintenance()); return ['message'=>'Update installed successfully.','backup'=>basename($backup),'sha'=>$update['latest']];
        } catch(Throwable $e){ if(is_file($tmp))unlink($tmp); if(is_file($this->maintenance()))unlink($this->maintenance()); throw $e; }
    }
}
