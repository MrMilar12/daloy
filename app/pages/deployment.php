<?php
if($page==='activities') {
    page_heading('PERSONNEL REQUIREMENTS','Activities & events','Plan official activities, set staffing needs, and coordinate assignments.',add_link('?page=activities&new=1','New requirement'));
    if(isset($_GET['view'])) {
        $viewId=(int)$_GET['view'];
        $viewActivity=$service->record('activities',$viewId);
        $viewLocation=!empty($viewActivity['location_id'])?$service->record('locations',(int)$viewActivity['location_id']):['name'=>$viewActivity['destination_name']?:'Location unavailable','address'=>$viewActivity['destination_address']??''];
        $viewAssignments=$db->all("SELECT d.*,u.name nurse_name,u.employee_number,u.designation,COALESCE(l.name,a.destination_name) location_name,h.name authorized_name FROM deployments d JOIN users u ON u.id=d.nurse_id JOIN users h ON h.id=d.authorized_by JOIN activities a ON a.id=d.activity_id LEFT JOIN locations l ON l.id=d.location_id WHERE d.activity_id=? ORDER BY d.created_at DESC,d.id DESC",[$viewId]);
        $viewAssigned=count(array_filter($viewAssignments,fn($assignment)=>$assignment['status']!=='Cancelled'));
        ?><dialog class="record-modal" data-record-modal aria-labelledby="activity-view-title">
            <section class="record-modal-card">
                <a class="record-modal-close" href="?page=activities" aria-label="Close activity view">×</a>
                <div class="record-modal-heading"><span class="record-modal-mark" aria-hidden="true"><?=icon('nurses')?></span><div><span class="eyebrow">ACTIVITY VIEW</span><h2 id="activity-view-title"><?=e($viewActivity['title'])?></h2><p><?=e($viewLocation['name'])?> · <?=$viewAssigned?> assigned nurses</p></div></div>
                <dl class="record-view-grid"><div><dt>Destination</dt><dd><?=e($viewLocation['name'])?></dd></div><div><dt>Schedule</dt><dd><?=e(range_label($viewActivity))?></dd></div><div><dt>Organizer</dt><dd><?=e($viewActivity['organizer']?:'—')?></dd></div><div><dt>Required competency</dt><dd><?=e($viewActivity['required_skill']?:'No specific requirement')?></dd></div></dl>
                <?php if(!$viewAssignments): empty_state('No assigned nurses','No nurse has been deployed to this activity yet.'); else: ?><div class="table-wrap" style="margin-top:16px"><table><thead><tr><th>Nurse</th><th>Designation</th><th>Authorization</th><th>Status</th><th>Acknowledgment</th></tr></thead><tbody><?php foreach($viewAssignments as $assignment): ?><tr><td><strong><?=e($assignment['nurse_name'])?></strong><small><?=e($assignment['employee_number']?:'No employee number')?></small></td><td><?=e($assignment['designation']?:'—')?></td><td><?=e($assignment['authorized_name'])?><small><?=e(dt($assignment['created_at']))?></small></td><td><?=badge($assignment['status'])?></td><td><?=e($assignment['acknowledged_at']?dt($assignment['acknowledged_at']):($assignment['status']==='Cancelled'?'—':'Awaiting acknowledgment'))?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
                <div class="form-actions" style="margin-top:18px"><a class="button" href="?page=activities">Close</a><?php if($viewActivity['status']==='Open' && $viewActivity['ends_at']>now() && $viewAssigned<(int)$viewActivity['required_nurses']): ?><a class="button primary" href="?page=deployment&activity=<?=$viewId?>">Review candidates</a><?php elseif($viewActivity['status']==='Open'): ?><span class="muted"><?=$viewActivity['ends_at']<=now()?'This requirement has ended. Create a new requirement to assign nurses.':'This requirement is fully staffed.'?></span><?php endif ?></div>
            </section>
        </dialog><?php
    }
    if(isset($_GET['new']) || isset($_GET['edit'])) {
        $r=isset($_GET['edit'])?$service->record('activities',(int)$_GET['edit']):[]; if($error && ($_POST['action']??'')==='activity') $r=array_merge($r,$_POST);
        $destinations=array_column($db->all("SELECT id,CONCAT(name,' — ',type) name FROM locations WHERE active=1 ORDER BY name,type"),'name','id');
        ?><dialog class="activity-editor-modal" data-activity-modal aria-labelledby="activity-form-title">
            <section class="activity-editor-card">
                <a class="activity-modal-close" href="?page=activities" aria-label="Close requirement form">×</a>
                <div class="activity-form-heading">
                    <span class="activity-form-mark" aria-hidden="true"><?=icon('activities')?></span>
                    <div><span class="eyebrow">PERSONNEL REQUIREMENT</span><h2 id="activity-form-title"><?=isset($r['id'])?'Edit requirement':'Request nursing personnel'?></h2><p><?=isset($r['id'])?'Update the activity details and staffing request.':'Create an official activity and coordinate nursing support.'?></p></div>
                </div>
                <form method="post" enctype="multipart/form-data" class="activity-form-fields form-grid" data-validate>
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="action" value="activity">
                    <input type="hidden" name="return" value="?page=activities">
                    <?php if(isset($r['id'])): ?><input type="hidden" name="id" value="<?=e($r['id'])?>"><?php endif ?>
                    <section class="activity-details-panel">
                        <div class="activity-form-section-heading">
                            <span class="activity-location-mark" aria-hidden="true"><?=icon('activities')?></span>
                            <div><h3>Activity details</h3><p>Describe the event and staffing requirement.</p></div>
                        </div>
                        <div class="activity-fields-grid">
                            <?php input('title','Activity / requirement title',$r['title']??''); ?>
                            <?php input('organizer','Organizer',$r['organizer']??'','text',false); ?>
                            <?php input('required_nurses','Nurses required',$r['required_nurses']??1,'number',true,'min="1" max="1000"'); ?>
                            <?php select_field('reason_id','Deployment reason',options('deployment_reasons'),$r['reason_id']??''); ?>
                            <?php input('required_skill','Required competency (optional)',$r['required_skill']??'','text',false); ?>
                            <label class="span-2">Reason, instructions & remarks<textarea name="instructions" rows="4" maxlength="5000"><?=e($r['instructions']??'')?></textarea></label>
                        </div>
                    </section>
                    <div class="activity-form-side">
                        <section class="activity-destination-panel">
                            <div class="activity-form-section-heading">
                                <span class="activity-location-mark" aria-hidden="true"><?=icon('locations')?></span>
                                <div><h3>Destination / venue</h3><p>Choose a directory location or enter a custom venue.</p></div>
                            </div>
                            <?php select_field('location_id','Registered location', $destinations,$r['location_id']??'',false); ?>
                            <label>Destination name (if outside the directory)<input type="text" name="destination_name" value="<?=e($r['destination_name']??'')?>" maxlength="190" placeholder="e.g. Community gymnasium"></label>
                            <label>Destination address / details<input type="text" name="destination_address" value="<?=e($r['destination_address']??'')?>" maxlength="1000" placeholder="Street, barangay, or landmark"></label>
                        </section>
                        <section class="activity-schedule-panel">
                            <div class="activity-form-section-heading">
                                <span class="activity-schedule-mark" aria-hidden="true"><?=icon('calendar')?></span>
                                <div><h3>Schedule</h3><p>Set the exact coverage interval.</p></div>
                            </div>
                            <div class="activity-fields-grid">
                                <?php input('starts_at','Starts at',str_replace(' ','T',substr($r['starts_at']??date('Y-m-d').' 08:00',0,16)),'datetime-local'); ?>
                                <?php input('ends_at','Ends at',str_replace(' ','T',substr($r['ends_at']??date('Y-m-d').' 17:00',0,16)),'datetime-local'); ?>
                            </div>
                        </section>
                    </div>
                    <div class="form-actions span-2"><a href="?page=activities" class="button">Cancel</a><button class="primary" type="submit"><?=isset($r['id'])?'Save changes':'Save requirement'?></button></div>
                </form>
            </section>
        </dialog><?php
    }
    $status=(string)($_GET['status']??'Open'); if(!in_array($status,['Open','Ended','Cancelled'],true)) $status='Open';
    ?><div class="tabs"><a class="<?=$status==='Open'?'selected':''?>" href="?page=activities">Open requirements</a><a class="<?=$status==='Ended'?'selected':''?>" href="?page=activities&status=Ended">Ended</a><a class="<?=$status==='Cancelled'?'selected':''?>" href="?page=activities&status=Cancelled">Cancelled</a></div><?php
    $activityFilter=match($status) {'Cancelled'=>['a.status=?',['Cancelled']],'Ended'=>["a.status='Open' AND a.ends_at<=?",[now()]],default=>["a.status='Open' AND a.ends_at>?",[now()]]};
    $rows=$db->all("SELECT a.*,COALESCE(l.name,a.destination_name,'Location unavailable') location_name,r.name reason_name,(SELECT COUNT(*) FROM deployments d WHERE d.activity_id=a.id AND d.status<>'Cancelled') assigned FROM activities a LEFT JOIN locations l ON l.id=a.location_id JOIN deployment_reasons r ON r.id=a.reason_id WHERE ".$activityFilter[0]." ORDER BY a.starts_at DESC LIMIT 26 OFFSET $offset",$activityFilter[1]);
    ?><div class="activity-grid"><?php if(!$rows) empty_state($status==='Ended'?'No ended requirements':'No personnel requirements',$status==='Open'?'Create an activity or staffing request to find available nurses.':'There are no requirements in this category.'); foreach(array_slice($rows,0,25) as $r): $activityEnded=$r['status']==='Open' && $r['ends_at']<=now(); $activityStaffed=(int)$r['assigned']>=(int)$r['required_nurses']; $activityStatus=$r['status']==='Cancelled'?'Cancelled':($activityEnded?'Ended':($activityStaffed?'Fully staffed':'Needs '.((int)$r['required_nurses']-(int)$r['assigned']))); ?><article class="card activity-card"><div class="section-heading"><span class="activity-date"><strong><?=date('d',strtotime($r['starts_at']))?></strong><span><?=date('M',strtotime($r['starts_at']))?></span></span><?=badge($activityStatus)?></div><h2><?=e($r['title'])?></h2><p><?=e($r['location_name'])?></p><p><?=range_label($r)?></p><dl class="detail-list"><dt>Organizer</dt><dd><?=e($r['organizer']?:'—')?></dd><dt>Reason</dt><dd><?=e($r['reason_name'])?></dd><dt>Required competency</dt><dd><?=e($r['required_skill']?:'No specific requirement')?></dd><dt>Staffing</dt><dd><?=$r['assigned']?> / <?=$r['required_nurses']?> assigned</dd></dl><p><?=nl2br(e($r['instructions']))?></p><div class="card-actions"><a class="button small" href="?page=activities&view=<?=$r['id']?>">View</a><?php if(!$activityEnded && $r['status']==='Open' && !$activityStaffed): ?><a class="button primary small" href="?page=deployment&activity=<?=$r['id']?>">Review candidates →</a><?php elseif($activityEnded): ?><span class="muted">Ended. Create a new requirement to assign nurses.</span><?php elseif($activityStaffed && $r['status']==='Open'): ?><span class="muted">Fully staffed</span><?php endif ?><?php if($r['status']!=='Cancelled'): ?><a href="?page=activities&edit=<?=$r['id']?>">Edit</a><?php cancel_control('activities',$r,'?page=activities'); endif ?></div></article><?php endforeach ?></div><?php pagination(count($rows),$pageNum); return;
}
page_heading('COORDINATE & DEPLOY','Deployment center',$service->manager()?'Find the right personnel. Review eligibility. Confirm official assignments.':'Review and acknowledge your official duty assignments.',$service->manager()?add_link('?page=activities&new=1','New requirement'):'');
if($service->manager()) {
    $requirements=$db->all("SELECT a.id,a.title FROM activities a WHERE a.status='Open' AND a.ends_at>? AND (SELECT COUNT(*) FROM deployments d WHERE d.activity_id=a.id AND d.status<>'Cancelled')<a.required_nurses ORDER BY a.starts_at DESC",[now()]);
    if($requirements): ?><form method="get" class="filters"><input type="hidden" name="page" value="deployment"><?php select_field('activity','Select a personnel requirement',array_column($requirements,'title','id'),$_GET['activity']??'',false); ?><button class="primary">Find candidates</button><a href="?page=deployment">All deployments</a></form><?php else: empty_state('No assignable requirements','There are no upcoming open requirements with remaining staffing capacity. Create a new requirement to deploy nurses.'); endif;
    if(!empty($_GET['activity'])) {
        $a=$service->record('activities',(int)$_GET['activity']); $location=!empty($a['location_id'])?$service->record('locations',(int)$a['location_id']):['name'=>$a['destination_name']?:'Location unavailable']; $assigned=$db->one("SELECT COUNT(*) n FROM deployments WHERE activity_id=? AND status<>'Cancelled'",[$a['id']])['n'];
        ?><section class="requirement-summary card"><div><span class="eyebrow">DEPLOYMENT REQUIREMENT #<?=$a['id']?></span><h2><?=e($a['title'])?></h2><p><?=e($location['name'])?> · <?=e(dt($a['starts_at']))?> – <?=e(dt($a['ends_at']))?></p><p><?=nl2br(e($a['instructions']))?></p><p><strong>Required competency:</strong> <?=e($a['required_skill']?:'No specific requirement')?></p></div><div class="staffing-count"><strong><?=$assigned?><span>/<?=$a['required_nurses']?></span></strong><small>NURSES ASSIGNED</small></div></section>
        <?php $assignmentBlock=$a['status']!=='Open'?'Requirement cancelled':($a['ends_at']<=now()?'Requirement has ended':($assigned>=$a['required_nurses']?'Fully staffed':'')); ?>
        <dialog class="deployment-candidates-modal" data-candidates-modal aria-labelledby="candidate-modal-title">
            <section class="deployment-candidates-card">
                <a class="deployment-candidates-close" href="?page=deployment" aria-label="Close candidate list">×</a>
                <div class="deployment-candidates-heading">
                    <span class="deployment-candidates-mark" aria-hidden="true"><?=icon('nurses')?></span>
                    <div><span class="eyebrow">DEPLOYMENT REQUIREMENT #<?=$a['id']?></span><h2 id="candidate-modal-title">Potential deployment candidates</h2><p>Eligibility includes the full requested interval. The authorized supervisor makes the final decision.</p></div>
                </div>
                <div class="deployment-candidates-summary"><div><strong><?=e($a['title'])?></strong><small><?=e($location['name'])?></small></div><div><strong><?=$assigned?> / <?=$a['required_nurses']?></strong><small>Nurses assigned</small></div></div>
                <div class="table-wrap"><table><thead><tr><th>Nurse / home assignment</th><th>Competencies</th><th>Eligibility & conflicts</th><th>Action</th></tr></thead><tbody><?php foreach($service->candidates((int)$a['id']) as $c): ?><tr><td><div class="person"><?=avatar($c['name'])?><div><strong><?=e($c['name'])?></strong><small><?=e($c['location_name']?:'No home location')?></small></div></div></td><td><?=e($c['specialization'])?><small><?=e($c['skills'])?></small></td><td><?=badge($c['unavailable']?'Unavailable':($c['eligible']?'Available':'Not eligible'))?><?php foreach($c['reasons'] as $reason): ?><small><?=e($reason)?></small><?php endforeach ?></td><td><?php if($c['eligible'] && $assignmentBlock===''): ?><details class="deploy-confirm"><summary class="button small primary">Review & deploy</summary><div class="confirm-body"><h3>Confirm official deployment</h3><p><strong><?=e($c['name'])?></strong><br><?=e($a['title'])?><br><?=e($location['name'])?><br><?=e(dt($a['starts_at']))?> – <?=e(dt($a['ends_at']))?></p><p><?=e($service->record('deployment_reasons',(int)$a['reason_id'])['name'])?></p><p><?=nl2br(e($a['instructions']))?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="deploy"><input type="hidden" name="activity_id" value="<?=$a['id']?>"><input type="hidden" name="nurse_id" value="<?=$c['id']?>"><input type="hidden" name="return" value="?page=deployment&activity=<?=$a['id']?>"><button class="primary">Confirm deployment</button></form></div></details><?php else: ?><span class="muted"><?=e($assignmentBlock?:'Not eligible')?></span><?php endif ?></td></tr><?php endforeach ?></tbody></table></div>
                <div class="deployment-candidates-footer"><a class="button" href="?page=deployment">Close candidates</a></div>
            </section>
        </dialog><?php
    }
}
$where=$service->manager()?'1=1':'d.nurse_id='.(int)$user['id']; $params=[]; if(!empty($_GET['activity'])) { $where.=' AND d.activity_id=?'; $params[]=(int)$_GET['activity']; }
$rows=$db->all("SELECT d.*,u.name nurse_name,a.title,COALESCE(l.name,a.destination_name) location_name,h.name authorized_name FROM deployments d JOIN users u ON u.id=d.nurse_id JOIN users h ON h.id=d.authorized_by JOIN activities a ON a.id=d.activity_id LEFT JOIN locations l ON l.id=d.location_id WHERE $where ORDER BY d.created_at DESC,d.id DESC LIMIT 26 OFFSET $offset",$params);
?><section class="card"><div class="section-heading"><h2>Assignments & deployment history</h2><span class="muted">Original records are retained</span></div><?php if(!$rows): empty_state('No deployments yet','Confirmed assignments and acknowledgments will appear here.'); else: ?><div class="table-wrap"><table><thead><tr><th>Nurse / activity</th><th>Destination & time</th><th>Authorization</th><th>Acknowledgment</th><th>Details / action</th></tr></thead><tbody><?php foreach(array_slice($rows,0,25) as $r): ?><tr><td><strong><?=e($r['nurse_name'])?></strong><small><?=e($r['title'])?></small><small>Deployment #<?=$r['id']?></small></td><td><?=e($r['location_name'])?><?=range_label($r)?></td><td><?=e($r['authorized_name'])?><small><?=e(dt($r['created_at']))?></small></td><td><?=badge($r['status'])?><small><?=$r['acknowledged_at']?e(dt($r['acknowledged_at'])):($r['status']==='Cancelled'?'—':'Awaiting acknowledgment')?></small></td><td><details><summary>Assignment details</summary><dl class="detail-list"><dt>Original home assignment</dt><dd><?=e($r['original_assignment'])?></dd><dt>Reason</dt><dd><?=e($r['reason'])?></dd><dt>Instructions</dt><dd><?=nl2br(e($r['instructions']))?></dd><?php if($r['status']==='Cancelled'): ?><dt>Cancellation</dt><dd><?=e($r['cancellation_reason'])?> · <?=e(dt($r['cancelled_at']))?></dd><?php endif ?></dl></details><?php if(!$service->manager() && !$r['acknowledged_at'] && $r['status']!=='Cancelled') simple_action('acknowledge',(int)$r['id'],'Acknowledge assignment','?page=deployment'); if($service->manager()) cancel_control('deployments',$r,'?page=deployment'); ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section><?php pagination(count($rows),$pageNum);
