<?php
declare(strict_types=1);
function receive_upload(string $field,bool $photo=false): ?string {
    $f=$_FILES[$field]??null;
    if($f===null || ($f['error']??null)===UPLOAD_ERR_NO_FILE) return null;
    if(($f['error']??null)!==UPLOAD_ERR_OK || !is_int($f['size']??null) || $f['size']>5*1024*1024 || !is_string($f['tmp_name']??null) || !is_uploaded_file($f['tmp_name'])) throw new DomainException('Upload a single file up to 5 MB.',422);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed=$photo?['image/jpeg'=>'jpg','image/png'=>'png']:['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if(!isset($allowed[$mime])) throw new DomainException($photo?'Use a JPEG or PNG profile photo.':'Use a PDF, JPEG, or PNG attachment.',422);
    $directory=ROOT.'/storage/uploads'; if(!is_dir($directory)) mkdir($directory,0700,true);
    $name=bin2hex(random_bytes(24)).'.'.$allowed[$mime];
    if($photo) {
        $size=getimagesize($f['tmp_name']); if(!$size || $size[0]*$size[1]>20000000) throw new DomainException('The photo is too large. Use an image below 20 megapixels.',422);
        $source=$mime==='image/jpeg'?imagecreatefromjpeg($f['tmp_name']):imagecreatefrompng($f['tmp_name']);
        if(!$source) throw new DomainException('The image could not be read.',422);
        $thumb=imagescale($source,min(512,$size[0]));
        imagedestroy($source);
        if(!$thumb) throw new RuntimeException('Could not resize the profile photo.');
        $name=bin2hex(random_bytes(24)).'.jpg';
        $path=$directory.'/'.$name;
        $saved=imagejpeg($thumb,$path,85);
        imagedestroy($thumb);
        if(!$saved || !is_file($path) || !filesize($path)) {
            if(is_file($path)) unlink($path);
            throw new RuntimeException('Could not save the profile photo.');
        }
    } elseif(!move_uploaded_file($f['tmp_name'],$directory.'/'.$name)) throw new RuntimeException('Could not save the attachment.');
    return $name;
}
