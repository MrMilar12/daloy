<?php
if ($page==='settings') {
    page_heading('System administration','Workspace settings','Manage the information shown across the workspace.');
    $settings = array_column($db->all('SELECT * FROM system_settings'),'setting_value','setting_key');
    ?>
    <div class="workspace-settings">
        <?php if ($user['role']==='admin'): ?>
            <section class="card settings-updates" data-github-updater aria-labelledby="settings-updates-title">
                <div class="settings-updates-banner">
                    <span class="settings-panel-icon"><?=icon('settings')?></span>
                    <div>
                        <span class="settings-panel-kicker">Developer center</span>
                        <h2 id="settings-updates-title">Keep DALOY current</h2>
                        <p>Review and install approved updates from the official DALOY repository.</p>
                    </div>
                </div>
                <div class="settings-updates-body">
                    <div class="settings-update-toolbar">
                        <div class="settings-update-buttons">
                            <button type="button" class="primary" data-github-check>Check for updates</button>
                            <a class="button" href="https://github.com/MrMilar12/daloy" target="_blank" rel="noopener">Open repository</a>
                        </div>
                        <span class="settings-update-status" data-github-status role="status"></span>
                    </div>
                    <div class="update-progress" data-update-progress hidden>
                        <label for="settings-install-progress">Installing update…</label>
                        <progress id="settings-install-progress"></progress>
                    </div>
                    <div class="github-updater-result" data-github-result hidden></div>
                </div>
            </section>
        <?php endif ?>
        <div class="settings-panels">
            <section class="card editor settings-panel" aria-labelledby="settings-organization-title">
                <div class="settings-panel-heading">
                    <span class="settings-panel-icon"><?=icon('nurses')?></span>
                    <h2 id="settings-organization-title">Organization details</h2>
                </div>
                <?php form_start('settings','?page=settings'); ?>
                <?php input('organization','Organization name',$settings['organization']); ?>
                <?php textarea('system_notice','Workspace notice',$settings['system_notice']); ?>
                <?php form_end('?page=settings'); ?>
            </section>
            <section class="card settings-panel" aria-labelledby="settings-server-title">
                <div class="settings-panel-heading">
                    <span class="settings-panel-icon"><?=icon('reports')?></span>
                    <h2 id="settings-server-title">Server configuration &amp; backups</h2>
                </div>
                <dl class="settings-server-facts">
                    <div><dt>Timezone</dt><dd><?=e($config['timezone'] ?? 'Asia/Manila')?></dd></div>
                    <div><dt>Session timeout</dt><dd><?=round($config['session_timeout']/60)?> minutes</dd></div>
                </dl>
                <p>Manage database credentials, secure cookies, and the session timeout in <code>config.local.php</code>.</p>
                <p>Follow the backup procedure in <code>README.md</code> to back up the database and protected uploads together.</p>
            </section>
        </div>
    </div>
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
