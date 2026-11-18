<?php
if($page==='settings') {
    page_heading('SYSTEM ADMINISTRATION','Workspace settings','Manage the information shown across the workspace.');
    $settings=array_column($db->all('SELECT * FROM system_settings'),'setting_value','setting_key');
    ?>
    <section class="settings-page-intro">
        <span class="settings-page-intro-mark" aria-hidden="true"><?=icon('settings')?></span>
        <div class="settings-page-intro-copy">
            <span class="eyebrow">ADMIN CONTROL CENTER</span>
            <h2>Keep your workspace in good order.</h2>
            <p>Update organization details, manage system updates, and review your server settings.</p>
        </div>
        <div class="settings-page-intro-tag"><span><?=icon('profile')?></span><div><strong>Administrator tools</strong><small>Workspace controls</small></div></div>
    </section>
    <div class="settings-panels">
        <section class="card editor settings-workspace-panel">
            <div class="settings-panel-heading">
                <span class="settings-panel-mark" aria-hidden="true"><?=icon('settings')?></span>
                <div><span class="eyebrow">WORKSPACE PROFILE</span><h2>Organization details</h2><p>These details appear throughout the DALOY workspace.</p></div>
            </div>
            <?php form_start('settings','?page=settings'); ?>
                <?php input('organization','Organization name',$settings['organization']); ?>
                <?php textarea('system_notice','Workspace notice',$settings['system_notice']); ?>
                <p class="settings-form-hint">Use the notice for important updates that staff should see when they sign in.</p>
                <?php form_end('?page=settings','Save settings'); ?>
        </section>
        <?php if($user['role']==='admin'): ?>
            <section class="card settings-updater-panel github-updater" data-github-updater>
                <div class="settings-panel-heading">
                    <span class="settings-panel-mark settings-update-mark" aria-hidden="true"><?=icon('deployment')?></span>
                    <div><span class="eyebrow">SYSTEM UPDATES</span><h2>Keep DALOY current</h2><p>Check for approved updates from the official repository.</p></div>
                </div>
                <div class="settings-updater-repository">
                    <span class="settings-repository-mark" aria-hidden="true"><?=icon('settings')?></span>
                    <div><strong>MrMilar12/daloy</strong><small>Official repository · main branch</small></div>
                    <span class="github-updater-status" data-github-status>Ready</span>
                </div>
                <div class="github-updater-actions settings-updater-actions">
                    <button type="button" class="primary" data-github-check>Check for updates</button>
                    <a href="https://github.com/MrMilar12/daloy" target="_blank" rel="noopener">Open repository <span aria-hidden="true">↗</span></a>
                </div>
                <div class="update-progress" data-update-progress hidden><div class="progress-track"><span data-update-progress-bar></span></div><div class="progress-meta"><strong data-update-progress-label>Preparing update…</strong><span data-update-progress-percent>0%</span></div></div>
                <div class="github-updater-result" data-github-result hidden></div>
                <dialog class="github-update-dialog" data-github-update-dialog aria-labelledby="github-update-title">
                    <section class="github-update-dialog-card">
                        <button type="button" class="github-update-dialog-close" data-github-update-close aria-label="Close update notice">×</button>
                        <div class="github-update-dialog-heading">
                            <span class="github-update-dialog-mark" aria-hidden="true">↻</span>
                            <div><span class="eyebrow">VERIFIED SYSTEM UPDATE</span><h2 id="github-update-title">New system update found</h2><p>A verified DALOY update is ready to install.</p></div>
                        </div>
                        <div class="github-update-dialog-commit">
                            <div class="github-update-dialog-meta"><span>Latest commit</span><code data-github-update-commit></code></div>
                            <div class="github-update-dialog-message" data-github-update-message></div>
                        </div>
                        <div class="github-update-dialog-actions">
                            <button type="button" data-github-update-close>Not now</button>
                            <button type="button" class="primary" data-github-install>Install update</button>
                        </div>
                    </section>
                </dialog>
            </section>
        <?php endif ?>
    </div>
    <section class="card settings-server-panel">
        <div class="settings-panel-heading">
            <span class="settings-panel-mark settings-server-mark" aria-hidden="true"><?=icon('calendar')?></span>
            <div><span class="eyebrow">SYSTEM INFORMATION</span><h2>Server configuration &amp; backups</h2><p>Review the environment details needed to maintain this installation.</p></div>
        </div>
        <div class="settings-server-grid">
            <div><span>TIME ZONE</span><strong>Asia/Manila</strong><small>Philippine Standard Time · UTC+8</small></div>
            <div><span>SESSION TIMEOUT</span><strong><?=round($config['session_timeout']/60)?> minutes</strong><small>Inactive sessions require sign-in again.</small></div>
            <div><span>CONFIGURATION FILE</span><strong><code>config.local.php</code></strong><small>Credentials and security settings are stored here.</small></div>
        </div>
        <p class="settings-backup-note"><span aria-hidden="true"><?=icon('availability')?></span><span>Back up the database and protected uploads together. Follow the recovery procedure in <code>README.md</code>.</span></p>
    </section>
    <?php return;
}
$tables=['districts'=>'Districts / areas','municipalities'=>'Municipalities','duty_types'=>'Duty types','deployment_reasons'=>'Deployment reasons','shifts'=>'Duty shifts'];
$table=$page==='locations'?'locations':(string)($_GET['tab']??'districts');
if($page!=='locations' && !array_key_exists($table,$tables)) throw new DomainException('Unknown configuration section.',404);
$return=$page==='locations'?'?page=locations':'?page=configuration&tab='.$table;
$eligible=[];
if($table==='locations') {
    $snapshot=json_decode(file_get_contents(ROOT.'/database/aurora-schools.json'),true,512,JSON_THROW_ON_ERROR);
    $eligible=array_values(array_unique(array_column(array_filter($snapshot['schools'],fn($school)=>!empty($school['levels']) && !empty($school['public_inventory'])),'name')));
}
page_heading('ORGANIZATION & RESOURCES',$page==='locations'?'Destinations & venues':'Configuration',$page==='locations'?'Manage schools, offices, and other destinations available for duty assignments.':'Adapt DALOY to your organization without changing source code.',$user['role']==='admin'?add_link($return.'&new=1',$page==='locations'?'Add destination or venue':'Add record'):'');
if($page==='configuration'): ?><div class="tabs"><?php foreach($tables as $key=>$name): ?><a class="<?=$table===$key?'selected':''?>" href="?page=configuration&tab=<?=$key?>"><?=e($name)?></a><?php endforeach ?></div><?php endif;
if($user['role']==='admin' && (isset($_GET['new'])||isset($_GET['edit']))) {
    $r=isset($_GET['edit'])?$service->record($table,(int)$_GET['edit']):[]; if($error && ($_POST['action']??'')==='catalog') $r=array_merge($r,$_POST);
    ?><dialog class="record-modal" data-record-modal aria-labelledby="record-form-title"><section class="card editor record-modal-card"><a class="record-modal-close" href="<?=e($return)?>" aria-label="Close record form">×</a><div class="record-modal-heading"><span class="record-modal-mark"><?=icon($table==='locations'?'locations':'settings')?></span><div><span class="eyebrow">ORGANIZATION & RESOURCES</span><h2 id="record-form-title"><?=isset($r['id'])?'Edit record':($table==='locations'?'Add destination or venue':'New record')?></h2><p>Keep your organization’s resource directory current.</p></div></div><?php form_start('catalog',$return,$r); ?><input type="hidden" name="table" value="<?=e($table)?>"><?php
    input('name',$table==='locations'?'Destination / venue name':'Name',$r['name']??'','text',true,'maxlength="150"'.($table==='locations'?' list="aurora-school-names" autocomplete="off" placeholder="Enter a school, office, or venue name"':''));
    if($table==='locations') {
        $schools=$db->all("SELECT l.id,l.name,m.name municipality_name FROM locations l LEFT JOIN municipalities m ON m.id=l.municipality_id WHERE l.type='School' ORDER BY l.name,l.id");
        ?><datalist id="aurora-school-names"><?php foreach($schools as $school): if(!in_array($school['name'],$eligible,true)) continue; ?><option value="<?=e($school['name'])?>" data-location-id="<?=$school['id']?>"><?=e($school['municipality_name'])?></option><?php endforeach ?></datalist><?php
    }
    select_field('active','Status',[1=>'Active',0=>'Inactive'],$r['active']??1);
    if($table==='locations') { select_field('type','Destination type',choices(['School','SDO Office','District Office','Activity Venue','Training Venue','Sports Venue','Health Activity','Temporary Location','Other']),$r['type']??'School'); select_field('district_id','District / area',options('districts'),$r['district_id']??'',false); select_field('municipality_id','Municipality',options('municipalities'),$r['municipality_id']??'',false); input('required_nurses','Daily coverage target (0 = not monitored)',$r['required_nurses']??0,'number',true,'min="0" max="1000"'); input('contact_person','Contact person',$r['contact_person']??'','text',false); input('contact_info','Contact information',$r['contact_info']??'','text',false); textarea('address','Address / description',$r['address']??''); }
    if($table==='shifts') { input('start_time','Start time',$r['start_time']??'08:00','time'); input('end_time','End time',$r['end_time']??'17:00','time'); echo '<p class="help span-2">An end time at or before the start time represents an overnight shift.</p>'; }
    form_end($return); ?></section></dialog><?php
}
$q=trim((string)($_GET['q']??''));
$municipality=(string)($_GET['municipality_id']??'');
$locationType=(string)($_GET['location_type']??'');
?><form class="filters" method="get"><input type="hidden" name="page" value="<?=e($page)?>"><?php if($page==='configuration'): ?><input type="hidden" name="tab" value="<?=e($table)?>"><?php endif; input('q',$page==='locations'?'Search destinations, venues, or address':'Search names',$q,'search',false); if($page==='locations') { select_field('municipality_id','Municipality',options('municipalities','1=1')+['unknown'=>'Not specified in source'],$municipality,false); select_field('location_type','Destination type',['School'=>'Schools','venues'=>'Offices & venues'],$locationType,false); } ?><button>Search</button><a href="<?=e($return)?>">Reset</a></form><?php
$sql=$table==='locations'?'SELECT t.*,d.name district_name,m.name municipality_name FROM locations t LEFT JOIN districts d ON d.id=t.district_id LEFT JOIN municipalities m ON m.id=t.municipality_id':"SELECT t.* FROM $table t";
$where='t.name LIKE ?'; $params=['%'.$q.'%'];
if($table==='locations') {
    $where='(t.name LIKE ? OR t.address LIKE ?)'; $params[]='%'.$q.'%';
    $where.=" AND (t.type<>'School' OR t.name IN (".implode(',',array_fill(0,count($eligible),'?'))."))";
    $params=[...$params,...$eligible];
    if($municipality==='unknown') $where.=' AND t.municipality_id IS NULL';
    elseif($municipality!=='') { $where.=' AND t.municipality_id=?'; $params[]=(int)$municipality; }
    if($locationType==='School') $where.=" AND t.type='School'";
    elseif($locationType==='venues') $where.=" AND t.type<>'School'";
    $total=(int)$db->one("SELECT COUNT(*) n FROM locations t WHERE $where",$params)['n'];
    ?><p class="muted"><?=number_format($total)?> matching locations · 25 per page. School listings and Name suggestions include public elementary and high schools in DepEd's Aurora inventory.</p><p class="help">Sources: <a href="https://ebeis.deped.gov.ph/beis/reports_info/masterlist" target="_blank" rel="noopener noreferrer">DepEd school masterlist</a> and <a href="https://nid.deped.gov.ph/public-dashboard/region/Region%20III/division/Aurora" target="_blank" rel="noopener noreferrer">Aurora inventory directory</a>, retrieved September 30, 2026. Records may include former school names and IDs. Operating status is not supplied; “Active” means available for scheduling in DALOY.</p><?php
}
$rows=$db->all($sql." WHERE $where ORDER BY t.name,t.id LIMIT 26 OFFSET $offset",$params);
?><section class="card"><?php if(!$rows): empty_state('No records found','Add a record to configure your organization.'); else: ?><div class="table-wrap"><table><thead><tr><th>Name</th><?php if($table==='locations'): ?><th>District / municipality</th><th>Contact & coverage</th><?php elseif($table==='shifts'): ?><th>Duty times</th><?php endif ?><th>Status</th><?php if($user['role']==='admin'): ?><th>Action</th><?php endif ?></tr></thead><tbody><?php foreach(array_slice($rows,0,25) as $r): ?><tr><td><strong><?=e($r['name'])?></strong><?php if($table==='locations'): ?><small><?=e($r['type'])?></small><small><?=e($r['address'])?></small><?php endif ?></td><?php if($table==='locations'): ?><td><?=e($r['district_name']?:'—')?><small><?=e($r['municipality_name'])?></small></td><td><?=e($r['contact_person'])?><small><?=e($r['contact_info'])?></small><small>Coverage target: <?=$r['required_nurses']?> nurse(s)</small></td><?php elseif($table==='shifts'): ?><td><?=e($r['start_time'].' – '.$r['end_time'])?><?=$r['end_time']<=$r['start_time']?' (+1 day)':''?></td><?php endif ?><td><?=badge($r['active']?'Active':'Inactive')?></td><?php if($user['role']==='admin'): ?><td><a href="<?=e($return)?>&edit=<?=$r['id']?>">Edit</a></td><?php endif ?></tr><?php endforeach ?></tbody></table></div><?php endif ?></section><?php pagination(count($rows),$pageNum);
