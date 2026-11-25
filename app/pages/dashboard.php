<?php
$date=(string)($_GET['date']??date('Y-m-d')); [$start,$end]=$service->window(['starts_at'=>$date.' 00:00','ends_at'=>date('Y-m-d',strtotime($date.' +1 day')).' 00:00']);
$workforce=$service->workforce($start,$end); $counts=array_count_values(array_column(array_filter($workforce,fn($u)=>$u['active']),'status'));
$active=count(array_filter($workforce,fn($u)=>$u['active'])); $available=$counts['Available']??0;
$roster=$service->roster(['starts_at'=>$start,'ends_at'=>$end]);
$districtOverview=[]; $unassignedDistrictNurses=0;
if($service->manager()) {
    $districtOverview=$db->all("SELECT d.id,d.name,COALESCE(n.nurse_count,0) nurse_count,COALESCE(s.school_count,0) school_count FROM districts d LEFT JOIN (SELECT district_id,COUNT(*) nurse_count FROM users WHERE role='nurse' AND active=1 GROUP BY district_id) n ON n.district_id=d.id LEFT JOIN (SELECT district_id,COUNT(*) school_count FROM locations WHERE active=1 AND type='School' GROUP BY district_id) s ON s.district_id=d.id WHERE d.active=1 ORDER BY nurse_count DESC,d.name");
    $unassignedDistrictNurses=(int)$db->one("SELECT COUNT(*) n FROM users u LEFT JOIN districts d ON d.id=u.district_id WHERE u.role='nurse' AND u.active=1 AND (u.district_id IS NULL OR d.id IS NULL OR d.active<>1)")['n'];
    $schoolsByCoverage=['with'=>[], 'without'=>[]];
    $schools=$db->all("
        SELECT l.name, d.name AS district_name, COALESCE(coverage.nurse_count,0) AS nurse_count
        FROM locations l
        LEFT JOIN districts d ON d.id=l.district_id
        LEFT JOIN (
            SELECT location_id, COUNT(*) AS nurse_count
            FROM (
                SELECT location_id, id AS nurse_id FROM users WHERE role='nurse' AND active=1 AND location_id IS NOT NULL
                UNION
                SELECT nl.location_id, nl.nurse_id FROM nurse_locations nl
                JOIN users u ON u.id=nl.nurse_id AND u.role='nurse' AND u.active=1
            ) assignments
            GROUP BY location_id
        ) coverage ON coverage.location_id=l.id
        WHERE l.type='School' AND l.active=1
        ORDER BY l.name, d.name, l.id
    ");
    foreach ($schools as $school) {
        $schoolsByCoverage[(int)$school['nurse_count'] > 0 ? 'with' : 'without'][]=$school;
    }
}
$activities=$db->all("SELECT a.*,COALESCE(l.name,a.destination_name) location_name,(SELECT COUNT(*) FROM deployments d WHERE d.activity_id=a.id AND d.status<>'Cancelled') assigned FROM activities a LEFT JOIN locations l ON l.id=a.location_id WHERE a.status='Open' AND a.ends_at>? ORDER BY a.starts_at LIMIT 4",[now()]);
page_heading('WORKFORCE AT A GLANCE',($service->manager()?'Your workforce, in flow.':'Hello, '.preg_replace('/^Demo /','',explode(' ',$user['name'])[0]).'.'),$service->manager()?'A clearer picture of your people, their duty, and where they’re needed.':'Your duty schedule, upcoming assignments, and updates in one place.',add_link('?page=schedule&new=1','Add schedule'));
?>
<?php if($user['role']==='admin'):
    $setupSteps=[
        ['label'=>'Add districts / areas','url'=>'?page=configuration&tab=districts&new=1','done'=>(bool)$db->one('SELECT id FROM districts WHERE active=1 LIMIT 1')],
        ['label'=>'Add schools & duty locations','url'=>'?page=locations&new=1','done'=>(bool)$db->one('SELECT id FROM locations WHERE active=1 LIMIT 1')],
        ['label'=>'Create nursing personnel accounts','url'=>'?page=users&new=1','done'=>(bool)$db->one("SELECT id FROM users WHERE active=1 AND role='nurse' LIMIT 1")],
    ];
    if(count(array_filter($setupSteps,fn($step)=>$step['done']))<count($setupSteps)): ?>
    <section class="card workspace-setup" aria-labelledby="workspace-setup-title">
        <h2 id="workspace-setup-title">Set up your workforce</h2>
        <p>Add your official districts, locations, and nursing personnel to start scheduling.</p>
        <ol><?php foreach($setupSteps as $step): ?><li><?php if($step['done']): ?><span><?=e($step['label'])?></span><?=badge('Ready')?><?php else: ?><a href="<?=e($step['url'])?>"><?=e($step['label'])?> <span aria-hidden="true">→</span></a><?php endif ?></li><?php endforeach ?></ol>
    </section>
    <?php endif; endif ?>
<section class="overview-banner"><div><span class="eyebrow">SCHOOLS DIVISION OFFICE OF AURORA</span><h2>Every assignment connects to better care.</h2><p><?=e(date('l, F j, Y',strtotime($date)))?> <span>•</span> <?=count($roster)?> assignment<?=count($roster)===1?'':'s'?> on the roster</p></div><form method="get" class="date-switch"><input type="hidden" name="page" value="dashboard"><label class="sr-only" for="dashboard-date">Workforce date</label><input id="dashboard-date" type="date" name="date" value="<?=e($date)?>" required><button>View day</button></form></section>
<section class="stats-grid" aria-label="Workforce summary"><?php foreach([['Active nurses',$active,'nurses','blue','In your '.($service->manager()?'workforce':'profile')],['On duty / scheduled',($counts['On Duty']??0)+($counts['Scheduled']??0),'calendar','teal','Duty during selected day'],['Potentially available',$available,'availability','green','No conflicts during the day'],['Deployed / on leave',($counts['Deployed']??0)+($counts['On Leave']??0),'deployment','violet',($counts['Deployed']??0).' deployed · '.($counts['On Leave']??0).' on leave']] as [$label,$value,$ico,$color,$note]): ?><article class="stat-card"><div><span><?=e($label)?></span><div class="stat-icon <?=$color?>"><?=icon($ico)?></div></div><strong class="stat-value"><?=$value?></strong><small><?=e($note)?></small></article><?php endforeach ?></section>
<?php if($service->manager()):
    $districtMax=max(1,$unassignedDistrictNurses,...array_map(fn($d)=>(int)$d['nurse_count'],$districtOverview));
    $districtCards=$districtOverview;
    if ($unassignedDistrictNurses) {
        $districtCards[]=['id'=>null,'name'=>'Unassigned district','nurse_count'=>$unassignedDistrictNurses,'school_count'=>null];
    }
    $districtNurseTotal=array_sum(array_map(fn($d)=>(int)$d['nurse_count'],$districtOverview));
    $districtSchoolTotal=array_sum(array_map(fn($d)=>(int)$d['school_count'],$districtOverview));
    $districtTotal=$districtNurseTotal+$unassignedDistrictNurses;
    $shareColors=['#2a7bb7','#24a18d','#8970c5','#dd7065','#4460a8','#6d9d54','#c668a0','#4ea1c8','#7c6c56','#a49b3b','#5266aa','#21766d'];
    $shareSegments=[];
    $shareCursor=0;
    foreach ($districtCards as $district) {
        $nurses=(int)$district['nurse_count'];
        if ($nurses===0) continue;
        $share=$nurses / $districtTotal * 100;
        $color=$district['id']===null ? '#d6943b' : $shareColors[count($shareSegments) % count($shareColors)];
        $startAngle=2 * M_PI * $shareCursor / $districtTotal;
        $shareCursor+=$nurses;
        $endAngle=2 * M_PI * $shareCursor / $districtTotal;
        $path=sprintf(
            'M 50 50 L %.5F %.5F A 50 50 0 %d 1 %.5F %.5F Z',
            50 + 50*sin($startAngle), 50 - 50*cos($startAngle),
            (int)($share > 50), 50 + 50*sin($endAngle), 50 - 50*cos($endAngle)
        );
        $shareSegments[]=['name'=>$district['name'],'color'=>$color,'share'=>$share,'path'=>$path];
    }
?>
<section class="card district-overview" aria-labelledby="district-overview-title">
    <div class="section-heading"><div><span class="eyebrow">DISTRICT DISTRIBUTION</span><h2 id="district-overview-title">Nursing workforce by district</h2><p>Active nurses and public schools assigned to each active district.</p></div><a href="?page=locations">Districts &amp; schools <?=icon('arrow')?></a></div>
    <div class="district-overview-stats"><div><strong><?=count($districtOverview)?></strong><span>Active districts</span></div><div><strong><?=number_format($districtNurseTotal+$unassignedDistrictNurses)?></strong><span>Active nurses</span></div><div><strong><?=number_format($districtSchoolTotal)?></strong><span>Active schools</span></div></div>
    <?php if(!$districtCards): ?><div class="district-chart-empty"><h3>No active districts yet</h3><p>Add district records to see workforce distribution.</p><a class="button small" href="?page=configuration&tab=districts">Manage districts</a></div><?php else: ?>
    <div class="district-chart-layout">
      <section class="district-share-chart" aria-labelledby="district-share-title">
        <header>
            <div>
                <h3 id="district-share-title">Workforce share</h3>
                <p>Percentage of active nurses by district</p>
            </div>
            <div class="district-share-total"><strong><?=number_format($districtTotal)?></strong><span>Active nurses</span></div>
        </header>
        <?php if ($shareSegments): ?>
            <div class="district-share-content">
                <svg class="district-share-pie" viewBox="0 0 100 100" aria-hidden="true">
                    <?php if (count($shareSegments) === 1): ?>
                        <circle cx="50" cy="50" r="50" fill="<?=$shareSegments[0]['color']?>"></circle>
                    <?php else: ?>
                        <?php foreach ($shareSegments as $segment): ?>
                            <path d="<?=e($segment['path'])?>" fill="<?=$segment['color']?>"></path>
                        <?php endforeach ?>
                    <?php endif ?>
                </svg>
                <ul class="district-share-legend" role="list">
                    <?php foreach ($shareSegments as $segment):
                        $name=$segment['name'];
                        $label=mb_strtoupper($name)===$name ? mb_convert_case($name, MB_CASE_TITLE) : $name;
                    ?>
                        <li>
                            <svg class="district-share-swatch" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="<?=$segment['color']?>"></circle></svg>
                            <span class="district-share-name"><?=e($label)?></span>
                            <strong><?=number_format($segment['share'], 1)?>%</strong>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php else: ?>
            <p>No active nurses assigned to districts.</p>
        <?php endif ?>
      </section>
      <div class="district-card-grid">
        <?php foreach ($districtCards as $district):
            $districtId = $district['id'];
            $nurses = (int)$district['nurse_count'];
            $schools = $district['school_count'];
            $barWidth = round($nurses / $districtMax * 100, 2);
        ?>
            <article class="district-workforce-card<?=$districtId===null?' district-workforce-unassigned':''?>">
                <header>
                    <span class="district-card-icon"><?=icon('locations')?></span>
                    <h3><?=e($district['name'])?></h3>
                </header>
                <dl class="district-card-stats">
                    <div><dt>Active nurses</dt><dd><?=number_format($nurses)?></dd></div>
                    <?php if ($schools !== null): ?>
                        <div><dt>Active schools</dt><dd><?=number_format((int)$schools)?></dd></div>
                    <?php endif ?>
                </dl>
                <div class="district-card-track" role="meter" aria-label="Active nurses compared with the largest group" aria-valuemin="0" aria-valuemax="<?=$districtMax?>" aria-valuenow="<?=$nurses?>">
                    <svg aria-hidden="true"><rect width="<?=$barWidth?>%" height="100%" rx="4"></rect></svg>
                </div>
                <a class="button district-card-action" href="?page=nurses<?=$districtId===null?'':'&amp;district_id='.(int)$districtId?>" aria-label="<?=e($districtId===null?'View all nursing personnel':'View nurses in '.$district['name'])?>"><?=$districtId===null?'View personnel':'View nurses'?></a>
            </article>
        <?php endforeach ?>
      </div>
    </div><?php endif ?>
</section>
<?php
$coverageTabs=['with'=>'With nurses','without'=>'Without nurses'];
$requestedTab=$_GET['school_coverage'] ?? '';
$coverageTab=is_string($requestedTab) && isset($coverageTabs[$requestedTab]) ? $requestedTab : 'with';
$searchText=$_GET['school_search'] ?? '';
$schoolSearch=is_string($searchText) ? mb_substr(trim($searchText), 0, 120) : '';
$schoolMatches=$schoolsByCoverage[$coverageTab];
if ($schoolSearch !== '') {
    $schoolMatches=array_values(array_filter($schoolMatches, static fn($school) =>
        mb_stripos($school['name'], $schoolSearch) !== false
        || mb_stripos($school['district_name'] ?? 'Unassigned district', $schoolSearch) !== false
    ));
}
$schoolsPerPage=12;
$schoolMatchCount=count($schoolMatches);
$schoolLastPage=max(1, (int)ceil($schoolMatchCount / $schoolsPerPage));
$schoolPage=max(1, min($schoolLastPage, filter_var($_GET['school_page'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
$schoolOffset=($schoolPage - 1) * $schoolsPerPage;
$dashboardQuery=['page'=>'dashboard', 'date'=>$date];
if (isset($_GET['month']) && is_string($_GET['month'])) $dashboardQuery['month']=$_GET['month'];
$coverageUrl=static function (string $tab, string $search='', int $page=1) use ($dashboardQuery): string {
    $query=$dashboardQuery + ['school_coverage'=>$tab];
    if ($search !== '') $query['school_search']=$search;
    if ($page > 1) $query['school_page']=$page;
    return '?'.http_build_query($query).'#school-coverage';
};
?>
<section class="card school-coverage" id="school-coverage" aria-labelledby="school-coverage-title">
    <header class="school-coverage-heading">
        <span class="school-coverage-icon"><?=icon('locations')?></span>
        <div><h2 id="school-coverage-title">School nurse coverage</h2><p>Browse active schools by registered nurse assignment.</p></div>
    </header>
    <div class="school-coverage-body">
        <div class="school-coverage-toolbar">
            <nav class="school-coverage-tabs" aria-label="School coverage filters">
                <?php foreach ($coverageTabs as $tab=>$title): ?>
                    <a href="<?=e($coverageUrl($tab, $schoolSearch))?>" <?=$tab===$coverageTab?'aria-current="page"':''?>>
                        <?=e($title)?> <span><?=number_format(count($schoolsByCoverage[$tab]))?></span>
                    </a>
                <?php endforeach ?>
            </nav>
            <form class="school-coverage-search" method="get" action="./#school-coverage">
                <?php foreach ($dashboardQuery + ['school_coverage'=>$coverageTab] as $name=>$value): ?>
                    <input type="hidden" name="<?=e($name)?>" value="<?=e($value)?>">
                <?php endforeach ?>
                <label class="sr-only" for="school-coverage-search">Search schools or districts</label>
                <input id="school-coverage-search" type="search" name="school_search" value="<?=e($schoolSearch)?>" placeholder="Search schools or districts" maxlength="120">
                <button type="submit">Search</button>
            </form>
        </div>
        <?php if ($schoolMatches): ?>
            <div class="school-coverage-grid">
                <?php foreach (array_slice($schoolMatches, $schoolOffset, $schoolsPerPage) as $school):
                    $nurseCount=(int)$school['nurse_count'];
                ?>
                    <article class="school-coverage-card">
                        <h3><?=e($school['name'])?></h3>
                        <p><?=e($school['district_name'] ?? 'Unassigned district')?></p>
                        <span class="school-coverage-count<?=$nurseCount===0?' school-coverage-count-empty':''?>">
                            <?=$nurseCount>0 ? number_format($nurseCount).($nurseCount===1?' nurse':' nurses') : 'No nurse assigned'?>
                        </span>
                    </article>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <div class="school-coverage-empty">
                <strong><?=$schoolSearch!==''?'No matching schools':'No schools in this group'?></strong>
                <?php if ($schoolSearch!==''): ?><p>Try a different school or district name.</p><?php endif ?>
            </div>
        <?php endif ?>
        <?php if ($schoolMatches || $schoolSearch!==''): ?>
            <div class="school-coverage-footer">
                <?php if ($schoolMatches): ?>
                    <span><?=number_format($schoolOffset+1)?>–<?=number_format(min($schoolOffset+$schoolsPerPage, $schoolMatchCount))?> of <?=number_format($schoolMatchCount)?> schools</span>
                <?php endif ?>
                <?php if ($schoolSearch!==''): ?><a href="<?=e($coverageUrl($coverageTab))?>">Clear search</a><?php endif ?>
                <?php if ($schoolLastPage>1): ?>
                    <nav aria-label="School results pages">
                        <?php if ($schoolPage>1): ?><a class="button" href="<?=e($coverageUrl($coverageTab, $schoolSearch, $schoolPage-1))?>">Previous</a><?php endif ?>
                        <?php if ($schoolPage<$schoolLastPage): ?><a class="button" href="<?=e($coverageUrl($coverageTab, $schoolSearch, $schoolPage+1))?>">Next</a><?php endif ?>
                    </nav>
                <?php endif ?>
            </div>
        <?php endif ?>
    </div>
</section>
<?php endif ?>
<?php
$statusSeries = [
    ['Available', $available, 'available'],
    ['Scheduled', $counts['Scheduled'] ?? 0, 'scheduled'],
    ['On duty', $counts['On Duty'] ?? 0, 'on-duty'],
    ['Deployed / leave', ($counts['Deployed'] ?? 0) + ($counts['On Leave'] ?? 0), 'deployed'],
];
$statusScale = max(1, max(array_column($statusSeries, 1)));
?>
<section class="card workforce-chart" aria-labelledby="workforce-chart-title">
    <header class="workforce-chart-header">
        <div>
            <h2 id="workforce-chart-title">Daily workforce status</h2>
            <p>Active nurses by status</p>
        </div>
        <time datetime="<?=e($date)?>"><?=icon('calendar')?> <?=e(date('M j, Y', strtotime($date)))?></time>
    </header>
    <div class="workforce-status-grid">
        <?php foreach ($statusSeries as [$label, $value, $status]): ?>
            <article class="workforce-status-card workforce-status-<?=$status?>">
                <h3><?=e($label)?></h3>
                <strong><?=number_format($value)?></strong>
                <div class="workforce-status-track" aria-hidden="true">
                    <span style="width:<?=round($value / $statusScale * 100, 4)?>%"></span>
                </div>
            </article>
        <?php endforeach ?>
    </div>
</section>
<?php if ($service->manager()) require __DIR__.'/monthly-nurse-chart.php'; ?>
<div class="dashboard-columns"><div><section class="card roster-card"><div class="section-heading"><div><span class="eyebrow">DAILY OPERATIONS</span><h2><?= $service->manager()? 'Duty roster':'My assignments' ?></h2></div><a href="?page=schedule">View schedule <?=icon('arrow')?></a></div><?php roster_table(array_slice($roster,0,6)); ?><div class="card-foot"><span class="live-dot"></span> Based on recorded schedules and approved assignments</div></section>
<?php if($service->manager()): ?><section class="card"><div class="section-heading"><div><span class="eyebrow">LOCATION COVERAGE</span><h2>Care where it’s needed</h2></div><a href="?page=locations">All locations →</a></div><p class="muted inset">Distinct nurses assigned during this day; partial-day assignments may leave time gaps.</p><?php $locations=$db->all('SELECT * FROM locations WHERE active=1 AND required_nurses>0 ORDER BY name LIMIT 6'); if(!$locations) empty_state('Set your coverage targets','Add a required nurse count to your schools and duty locations.'); foreach($locations as $l): $ids=[]; foreach($roster as $r) if($r['location_id']==$l['id']) $ids[$r['nurse_id']]=true; $covered=count($ids); ?><div class="coverage-row"><div class="location-icon"><?=icon('locations')?></div><div><strong><?=e($l['name'])?></strong><small><?=$covered?> / <?=$l['required_nurses']?> nurses assigned</small></div><?=badge($covered>=$l['required_nurses']?'Covered':'Needs '.($l['required_nurses']-$covered))?></div><?php endforeach ?></section><?php endif ?></div>
<div><section class="card"><div class="section-heading"><div><span class="eyebrow">COMING UP</span><h2>Activities & staffing</h2></div><?=icon('activities')?></div><?php if(!$activities) empty_state('No upcoming activities','New personnel requirements will appear here.'); foreach($activities as $a): ?><article class="activity-preview"><div class="activity-date"><strong><?=date('d',strtotime($a['starts_at']))?></strong><span><?=date('M',strtotime($a['starts_at']))?></span></div><div><h3><?=e($a['title'])?></h3><p><?=e($a['location_name'])?></p><?=badge($a['assigned'] >= $a['required_nurses']?'Fully staffed':'Needs '.($a['required_nurses']-$a['assigned']).' nurses')?><?php if($service->manager()): ?><a href="?page=deployment&activity=<?=$a['id']?>">Review candidates →</a><?php endif ?></div></article><?php endforeach ?></section>
<section class="card available-card"><div class="section-heading"><div><span class="eyebrow">READY TO SUPPORT</span><h2>Potentially available</h2></div><span class="pill-count"><?=$available?></span></div><p class="muted inset">Availability is checked again for the exact deployment interval.</p><?php $free=array_slice(array_filter($workforce,fn($u)=>$u['status']==='Available'),0,4); if(!$free) empty_state('No free personnel for this day','Review exact times in the deployment center.'); foreach($free as $u): ?><div class="available-person"><div class="person"><?=avatar($u['name'])?><div><strong><?=e($u['name'])?></strong><small><?=e($u['district_name']??'Unassigned district')?></small></div></div><span class="live-dot"></span></div><?php endforeach ?><?php if($service->manager()): ?><a class="button full" href="?page=deployment">Open deployment center <?=icon('arrow')?></a><?php endif ?></section></div></div>
