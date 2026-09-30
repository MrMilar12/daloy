<?php
declare(strict_types=1);
final class Service {
    public function __construct(public Database $db, public array $actor) {}
    public function manager(): bool { return in_array($this->actor['role'], ['admin','head'], true); }
    public function requireRole(array $roles): void { if(!in_array($this->actor['role'],$roles,true)) throw new DomainException('You do not have permission to perform this action.',403); }
    public function own(int $id): void { if(!$this->manager() && $id !== (int)$this->actor['id']) throw new DomainException('This record belongs to another nurse.',403); }
    public function record(string $table,int $id): array { return $this->db->one("SELECT * FROM $table WHERE id=?",[$id]) ?? throw new DomainException('Record not found.',404); }
    public function text(array $d,string $key,int $max=190,bool $required=true): string {
        if(isset($d[$key]) && !is_scalar($d[$key])) throw new DomainException('Invalid '.str_replace('_',' ',$key).'.',422);
        $v=trim((string)($d[$key]??'')); if(($required && $v==='') || mb_strlen($v)>$max) throw new DomainException(ucwords(str_replace('_',' ',$key))." is required and must be no longer than $max characters.",422); return $v;
    }
    public function choice(array $d,string $key,array $values): string { $v=$this->text($d,$key); if(!in_array($v,$values,true)) throw new DomainException('Invalid '.str_replace('_',' ',$key).'.',422); return $v; }
    public function positive(array $d,string $key,int $min=1,int $max=10000): int {
        $v=filter_var($d[$key]??null,FILTER_VALIDATE_INT); if($v===false || $v<$min || $v>$max) throw new DomainException("Invalid $key (allowed $min–$max).",422); return $v;
    }
    public function reference(string $table,mixed $id): int { $id=(int)$id; $r=$this->record($table,$id); if(isset($r['active']) && !$r['active']) throw new DomainException('The selected record is inactive.',422); return $id; }
    public function nurse(mixed $id): int { $id=$this->reference('users',$id); if($this->record('users',$id)['role']!=='nurse') throw new DomainException('Select an active nurse.',422); return $id; }
    public function window(array $d): array {
        $values=[];
        foreach(['starts_at','ends_at'] as $key) {
            $raw=str_replace('T',' ',trim((string)($d[$key]??''))); if(strlen($raw)===16) $raw.=':00';
            $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$raw);
            if(!$date || $date->format('Y-m-d H:i:s')!==$raw) throw new DomainException('Enter valid start and end dates and times.',422);
            $values[]=$raw;
        }
        if($values[1]<=$values[0]) throw new DomainException('End time must be after start time. For overnight duty, choose the following date.',422);
        if(strtotime($values[1])-strtotime($values[0])>366*86400) throw new DomainException('A record cannot span more than one year.',422);
        return $values;
    }
    public function audit(string $action,string $table,?int $id,?array $old,?array $new,string $reason=''): void {
        foreach(['password_hash','password','photo','attachment'] as $key) { if($old) unset($old[$key]); if($new) unset($new[$key]); }
        $this->db->insert('audit_logs',['user_id'=>$this->actor['id']??null,'actor_name'=>$this->actor['name']??'System','role'=>$this->actor['role']??'system','action'=>$action,'record_type'=>$table,'record_id'=>$id,'old_value'=>$old?json_encode($old):null,'new_value'=>$new?json_encode($new):null,'reason'=>$reason,'created_at'=>now()]);
    }
    public function notify(int $id,string $title,string $message,string $link='?page=notifications'): void {
        $this->db->insert('notifications',['user_id'=>$id,'title'=>$title,'message'=>$message,'link'=>$link,'created_at'=>now()]);
    }
    public function notifyHeads(string $title,string $message,string $link): void { foreach($this->db->all("SELECT id FROM users WHERE active=1 AND role IN ('head','admin')") as $u) $this->notify((int)$u['id'],$title,$message,$link); }
    public function conflicts(int $id,string $start,string $end,string $excludeTable='',int $excludeId=0): array {
        $out=[];
        foreach(['schedules'=>"status<>'Cancelled'",'deployments'=>"status<>'Cancelled'",'leave_records'=>"status='Approved'",'availability_records'=>"status IN ('Unavailable','Off Duty')"] as $table=>$condition) {
            $sql="SELECT * FROM $table WHERE nurse_id=? AND starts_at<? AND ends_at>? AND $condition";
            $p=[$id,$end,$start]; if($excludeTable===$table) { $sql.=' AND id<>?'; $p[]=$excludeId; }
            foreach($this->db->all($sql,$p) as $r) $out[]=['type'=>$table,'id'=>$r['id'],'starts_at'=>$r['starts_at'],'ends_at'=>$r['ends_at'],'label'=> match($table) {'schedules'=>'Existing duty','deployments'=>'Existing deployment','leave_records'=>'Approved leave',default=>$r['status']}];
        }
        return $out;
    }
    public function assertFree(int $id,string $start,string $end,string $table='',int $exclude=0): void {
        $c=$this->conflicts($id,$start,$end,$table,$exclude);
        if($c) throw new DomainException('Schedule conflict: '.$c[0]['label'].' from '.$c[0]['starts_at'].' to '.$c[0]['ends_at'].'. Resolve that record first.',409);
    }
    public function schedule(array $d): int {
        return $this->db->transaction(function() use($d) {
            $id=(int)($d['id']??0); $old=$id?$this->record('schedules',$id):null;
            if($old) { $this->own((int)$old['nurse_id']); if($old['status']==='Cancelled') throw new DomainException('Cancelled schedules are retained as history. Create a new schedule.',422); }
            $nurse=$this->nurse($d['nurse_id']??$this->actor['id']); $this->own($nurse);
            [$start,$end]=$this->window($d); $this->assertFree($nurse,$start,$end,'schedules',$id);
            $data=['nurse_id'=>$nurse,'location_id'=>$this->reference('locations',$d['location_id']??0),'duty_type_id'=>$this->reference('duty_types',$d['duty_type_id']??0),'starts_at'=>$start,'ends_at'=>$end,'remarks'=>$this->text($d,'remarks',3000,false),'updated_at'=>now()];
            if($id) $this->db->update('schedules',$id,$data); else $id=$this->db->insert('schedules',$data+['created_by'=>$this->actor['id'],'created_at'=>now(),'status'=>'Scheduled']);
            $this->audit($old?'Schedule updated':'Schedule created','schedules',$id,$old,$data);
            $this->notify($nurse,'Duty schedule '.($old?'updated':'created'),$start.' · '.$this->record('locations',$data['location_id'])['name'],'?page=schedule'); return $id;
        });
    }
    public function cancel(array $d): void {
        $table=$this->choice($d,'table',['schedules','deployments','activities','leave_records','availability_records']);
        $this->db->transaction(function() use($d,$table) {
            $r=$this->record($table,(int)($d['id']??0)); $reason=$this->text($d,'reason',2000);
            if(in_array($table,['deployments','activities'])) $this->requireRole(['admin','head']); else $this->own((int)$r['nurse_id']);
            if(($r['status']??'')==='Cancelled') throw new DomainException('This record is already cancelled.',422);
            if($table==='leave_records' && $r['status']==='Approved') $this->requireRole(['admin','head']);
            if($table==='activities' && $this->db->one("SELECT id FROM deployments WHERE activity_id=? AND status<>'Cancelled'",[$r['id']])) throw new DomainException('Cancel the activity’s deployments first.',409);
            $change=['status'=>'Cancelled']; if($table==='deployments') $change+=['cancelled_at'=>now(),'cancellation_reason'=>$reason];
            $this->db->update($table,(int)$r['id'],$change); $this->audit('Cancelled',$table,(int)$r['id'],$r,$change,$reason);
            if(isset($r['nurse_id'])) $this->notify((int)$r['nurse_id'],'Assignment / availability record cancelled',$reason);
        });
    }
    public function activity(array $d): int {
        $this->requireRole(['admin','head']); return $this->db->transaction(function() use($d) {
            $id=(int)($d['id']??0); $old=$id?$this->record('activities',$id):null;
            if($old && $old['status']==='Cancelled') throw new DomainException('Create a new requirement for a cancelled activity.',422);
            if($id && $this->db->one('SELECT id FROM deployments WHERE activity_id=?',[$id])) throw new DomainException('This requirement has deployment history. Create a replacement requirement to preserve the original details.',409);
            [$start,$end]=$this->window($d);
            $data=['title'=>$this->text($d,'title'),'location_id'=>$this->reference('locations',$d['location_id']??0),'starts_at'=>$start,'ends_at'=>$end,'organizer'=>$this->text($d,'organizer',190,false),'required_nurses'=>$this->positive($d,'required_nurses',1,1000),'required_skill'=>$this->text($d,'required_skill',190,false),'reason_id'=>$this->reference('deployment_reasons',$d['reason_id']??0),'instructions'=>$this->text($d,'instructions',5000,false)];
            if($id) $this->db->update('activities',$id,$data); else $id=$this->db->insert('activities',$data+['created_by'=>$this->actor['id'],'created_at'=>now(),'status'=>'Open']);
            $this->audit($old?'Requirement updated':'Requirement created','activities',$id,$old,$data);
            $this->notifyHeads('Personnel required',$data['title'].' needs '.$data['required_nurses'].' nurse(s).','?page=deployment&activity='.$id); return $id;
        });
    }
    public function candidates(int $activityId): array {
        $this->requireRole(['admin','head']); $a=$this->record('activities',$activityId); $list=[];
        foreach($this->db->all("SELECT u.*,d.name district_name,l.name location_name FROM users u LEFT JOIN districts d ON d.id=u.district_id LEFT JOIN locations l ON l.id=u.location_id WHERE u.role='nurse' ORDER BY u.name") as $u) {
            $reasons=[]; if(!$u['active']) $reasons[]='Inactive account';
            if($a['status']==='Cancelled') $reasons[]='Requirement cancelled';
            if(!$this->record('locations',(int)$a['location_id'])['active']) $reasons[]='Destination is inactive';
            if($a['ends_at']<=now()) $reasons[]='Requirement has ended';
            foreach($this->conflicts((int)$u['id'],$a['starts_at'],$a['ends_at']) as $c) $reasons[]=$c['label'].' ('.$c['starts_at'].' – '.$c['ends_at'].')';
            if($a['required_skill']!=='' && mb_stripos(($u['skills']??'').' '.$u['specialization'],$a['required_skill'])===false) $reasons[]='Required competency not recorded: '.$a['required_skill'];
            unset($u['password_hash']); $list[]=$u+['eligible'=>!$reasons,'reasons'=>$reasons];
        } return $list;
    }
    public function deploy(array $d): int {
        $this->requireRole(['admin','head']); return $this->db->transaction(function() use($d) {
            $a=$this->record('activities',(int)($d['activity_id']??0)); $nurse=$this->nurse($d['nurse_id']??0);
            if($a['status']!=='Open' || $a['ends_at']<=now()) throw new DomainException('This requirement is no longer open for deployment.',422);
            $this->reference('locations',$a['location_id']);
            $count=$this->db->one("SELECT COUNT(*) n FROM deployments WHERE activity_id=? AND status<>'Cancelled'",[$a['id']]);
            if($count['n'] >= $a['required_nurses']) throw new DomainException('This requirement is already fully staffed.',409);
            $u=$this->record('users',$nurse);
            if($a['required_skill']!=='' && mb_stripos(($u['skills']??'').' '.$u['specialization'],$a['required_skill'])===false) throw new DomainException('The required competency is not recorded in this nurse’s profile.',422);
            $this->assertFree($nurse,$a['starts_at'],$a['ends_at']);
            $home=$u['location_id']?$this->record('locations',(int)$u['location_id'])['name']:'No home location recorded';
            $data=['activity_id'=>$a['id'],'nurse_id'=>$nurse,'location_id'=>$a['location_id'],'starts_at'=>$a['starts_at'],'ends_at'=>$a['ends_at'],'reason'=>$this->record('deployment_reasons',(int)$a['reason_id'])['name'],'instructions'=>$a['instructions'],'original_assignment'=>$home,'authorized_by'=>$this->actor['id'],'created_at'=>now(),'status'=>'Assigned'];
            $id=$this->db->insert('deployments',$data); $this->audit('Deployment assigned','deployments',$id,null,$data);
            $this->notify($nurse,'New official duty assignment',$a['title'].' · '.$a['starts_at'].'. Please review and acknowledge.','?page=deployment'); return $id;
        });
    }
    public function acknowledge(array $d): void {
        $this->requireRole(['nurse']); $this->db->transaction(function() use($d) {
            $r=$this->record('deployments',(int)($d['id']??0)); $this->own((int)$r['nurse_id']);
            if($r['status']==='Cancelled') throw new DomainException('This deployment was cancelled.',409);
            if($r['acknowledged_at']) return;
            $v=['acknowledged_at'=>now(),'status'=>'Acknowledged']; $this->db->update('deployments',(int)$r['id'],$v);
            $this->audit('Deployment acknowledged','deployments',(int)$r['id'],$r,$v);
            $this->notify((int)$r['authorized_by'],'Deployment acknowledged',$this->actor['name'].' acknowledged deployment #'.$r['id'].'.','?page=deployment');
        });
    }
    public function leave(array $d,?string $attachment=null): int {
        return $this->db->transaction(function() use($d,$attachment) {
            $nurse=$this->nurse($d['nurse_id']??$this->actor['id']); $this->own($nurse); [$start,$end]=$this->window($d);
            $type=$this->choice($d,'type',['Vacation Leave','Sick Leave','Official Leave','Other Leave','Unavailability']);
            $data=['nurse_id'=>$nurse,'type'=>$type,'starts_at'=>$start,'ends_at'=>$end,'reason'=>$this->text($d,'reason',3000),'attachment'=>$attachment,'created_at'=>now(),'status'=>'Submitted'];
            $id=$this->db->insert('leave_records',$data); $this->audit('Leave submitted','leave_records',$id,null,$data);
            $this->notifyHeads('Leave request for review',$this->record('users',$nurse)['name'].' · '.$start,'?page=leave'); return $id;
        });
    }
    public function reviewLeave(array $d): void {
        $this->requireRole(['admin','head']); $this->db->transaction(function() use($d) {
            $r=$this->record('leave_records',(int)($d['id']??0));
            if($r['status']!=='Submitted') throw new DomainException('This request has already been reviewed.',409);
            $status=$this->choice($d,'status',['Approved','Rejected']);
            if($status==='Approved') $this->assertFree((int)$r['nurse_id'],$r['starts_at'],$r['ends_at'],'leave_records',(int)$r['id']);
            $change=['status'=>$status,'reviewed_by'=>$this->actor['id'],'reviewed_at'=>now(),'review_note'=>$this->text($d,'review_note',2000,false)];
            $this->db->update('leave_records',(int)$r['id'],$change); $this->audit('Leave '.$status,'leave_records',(int)$r['id'],$r,$change);
            $this->notify((int)$r['nurse_id'],'Leave request '.$status,$r['starts_at'].' · '.$change['review_note'],'?page=leave');
        });
    }
    public function availability(array $d): int {
        return $this->db->transaction(function() use($d) {
            $nurse=$this->nurse($d['nurse_id']??$this->actor['id']); $this->own($nurse); [$start,$end]=$this->window($d);
            $status=$this->choice($d,'status',['Available','Off Duty','Unavailable']);
            $this->assertFree($nurse,$start,$end);
            if($this->db->one("SELECT id FROM availability_records WHERE nurse_id=? AND starts_at<? AND ends_at>? AND status<>'Cancelled'",[$nurse,$end,$start])) throw new DomainException('An availability record already covers this interval. Cancel it before replacing it.',409);
            $data=['nurse_id'=>$nurse,'starts_at'=>$start,'ends_at'=>$end,'status'=>$status,'reason'=>$this->text($d,'reason',2000,false),'created_at'=>now()];
            $id=$this->db->insert('availability_records',$data); $this->audit('Availability recorded','availability_records',$id,null,$data); return $id;
        });
    }
    public function saveUser(array $d): int {
        $this->requireRole(['admin']); return $this->db->transaction(function() use($d) {
            $id=(int)($d['id']??0); $old=$id?$this->record('users',$id):null;
            $role=$this->choice($d,'role',['admin','head','nurse']); $active=$this->positive($d,'active',0,1);
            if($id===$this->actor['id'] && (!$active || $role!=='admin')) throw new DomainException('You cannot deactivate or demote your own administrator account.',422);
            if($old && $old['role']!==$role) {
                foreach(['schedules','deployments','leave_records','availability_records'] as $t) if($this->db->one("SELECT id FROM $t WHERE nurse_id=?",[$id])) throw new DomainException('This account has nursing history. Retain its role and create a separate administrative account.',422);
            }
            $email=$this->text($d,'email'); if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.',422);
            $data=['name'=>$this->text($d,'name',120),'email'=>mb_strtolower($email),'role'=>$role,'active'=>$active,'employee_number'=>$this->text($d,'employee_number',60,false)?:null,'designation'=>$this->text($d,'designation',120),'district_id'=>empty($d['district_id'])?null:$this->reference('districts',$d['district_id']),'location_id'=>empty($d['location_id'])?null:$this->reference('locations',$d['location_id']),'employment_status'=>$this->choice($d,'employment_status',['Permanent','Contractual','Job Order','Temporary']),'phone'=>$this->text($d,'phone',40,false),'specialization'=>$this->text($d,'specialization',190,false),'skills'=>$this->text($d,'skills',3000,false)];
            $schoolIds=null;
            if(array_key_exists('school_ids',$d) || isset($d['schools_present'])) {
                if(!is_array($d['school_ids']??[])) throw new DomainException('Select valid school assignments.',422);
                $schoolIds=[];
                $previous=$id?array_column($this->db->all('SELECT location_id FROM nurse_locations WHERE nurse_id=?',[$id]),'location_id'):[];
                foreach($d['school_ids']??[] as $schoolId) {
                    if(!is_scalar($schoolId) || !filter_var($schoolId,FILTER_VALIDATE_INT) || (int)$schoolId<1) throw new DomainException('Select valid school assignments.',422);
                    $school=$this->record('locations',(int)$schoolId);
                    if($school['type']!=='School' || (!$school['active'] && !in_array($schoolId,$previous))) throw new DomainException('Select an active school.',422);
                    $schoolIds[]=(int)$schoolId;
                }
                $schoolIds=array_values(array_unique($schoolIds));
                if($role!=='nurse' && $schoolIds) throw new DomainException('Multiple school assignments are for nursing personnel.',422);
                if($old) $old['school_ids']=$previous;
            }
            if(!$id || !empty($d['password'])) { $password=$this->password($d['password']??''); $data['password_hash']=password_hash($password,PASSWORD_DEFAULT); $data['must_change_password']=1; }
            if($id) $this->db->update('users',$id,$data); else $id=$this->db->insert('users',$data+['created_at'=>now()]);
            if($schoolIds!==null || $role!=='nurse') {
                $this->db->run('DELETE FROM nurse_locations WHERE nurse_id=?',[$id]);
                foreach($schoolIds??[] as $schoolId) $this->db->insert('nurse_locations',['nurse_id'=>$id,'location_id'=>$schoolId]);
                $data['school_ids']=$schoolIds??[];
            }
            $this->audit($old?'Account updated':'Account created','users',$id,$old,$data); return $id;
        });
    }
    public function monthlyNurseSchedules(string $month): array {
        $this->requireRole(['admin','head']);
        $first=DateTimeImmutable::createFromFormat('!Y-m',$month);
        if(!$first || $first->format('Y-m')!==$month) throw new DomainException('Select a valid month.',422);
        return $this->db->all("SELECT u.id,u.name,COUNT(*) duty_count FROM schedules s JOIN users u ON u.id=s.nurse_id WHERE u.role='nurse' AND s.status<>'Cancelled' AND s.starts_at>=? AND s.starts_at<? GROUP BY u.id,u.name ORDER BY duty_count DESC,u.name,u.id",[$first->format('Y-m-d H:i:s'),$first->modify('+1 month')->format('Y-m-d H:i:s')]);
    }
    public function assignedLocations(int $nurseId): array {
        return $this->db->all('SELECT l.* FROM locations l WHERE l.id=(SELECT location_id FROM users WHERE id=?) OR EXISTS (SELECT 1 FROM nurse_locations nl WHERE nl.nurse_id=? AND nl.location_id=l.id) ORDER BY l.name,l.id',[$nurseId,$nurseId]);
    }
    public function password(mixed $raw): string { $p=(string)$raw; if(strlen($p)<12 || strlen($p)>72) throw new DomainException('Use a password between 12 and 72 bytes.',422); return $p; }
    public function profile(array $d,?string $photo=null): void {
        $this->db->transaction(function() use($d,$photo) {
            $old=$this->record('users',(int)$this->actor['id']); $data=['phone'=>$this->text($d,'phone',40,false)];
            if($photo) $data['photo']=$photo;
            if(!empty($d['new_password'])) {
                if(!password_verify((string)($d['current_password']??''),$old['password_hash'])) throw new DomainException('Current password is incorrect.',422);
                $p=$this->password($d['new_password']); if($p!==($d['confirm_password']??'')) throw new DomainException('New passwords do not match.',422);
                if(password_verify($p,$old['password_hash'])) throw new DomainException('Choose a new password different from your current password.',422);
                $data['password_hash']=password_hash($p,PASSWORD_DEFAULT); $data['must_change_password']=0;
            }
            if($old['must_change_password'] && !isset($data['password_hash'])) throw new DomainException('Set a new password to activate your account.',422);
            $this->db->update('users',(int)$old['id'],$data); $this->audit('Profile updated','users',(int)$old['id'],$old,$data);
        });
    }
    public function catalog(array $d): int {
        $this->requireRole(['admin']); return $this->db->transaction(function() use($d) {
            $table=$this->choice($d,'table',['districts','municipalities','locations','duty_types','deployment_reasons','shifts']); $id=(int)($d['id']??0); $old=$id?$this->record($table,$id):null;
            $data=['name'=>$this->text($d,'name',150),'active'=>$this->positive($d,'active',0,1)];
            if($table==='locations') $data+=['type'=>$this->choice($d,'type',['School','SDO Office','District Office','Activity Venue','Training Venue','Sports Venue','Health Activity','Temporary Location','Other']),'district_id'=>empty($d['district_id'])?null:$this->reference('districts',$d['district_id']),'municipality_id'=>empty($d['municipality_id'])?null:$this->reference('municipalities',$d['municipality_id']),'address'=>$this->text($d,'address',2000,false),'contact_person'=>$this->text($d,'contact_person',120,false),'contact_info'=>$this->text($d,'contact_info',150,false),'required_nurses'=>$this->positive($d,'required_nurses',0,1000)];
            if($table==='shifts') foreach(['start_time','end_time'] as $k) { $v=$this->text($d,$k,5); if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$v)) throw new DomainException('Invalid shift time.',422); $data[$k]=$v; }
            if($id) $this->db->update($table,$id,$data); else $id=$this->db->insert($table,$data);
            $this->audit($old?'Configuration updated':'Configuration created',$table,$id,$old,$data); return $id;
        });
    }
    public function settings(array $d): void {
        $this->requireRole(['admin']); $this->db->transaction(function() use($d) {
            foreach(['organization','system_notice'] as $key) { $v=$this->text($d,$key,$key==='organization'?190:2000,$key==='organization'); $old=$this->db->one('SELECT * FROM system_settings WHERE setting_key=?',[$key]); $this->db->run('UPDATE system_settings SET setting_value=? WHERE setting_key=?',[$v,$key]); $this->audit('Settings updated','system_settings',null,$old,['setting_key'=>$key,'setting_value'=>$v]); }
        });
    }
    public function workforce(string $start,string $end): array {
        $users=$this->db->all("SELECT u.id,u.name,u.active,u.district_id,u.location_id,u.specialization,u.skills,d.name district_name,l.name location_name FROM users u LEFT JOIN districts d ON d.id=u.district_id LEFT JOIN locations l ON l.id=u.location_id WHERE u.role='nurse'".($this->manager()?'':' AND u.id='.(int)$this->actor['id']).' ORDER BY u.name');
        foreach($users as &$u) {
            $c=$this->conflicts((int)$u['id'],$start,$end); $u['conflicts']=$c; $u['status']=$u['active']?'Available':'Inactive';
            if($u['active'] && $c) { $types=array_column($c,'type');
                $u['status']=in_array('leave_records',$types)?'On Leave':(in_array('deployments',$types)?'Deployed':(in_array('schedules',$types)?'Scheduled':$c[0]['label']));
                if($u['status']==='Scheduled') foreach($c as $v) if($v['type']==='schedules' && $v['starts_at']<=now() && $v['ends_at']>now()) $u['status']='On Duty';
            }
        } unset($u); return $users;
    }
    public function roster(array $f): array {
        [$start,$end]=$this->window($f); $rows=[];
        foreach(['schedules','deployments'] as $t) {
            $is=$t==='schedules';
            $sql='SELECT r.*,u.name nurse_name,u.district_id,l.name location_name,l.municipality_id,'.($is?'dt.name':'a.title')." duty_name,'$t' source FROM $t r JOIN users u ON u.id=r.nurse_id JOIN locations l ON l.id=r.location_id ".($is?'JOIN duty_types dt ON dt.id=r.duty_type_id':'JOIN activities a ON a.id=r.activity_id').' WHERE r.starts_at<? AND r.ends_at>?';
            $p=[$end,$start];
            if(!$this->manager()) { $sql.=' AND r.nurse_id=?'; $p[]=$this->actor['id']; }
            foreach(['nurse_id'=>'r.nurse_id','district_id'=>'u.district_id','location_id'=>'r.location_id','municipality_id'=>'l.municipality_id'] as $key=>$column) if(!empty($f[$key])) { $sql.=" AND $column=?"; $p[]=(int)$f[$key]; }
            if(!empty($f['duty_type_id'])) { if(!$is) continue; $sql.=' AND r.duty_type_id=?'; $p[]=(int)$f['duty_type_id']; }
            if(!empty($f['status'])) { $sql.=' AND r.status=?'; $p[]=$f['status']; } else $sql.=" AND r.status<>'Cancelled'";
            $rows=array_merge($rows,$this->db->all($sql.' ORDER BY r.starts_at LIMIT 5000',$p));
        }
        usort($rows,fn($a,$b)=>strcmp($a['starts_at'],$b['starts_at'])?:strcmp($a['nurse_name'],$b['nurse_name'])); return $rows;
    }
}
