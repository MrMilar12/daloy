<?php
declare(strict_types=1);
final class Reports {
    public const TYPES=['daily'=>'Daily duty roster','weekly'=>'Weekly nurse schedule','monthly'=>'Monthly nurse schedule','individual'=>'Individual duty history','school'=>'School assignment report','district'=>'District / area assignments','deployment'=>'Deployment report','activity'=>'Activity personnel report','availability'=>'Availability report','leave'=>'Leave / unavailability report','conflicts'=>'Schedule conflict report','audit'=>'Audit trail report'];
    public static function build(Service $s,string $type,array $f): array {
        $s->requireRole(['admin','head']); if(!isset(self::TYPES[$type])) throw new DomainException('Unknown report.',422);
        [$start,$end]=$s->window($f); $db=$s->db;
        if($type==='audit') {
            $s->requireRole(['admin']);
            $rows=$db->all('SELECT created_at,actor_name,role,action,record_type,record_id,reason,old_value,new_value FROM audit_logs WHERE created_at>=? AND created_at<? ORDER BY id DESC LIMIT 5000',[$start,$end]);
            return ['columns'=>['Time','User','Role','Action','Record type','Record ID','Reason','Old value','New value'],'rows'=>array_map('array_values',$rows)];
        }
        if($type==='availability') {
            $rows=[]; foreach($s->workforce($start,$end) as $u) {
                if(!empty($f['nurse_id']) && $u['id']!=(int)$f['nurse_id']) continue;
                if(!empty($f['district_id']) && $u['district_id']!=(int)$f['district_id']) continue;
                $assignments=$s->assignedLocations((int)$u['id']);
                if(!empty($f['location_id']) && !in_array((int)$f['location_id'],array_column($assignments,'id'))) continue;
                if(!empty($f['municipality_id']) && !in_array((int)$f['municipality_id'],array_column($assignments,'municipality_id'))) continue;
                $rows[]=[$u['name'],$u['district_name'],implode('; ',array_column($assignments,'name')),$u['status'],implode('; ',array_map(fn($c)=>$c['label'].' '.$c['starts_at'].' to '.$c['ends_at'],$u['conflicts']))];
            } return ['columns'=>['Nurse','District','Assigned schools / office','Status for interval','Recorded blockers'],'rows'=>$rows];
        }
        if($type==='leave') {
            $where='r.starts_at<? AND r.ends_at>?'; $params=[$end,$start];
            foreach(['nurse_id'=>'u.id','district_id'=>'u.district_id'] as $key=>$column) if(!empty($f[$key])) { $where.=" AND $column=?"; $params[]=(int)$f[$key]; }
            if(!empty($f['location_id'])) { $where.=' AND (u.location_id=? OR EXISTS (SELECT 1 FROM nurse_locations nl WHERE nl.nurse_id=u.id AND nl.location_id=?))'; array_push($params,(int)$f['location_id'],(int)$f['location_id']); }
            if(!empty($f['municipality_id'])) { $where.=' AND (l.municipality_id=? OR EXISTS (SELECT 1 FROM nurse_locations nl JOIN locations al ON al.id=nl.location_id WHERE nl.nurse_id=u.id AND al.municipality_id=?))'; array_push($params,(int)$f['municipality_id'],(int)$f['municipality_id']); }
            $rows=$db->all("SELECT u.name,r.type,r.starts_at,r.ends_at,r.status,r.reason,h.name reviewer,r.reviewed_at,r.review_note FROM leave_records r JOIN users u ON u.id=r.nurse_id LEFT JOIN locations l ON l.id=u.location_id LEFT JOIN users h ON h.id=r.reviewed_by WHERE $where ORDER BY r.starts_at LIMIT 5000",$params);
            return ['columns'=>['Nurse','Type','Start','End','Status','Reason','Reviewer','Reviewed at','Review note'],'rows'=>array_map('array_values',$rows)];
        }
        $roster=$s->roster($f);
        if($type==='conflicts') {
            $rows=[]; $seen=[];
            foreach($roster as $r) {
                if($r['status']==='Cancelled') continue;
                foreach($s->conflicts((int)$r['nurse_id'],$r['starts_at'],$r['ends_at'],$r['source'],(int)$r['id']) as $c) {
                    $pair=[$r['source'].':'.$r['id'],$c['type'].':'.$c['id']]; sort($pair); $key=implode('|',$pair); if(isset($seen[$key])) continue; $seen[$key]=true;
                    $rows[]=[$r['nurse_name'],$r['source'].' #'.$r['id'],$r['starts_at'],$r['ends_at'],$c['label'].' #'.$c['id'],$c['starts_at'],$c['ends_at']];
                }
            }
            return ['columns'=>['Nurse','Assignment','Start','End','Conflict','Conflict start','Conflict end'],'rows'=>$rows];
        }
        if(in_array($type,['deployment','activity'],true)) {
            $rows=[];
            foreach($roster as $r) { if($r['source']!=='deployments' || (!empty($f['activity_id']) && $r['activity_id']!=(int)$f['activity_id'])) continue;
                $rows[]=[$r['id'],$r['nurse_name'],$r['duty_name'],$r['location_name'],$r['starts_at'],$r['ends_at'],$r['status'],$r['reason'],$s->record('users',(int)$r['authorized_by'])['name'],$r['acknowledged_at'],$r['original_assignment'],$r['cancellation_reason']];
            }
            return ['columns'=>['ID','Nurse','Activity','Destination','Start','End','Status','Reason','Authorized by','Acknowledged at','Original home assignment','Cancellation reason'],'rows'=>$rows];
        }
        $rows=[]; foreach($roster as $r) $rows[]=[$r['nurse_name'],$r['duty_name'],$r['location_name'],$r['district_id']?$s->record('districts',(int)$r['district_id'])['name']:'',$r['starts_at'],$r['ends_at'],$r['status'],$r['source']==='deployments'?'Deployment':'Duty', $r['remarks']??$r['instructions']??''];
        return ['columns'=>['Nurse','Assignment','Location','District','Start','End','Status','Record type','Remarks'],'rows'=>$rows];
    }
    public static function xlsx(array $report,string $title): string {
        if(!class_exists('ZipArchive')) throw new RuntimeException('The PHP zip extension is required for Excel export.');
        $tmp=tempnam(sys_get_temp_dir(),'daloy-xlsx-'); $zip=new ZipArchive(); $zip->open($tmp,ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="DALOY report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach([[$title],['Generated '.now().' · Asia/Manila'],$report['columns'],...$report['rows']] as $i=>$row) {
            $xml.='<row r="'.($i+1).'">'; foreach($row as $value) {
                $clean=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string)($value??''));
                // Inline strings cannot be interpreted as spreadsheet formulas.
                $xml.='<c t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($clean,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
            } $xml.='</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml',$xml.'</sheetData></worksheet>'); $zip->close(); $bytes=file_get_contents($tmp); unlink($tmp); return $bytes;
    }
    public static function pdf(array $report,string $title): string {
        // Portable, paginated PDF using built-in Helvetica. Unicode originals remain in XLSX.
        $lines=[iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$title),'Generated '.now().' (Asia/Manila)',''];
        foreach($report['rows'] as $i=>$row) {
            $lines[]='Record '.($i+1);
            foreach($report['columns'] as $j=>$column) {
                $value=preg_replace('/\s+/u',' ',(string)($row[$j]??''));
                $ascii=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$column.': '.$value);
                foreach(explode("\n",wordwrap($ascii?:'',105,"\n",true)) as $line) $lines[]=$line;
            } $lines[]='';
        }
        if(!$report['rows']) $lines[]='No records match the selected filters.';
        $pages=array_chunk($lines,51); $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>']; $kids=[];
        foreach($pages as $i=>$page) {
            $pageId=4+$i*2; $streamId=$pageId+1; $kids[]="$pageId 0 R";
            $stream="BT /F1 10 Tf 42 796 Td 14 TL ";
            foreach($page as $line) { $line=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line); $stream.='('.$line.") Tj T*\n"; }
            $stream.="ET\nBT /F1 9 Tf 42 30 Td (DALOY - Page ".($i+1).' of '.count($pages).") Tj ET";
            $objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";
            $objects[$streamId]='<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>'; ksort($objects);
        $pdf="%PDF-1.4\n"; $offsets=[0]; foreach($objects as $id=>$object) { $offsets[$id]=strlen($pdf); $pdf.="$id 0 obj\n$object\nendobj\n"; }
        $xref=strlen($pdf); $pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n"; for($i=1;$i<count($offsets);$i++) $pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf.'trailer << /Size '.count($offsets)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
