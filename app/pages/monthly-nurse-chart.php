<?php
$month=(string)($_GET['month']??substr($date,0,7));
$monthlyNurses=$service->monthlyNurseSchedules($month);
$monthStart=new DateTimeImmutable($month.'-01');
$monthLabel=$monthStart->format('F Y');
$monthlyDuties=array_sum(array_column($monthlyNurses,'duty_count'));
$highest=(int)($monthlyNurses[0]['duty_count']??0);
$topNurses=array_slice($monthlyNurses,0,10);
?>
<section class="card monthly-nurse-chart" id="monthly-workload" aria-labelledby="monthly-workload-title">
    <div class="monthly-chart-heading"><div><span class="eyebrow">MONTHLY WORKLOAD</span><h2 id="monthly-workload-title">Most-scheduled nurses</h2><p>Scheduled duties per nurse · <?=e($monthLabel)?></p></div><form method="get" action="#monthly-workload" class="monthly-chart-filter"><input type="hidden" name="page" value="dashboard"><input type="hidden" name="date" value="<?=e($date)?>"><label for="workload-month">Month</label><input type="month" id="workload-month" name="month" value="<?=e($month)?>" required><button type="submit">View month</button></form></div>
    <div class="monthly-chart-stats"><div><span>Nurses scheduled</span><strong><?=number_format(count($monthlyNurses))?></strong></div><div><span>Total scheduled duties</span><strong><?=number_format($monthlyDuties)?></strong></div><div><span>Highest nurse duty count</span><strong><?=number_format($highest)?></strong></div></div>
    <?php if(!$topNurses): ?><div class="monthly-chart-empty"><?=icon('calendar')?><h3>No scheduled duties this month</h3><p>Counts and the nurse ranking will appear when schedules are added.</p><a class="button small" href="?page=schedule&new=1">Add a schedule</a></div><?php else: ?>
    <div class="monthly-chart-key"><span>Top <?=count($topNurses)?> of <?=count($monthlyNurses)?> scheduled nurses</span><span>Number of duties</span></div>
    <ol class="monthly-nurse-bars" aria-label="<?=e('Nurses ranked by scheduled duties in '.$monthLabel)?>"><?php $rank=0; $lastCount=null; foreach($topNurses as $i=>$nurse): $count=(int)$nurse['duty_count']; if($lastCount!==$count) $rank=$i+1; $lastCount=$count; $href='?'.http_build_query(['page'=>'schedule','nurse_id'=>$nurse['id'],'from'=>$monthStart->format('Y-m-d'),'to'=>$monthStart->format('Y-m-t')]); ?><li><span class="monthly-nurse-rank" aria-label="Rank <?=$rank?>"><?=$rank?></span><a class="monthly-nurse-name" href="<?=e($href)?>" title="<?=e('View schedules for '.$nurse['name'])?>"><?=e($nurse['name'])?></a><svg class="monthly-nurse-bar" viewBox="0 0 100 12" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="12" rx="2" class="monthly-bar-track"/><rect width="<?=round($count/$highest*100,2)?>" height="12" rx="2" class="monthly-bar-fill"/></svg><strong class="monthly-nurse-value"><?=number_format($count)?><span class="sr-only"> scheduled duties</span></strong></li><?php endforeach ?></ol>
    <?php endif ?>
    <p class="monthly-chart-note">Each non-cancelled duty schedule starting in the selected month counts once. Deployments are excluded. Equal counts share a rank.</p>
</section>
