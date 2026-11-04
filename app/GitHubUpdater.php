<?php
declare(strict_types=1);
final class GitHubUpdater {
    public function __construct(private Database $db, private string $root) {}

    private function maintenance(): string {
        return $this->root.'/storage/update-maintenance.json';
    }

    public function check(string $repository='MrMilar12/daloy', string $branch='main'): array {
        if(!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~',$repository) || !preg_match('~^[A-Za-z0-9._/-]+$~',$branch)) {
            throw new InvalidArgumentException('Invalid GitHub repository or branch.');
        }
        $url='https://api.github.com/repos/'.$repository.'/commits/'.rawurlencode($branch);
        $ctx=stream_context_create(['http'=>['header'=>"Accept: application/vnd.github+json\r\nUser-Agent: DALOY-Updater\r\n",'timeout'=>10,'ignore_errors'=>true]]);
        $raw=@file_get_contents($url,false,$ctx);
        if($raw===false) throw new RuntimeException('GitHub could not be reached. Check HTTPS access from the server.');
        $status=0;
        foreach($http_response_header??[] as $header) if(preg_match('#^HTTP/\S+\s+(\d{3})#',$header,$match)) $status=(int)$match[1];
        $message='';
        $commitUrl='';
        if($status===403 && preg_grep('/^X-RateLimit-Remaining:\s*0$/i',$http_response_header??[])) {
            $feed='https://github.com/'.$repository.'/commits/'.$branch.'.atom';
            $feedRaw=@file_get_contents($feed,false,$ctx);
            $feedStatus=0;
            foreach($http_response_header??[] as $header) if(preg_match('#^HTTP/\S+\s+(\d{3})#',$header,$match)) $feedStatus=(int)$match[1];
            if($feedRaw===false || $feedStatus<200 || $feedStatus>=300) throw new RuntimeException('GitHub API rate limit reached and the public commit feed could not be reached.');
            if(!preg_match('/<entry\b[^>]*>.*?<id>tag:github\.com,2008:Grit::Commit\/([a-f0-9]{40})<\/id>.*?<title>\s*(.*?)\s*<\/title>/s',$feedRaw,$feedCommit)) {
                throw new RuntimeException('GitHub returned an invalid commit feed.');
            }
            $sha=$feedCommit[1];
            $message=htmlspecialchars(html_entity_decode(strip_tags($feedCommit[2]),ENT_QUOTES|ENT_XML1,'UTF-8'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $commitUrl='https://github.com/'.$repository.'/commit/'.$sha;
        } else {
            if($status<200 || $status>=300) throw new RuntimeException('GitHub update check failed (HTTP '.$status.').');
            $c=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            $sha=(string)($c['sha']??'');
            $message=htmlspecialchars((string)($c['commit']['message']??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $commitUrl=(string)($c['html_url']??'https://github.com/'.$repository.'/commit/'.$sha);
        }
        if(!preg_match('/^[a-f0-9]{40}$/',$sha)) throw new RuntimeException('GitHub returned an invalid commit.');
        $current=(string)($this->db->one("SELECT setting_value FROM system_settings WHERE setting_key='github_update_sha'")['setting_value']??'');
        return [
            'repository'=>$repository,
            'branch'=>$branch,
            'current'=>$current?:'Not recorded',
            'latest'=>$sha,
            'available'=>$current!==$sha,
            'message'=>$message,
            'url'=>$commitUrl,
            'download'=>'https://github.com/'.$repository.'/archive/'.$sha.'.zip',
        ];
    }

    public function apply(array $update): array {
        $repository=(string)($update['repository']??'');
        $sha=(string)($update['latest']??'');
        if(!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~',$repository) || !preg_match('/^[a-f0-9]{40}$/',$sha)) {
            throw new InvalidArgumentException('Invalid update repository or commit.');
        }
        if(is_file($this->maintenance())) throw new RuntimeException('Another update is already running.');
        $marker=['started_at'=>now(),'message'=>'DALOY is being updated. Please try again shortly.'];
        if(file_put_contents($this->maintenance(),json_encode($marker),LOCK_EX)===false) throw new RuntimeException('Could not enable update maintenance mode.');
        $backup=$this->root.'/storage/update-backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.zip';
        $tmp=$this->root.'/storage/update-download-'.bin2hex(random_bytes(5)).'.zip';
        $zip=null;
        try {
            $url='https://github.com/'.$repository.'/archive/'.$sha.'.zip';
            $context=stream_context_create(['http'=>['header'=>"User-Agent: DALOY-Updater\r\n",'timeout'=>60]]);
            $bytes=@file_get_contents($url,false,$context);
            if($bytes===false || strlen($bytes)>100*1024*1024) throw new RuntimeException('Update archive could not be downloaded or exceeds the 100 MB limit.');
            if(file_put_contents($tmp,$bytes,LOCK_EX)===false) throw new RuntimeException('Could not save the update archive.');
            $zip=new ZipArchive();
            if($zip->open($tmp)!==true) throw new RuntimeException('Update archive is invalid.');

            $allowed=[];
            for($i=0;$i<$zip->numFiles;$i++) {
                $archivePath=$zip->getNameIndex($i);
                $parts=explode('/',(string)$archivePath,2);
                if(count($parts)!==2) continue;
                $path=$parts[1];
                if($path==='' || str_ends_with($path,'/')) continue;
                $segments=explode('/',$path);
                if(str_starts_with($path,'/') || str_contains($path,'\\') || in_array('..',$segments,true)) continue;
                if(str_starts_with($path,'storage/') || $path==='config.local.php' || str_starts_with($path,'.git/')) continue;
                $allowed[$path]=$archivePath;
            }
            if(!$allowed) throw new RuntimeException('Update contains no safe application files.');

            $out=new ZipArchive();
            if($out->open($backup,ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new RuntimeException('Could not create update backup.');
            foreach(array_keys($allowed) as $path) {
                $full=$this->root.'/'.$path;
                if(is_file($full) && !$out->addFile($full,$path)) {
                    $out->close();
                    throw new RuntimeException('Could not add an existing application file to the update backup.');
                }
            }
            if(!$out->close()) throw new RuntimeException('Could not finish the update backup.');

            foreach($allowed as $path=>$archivePath) {
                $data=$zip->getFromName($archivePath);
                if($data===false) throw new RuntimeException('An application file is missing from the update archive.');
                $full=$this->root.'/'.$path;
                if(!is_dir(dirname($full)) && !mkdir(dirname($full),0755,true) && !is_dir(dirname($full))) {
                    throw new RuntimeException('Could not create an application directory.');
                }
                if(file_put_contents($full,$data,LOCK_EX)===false) throw new RuntimeException('Could not install an application file.');
            }
            $this->db->run("INSERT INTO system_settings(setting_key,setting_value) VALUES('github_update_sha',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[$sha]);
            $zip->close();
            unlink($tmp);
            unlink($this->maintenance());
            return ['message'=>'Update installed successfully.','backup'=>basename($backup),'sha'=>$sha];
        } catch(Throwable $e) {
            if($zip instanceof ZipArchive) $zip->close();
            if(is_file($tmp)) unlink($tmp);
            if(is_file($this->maintenance())) unlink($this->maintenance());
            throw $e;
        }
    }
}
