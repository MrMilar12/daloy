<?php
$headerToday=new DateTimeImmutable('today');
$headerMonth=$headerToday->modify('first day of this month');
$headerCalendarStart=$headerMonth->modify('monday this week');
$headerDayCount=(int)$headerMonth->format('t')+(int)$headerMonth->format('N')-1;
$headerCalendarCells=(int)ceil($headerDayCount/7)*7;
$headerMonthEnd=$headerMonth->modify('+1 month')->format('Y-m-d H:i:s');
$headerMonthStart=$headerMonth->format('Y-m-d H:i:s');
$headerEvents=[];
foreach($service->roster(['starts_at'=>$headerMonthStart,'ends_at'=>$headerMonthEnd]) as $event) {
    $firstDay=new DateTimeImmutable(substr(max($event['starts_at'],$headerMonthStart),0,10));
    $eventEnd=min($event['ends_at'],$headerMonthEnd);
    for($eventDay=$firstDay;$eventDay->format('Y-m-d H:i:s')<$eventEnd;$eventDay=$eventDay->modify('+1 day')) {
        $headerEvents[$eventDay->format('Y-m-d')][]=$event;
    }
}
$headerTodayKey=$headerToday->format('Y-m-d');
?>
<details class="header-calendar" data-header-disclosure>
    <summary aria-label="Quick calendar, <?=e($headerToday->format('l, F j, Y'))?>">
        <?=icon('calendar')?> <time datetime="<?=e($headerToday->format('Y-m-d'))?>"><?=e($headerToday->format('D, M j, Y'))?></time>
    </summary>
    <section class="header-calendar-card" aria-labelledby="header-calendar-title">
        <header>
            <h2 id="header-calendar-title"><?=e($headerToday->format('F Y'))?></h2>
            <span>Today is <?=e($headerToday->format('l, M j'))?></span>
        </header>
        <div class="header-calendar-grid">
            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday): ?>
                <span class="header-calendar-weekday" aria-hidden="true"><?=$weekday?></span>
            <?php endforeach ?>
            <?php $headerDay=$headerCalendarStart; for($cell=0;$cell<$headerCalendarCells;$cell++,$headerDay=$headerDay->modify('+1 day')): ?>
                <?php if($headerDay->format('m')===$headerMonth->format('m')): ?>
                    <?php $dayKey=$headerDay->format('Y-m-d'); $eventCount=count($headerEvents[$dayKey]??[]); ?>
                    <a href="?page=calendar&amp;view=day&amp;date=<?=e($dayKey)?>" data-calendar-day="<?=e($dayKey)?>" aria-label="<?=e($headerDay->format('l, F j, Y').($eventCount?', '.$eventCount.' scheduled':'') )?>" <?=$dayKey===$headerTodayKey?'aria-current="date" data-selected':''?>>
                        <?=$headerDay->format('j')?><?php if($eventCount): ?><span class="header-calendar-event-dot" aria-hidden="true"></span><?php endif ?>
                    </a>
                <?php else: ?>
                    <span class="header-calendar-outside" aria-hidden="true"><?=$headerDay->format('j')?></span>
                <?php endif ?>
            <?php endfor ?>
        </div>
        <div class="header-calendar-agenda" aria-live="polite">
            <?php for($agendaDay=$headerMonth;$agendaDay->format('Y-m-d H:i:s')<$headerMonthEnd;$agendaDay=$agendaDay->modify('+1 day')):
                $agendaKey=$agendaDay->format('Y-m-d');
                $agendaEvents=$headerEvents[$agendaKey]??[];
            ?>
                <section data-calendar-agenda="<?=e($agendaKey)?>" <?=$agendaKey===$headerTodayKey?'':'hidden'?>>
                    <h3><?=$agendaKey===$headerTodayKey?'Today’s schedule':e($agendaDay->format('l, M j'))?></h3>
                    <?php if(!$agendaEvents): ?><p>No duties or deployments scheduled.</p>
                    <?php else: ?>
                        <ul><?php foreach(array_slice($agendaEvents,0,3) as $event): ?>
                            <li>
                                <strong><?=e($event['duty_name'])?></strong>
                                <span><?=e((substr($event['starts_at'],0,10)<$agendaKey?'Continues from previous day':date('g:i A',strtotime($event['starts_at']))).($event['location_name']?' · '.$event['location_name']:''))?></span>
                                <?php if($service->manager()): ?><span><?=e($event['nurse_name'])?></span><?php endif ?>
                            </li>
                        <?php endforeach ?></ul>
                        <a href="?page=calendar&amp;view=day&amp;date=<?=e($agendaKey)?>"><?=count($agendaEvents)>3?'View all '.count($agendaEvents).' assignments':'View day schedule'?></a>
                    <?php endif ?>
                </section>
            <?php endfor ?>
        </div>
        <a class="header-calendar-full" href="?page=calendar&amp;view=month&amp;date=<?=e($headerToday->format('Y-m-d'))?>">Open full calendar <?=icon('arrow')?></a>
    </section>
</details>
