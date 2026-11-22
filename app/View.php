<?php
declare(strict_types=1);
function icon(string $name): string {
    $paths=['dashboard'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z','calendar'=>'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2 M7 14h3m4 0h3m-10 4h3','nurses'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 4a4 4 0 0 1 0 7m3 10v-2a4 4 0 0 0-3-4','locations'=>'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0','deployment'=>'m22 2-7 20-4-9-9-4 20-7ZM11 13 22 2','reports'=>'M4 3h16v18H4zM8 7h8m-8 5h8m-8 5h4','settings'=>'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2','notifications'=>'M18 8a6 6 0 0 0-12 0c0 8-3 8-3 10h18c0-2-3-2-3-10m-8 14h4','availability'=>'m4 12 5 5L20 6','leave'=>'M8 3h8v4h5v14H3V7h5V3Zm0 4h8m-4 4v6m-3-3h6','activities'=>'m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6','profile'=>'M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10','arrow'=>'M5 12h14m-6-6 6 6-6 6'];
    $name=match($name){'schedule'=>'calendar','users'=>'nurses','configuration'=>'settings','audit'=>'reports',default=>$name};
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name]??$paths['dashboard']).'"/></svg>';
}
function badge(string $text): string { return '<span class="badge '.e(strtolower(str_replace(' ','-',$text))).'"><span class="dot"></span>'.e($text).'</span>'; }
function initials(string $name): string { $parts=explode(' ',preg_replace('/^Demo /','',$name)); return mb_substr($parts[0],0,1).mb_substr(end($parts),0,1); }
function avatar(string $name): string { return '<span class="avatar">'.e(initials($name)).'</span>'; }
function dt(string $date): string { return date('M j, Y · g:i A',strtotime($date)); }
function range_label(array $r): string { return e(date('M j, Y',strtotime($r['starts_at']))).'<small>'.e(date('g:i A',strtotime($r['starts_at'])).' – '.(substr($r['starts_at'],0,10)!==substr($r['ends_at'],0,10)?date('M j, ',strtotime($r['ends_at'])):'').date('g:i A',strtotime($r['ends_at']))).'</small>'; }
function options(string $table,string $where='active=1'): array { global $db; return array_column($db->all("SELECT id,name FROM $table WHERE $where ORDER BY name"),'name','id'); }
function input(string $name,string $label,mixed $value='',string $type='text',bool $required=true,string $extra=''): void { ?><label><?=e($label)?><input type="<?=e($type)?>" name="<?=e($name)?>" value="<?=e($value)?>" <?=$required?'required':''?> <?=$extra?>></label><?php }
function select_field(string $name,string $label,array $options,mixed $value='',bool $required=true): void { ?><label><?=e($label)?><select name="<?=e($name)?>" <?=$required?'required':''?>><option value=""><?=$required?'Select an option':'All / none'?></option><?php foreach($options as $k=>$v): ?><option value="<?=e($k)?>" <?=(string)$value===(string)$k?'selected':''?>><?=e($v)?></option><?php endforeach ?></select></label><?php }
function school_assignments_field(array $record): void {
    global $db;
    $selected=$record['school_ids']??(isset($record['id'])?array_column($db->all('SELECT location_id FROM nurse_locations WHERE nurse_id=?',[$record['id']]),'location_id'):[]);
    if(!is_array($selected)) $selected=[];
    $snapshot=json_decode(file_get_contents(ROOT.'/database/aurora-schools.json'),true,512,JSON_THROW_ON_ERROR);
    $names=array_column(array_filter($snapshot['schools'],fn($s)=>!empty($s['levels']) && !empty($s['public_inventory'])),'name');
    $schools=$db->all("SELECT l.*,m.name municipality_name FROM locations l LEFT JOIN municipalities m ON m.id=l.municipality_id WHERE l.type='School' ORDER BY l.name,l.id");
    ?><fieldset class="span-2 school-assignments">
        <legend class="sr-only">Additional assigned schools</legend>
        <input type="hidden" name="schools_present" value="1">
        <div class="school-picker-heading"><span class="school-picker-icon" aria-hidden="true"><?=icon('locations')?></span><div><h3>Additional schools</h3><p>Expand this nurse’s coverage beyond their home school.</p></div><span class="school-selection-badge" aria-live="polite"><?=count(array_unique($selected))?> selected</span></div>
        <div class="school-selected-chips" aria-label="Selected schools" hidden></div>
        <label class="school-search-box"><span class="sr-only">Search schools</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg><input type="search" class="school-assignment-search" placeholder="Search by school or municipality…" autocomplete="off"></label>
        <div class="school-picker-meta"><span>PUBLIC ELEMENTARY & HIGH SCHOOLS</span><span class="school-result-count"></span></div>
        <div class="school-assignment-options"><?php foreach($schools as $school): $checked=in_array($school['id'],$selected); if(!$checked && (!$school['active'] || !in_array($school['name'],$names,true))) continue; ?><label class="school-assignment-option"><input type="checkbox" name="school_ids[]" value="<?=$school['id']?>" <?=$checked?'checked':''?>><span><strong><?=e($school['name'])?></strong><small><?=e($school['municipality_name']?:'Aurora')?><?=$school['active']?'':' · Inactive'?></small></span></label><?php endforeach ?></div>
        <p class="school-picker-empty" hidden>No schools found. Try another name or municipality.</p>
        <div class="school-picker-footer"><span aria-hidden="true"><?=icon('calendar')?></span><span>Select all schools this nurse covers. Schedule each visit separately.</span></div>
    </fieldset><?php
}
function choices(array $values): array { return array_combine($values,$values); }
function textarea(string $name,string $label,mixed $value='',bool $required=false): void { ?><label class="span-2"><?=e($label)?><textarea name="<?=e($name)?>" rows="3" maxlength="5000" <?=$required?'required':''?>><?=e($value)?></textarea></label><?php }
function form_start(string $action,string $return,array $record=[]): void { ?><form method="post" enctype="multipart/form-data" class="form-grid" data-validate><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=e($action)?>"><input type="hidden" name="return" value="<?=e($return)?>"><?php if(isset($record['id'])): ?><input type="hidden" name="id" value="<?=e($record['id'])?>"><?php endif; }
function form_end(string $return,string $label='Save changes'): void { ?><div class="form-actions span-2"><a href="<?=e($return)?>" class="button">Cancel</a><button class="primary" type="submit"><?=e($label)?></button></div></form><?php }
function time_fields(array $r=[]): void { input('starts_at','Starts at',str_replace(' ','T',substr($r['starts_at']??date('Y-m-d').' 08:00',0,16)),'datetime-local'); input('ends_at','Ends at',str_replace(' ','T',substr($r['ends_at']??date('Y-m-d').' 17:00',0,16)),'datetime-local'); }
function nurse_field(array $r=[]): void { global $service,$user; if($service->manager()) select_field('nurse_id','Nurse',options('users',"active=1 AND role='nurse'"),$r['nurse_id']??''); else echo '<input type="hidden" name="nurse_id" value="'.e($user['id']).'">'; }
function cancel_control(string $table,array $r,string $return): void { if($r['status']==='Cancelled') return; ?><details class="cancel-control"><summary>Cancel</summary><form method="post" data-confirm="Cancel this record? Its history will be retained."><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="table" value="<?=e($table)?>"><input type="hidden" name="id" value="<?=e($r['id'])?>"><input type="hidden" name="return" value="<?=e($return)?>"><label>Reason<input name="reason" required maxlength="2000"></label><button type="submit" class="danger-button">Confirm cancellation</button></form></details><?php }
function simple_action(string $action,int $id,string $label,string $return): void { ?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=e($action)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="return" value="<?=e($return)?>"><button class="small primary"><?=e($label)?></button></form><?php }
function empty_state(string $title,string $message): void { echo '<div class="empty">'.icon('calendar').'<h3>'.e($title).'</h3><p>'.e($message).'</p></div>'; }
function page_heading(string $eyebrow,string $title,string $description,string $action=''): void {
    global $page;
    $display=($_GET['display']??'card')==='list'?'list':'card';
    $showDisplay=in_array((string)$page,['users','nurses','locations','configuration','availability','deployment','reports'],true);
    ?><div class="page-heading"><div><span class="eyebrow"><?=e($eyebrow)?></span><h1><?=e($title)?></h1><p><?=e($description)?></p></div><div class="page-heading-actions"><?php if($showDisplay): ?><div class="page-display-switch" role="group" aria-label="Page layout"><?php foreach(['card'=>'Card','list'=>'List'] as $key=>$label): ?><a href="?<?=e(http_build_query(array_merge($_GET,['display'=>$key])))?>" data-display-choice="<?=$key?>" role="button" aria-pressed="<?=$display===$key?'true':'false'?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><?php if($key==='card'): ?><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><?php else: ?><path d="M8 5h13M8 12h13M8 19h13M3 5h1M3 12h1M3 19h1"/><?php endif ?></svg><?=$label?></a><?php endforeach ?></div><?php endif ?><?=$action?></div></div><?php
}
function prepare_page_views(string $html): string {
    $dom=new DOMDocument('1.0','UTF-8');
    $previous=libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',LIBXML_NONET);
        foreach($dom->getElementsByTagName('table') as $table) {
            $table->setAttribute('class',trim($table->getAttribute('class').' record-view-table'));
            $table->setAttribute('role','table');
            $headers=[];
            foreach($table->getElementsByTagName('th') as $th) { $headers[]=trim($th->textContent); $th->setAttribute('scope','col'); }
            foreach($table->getElementsByTagName('tbody') as $body) foreach($body->getElementsByTagName('tr') as $row) {
                $row->setAttribute('role','row');
                foreach($row->getElementsByTagName('td') as $i=>$cell) { $cell->setAttribute('data-label',$headers[$i]??''); $cell->setAttribute('role','cell'); }
            }
        }
        foreach($dom->getElementsByTagName('form') as $form) if(strtolower($form->getAttribute('method'))==='get') {
            $input=$dom->createElement('input'); $input->setAttribute('type','hidden'); $input->setAttribute('name','display'); $input->setAttribute('value',($_GET['display']??'card')==='list'?'list':'card'); $form->appendChild($input);
        }
        $result=''; foreach($dom->getElementsByTagName('body')->item(0)->childNodes as $node) $result.=$dom->saveHTML($node);
        return $result;
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
}
function add_link(string $url,string $label): string { return '<a class="button primary" href="'.e($url).'">+ '.e($label).'</a>'; }
function pagination(int $count,int $pageNum,int $size=25): void { if($count<=$size && $pageNum===1) return; $base=$_GET; unset($base['p']); ?><div class="pagination"><span>Page <?=$pageNum?></span><?php if($pageNum>1): ?><a class="button small" href="?<?=e(http_build_query($base+['p'=>$pageNum-1]))?>">← Previous</a><?php endif ?><?php if($count>$size): ?><a class="button small" href="?<?=e(http_build_query($base+['p'=>$pageNum+1]))?>">Next →</a><?php endif ?></div><?php }
function roster_table(array $rows,bool $edit=false): void { global $service; if(!$rows) { empty_state('No assignments in this period','Adjust the filters or add an authorized duty schedule.'); return; } ?><div class="table-wrap"><table><thead><tr><th>Nurse</th><th>Assignment & location</th><th>Date & time</th><th>Status</th><?php if($edit): ?><th>Actions</th><?php endif ?></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><div class="person"><?=avatar($r['nurse_name'])?><strong><?=e($r['nurse_name'])?></strong></div></td><td><strong><?=e($r['duty_name'])?></strong><small><?=e($r['location_name'])?></small><?php if(!empty($r['remarks'])): ?><small><?=e($r['remarks'])?></small><?php endif ?></td><td><?=range_label($r)?></td><td><?=badge($r['source']==='deployments' && $r['status']!=='Cancelled'?'Deployed':$r['status'])?></td><?php if($edit): ?><td><?php if($r['source']==='schedules' && $r['status']!=='Cancelled'): ?><a href="?page=schedule&edit=<?=$r['id']?>">Edit</a><?php cancel_control('schedules',$r,'?page=schedule'); elseif($r['source']==='deployments'): ?><a href="?page=deployment">Details →</a><?php endif ?></td><?php endif ?></tr><?php endforeach ?></tbody></table></div><?php }
function filter_form(string $page,string $from,string $to,bool $duty=true): void { global $service; ?><form method="get" class="filters"><input type="hidden" name="page" value="<?=e($page)?>"><?php input('from','From',$from,'date'); input('to','Through',$to,'date'); if($service->manager()) { select_field('nurse_id','Nurse',options('users',"role='nurse'"),$_GET['nurse_id']??'',false); select_field('district_id','District / area',options('districts','1=1'),$_GET['district_id']??'',false); } select_field('location_id','Location',options('locations','1=1'),$_GET['location_id']??'',false); select_field('municipality_id','Municipality',options('municipalities','1=1'),$_GET['municipality_id']??'',false); if($duty) { select_field('duty_type_id','Duty type',options('duty_types','1=1'),$_GET['duty_type_id']??'',false); select_field('status','Status',choices(['Scheduled','Assigned','Acknowledged','Cancelled']),$_GET['status']??'',false); } ?><button type="submit">Apply filters</button><a href="?page=<?=e($page)?>">Reset</a></form><?php }
