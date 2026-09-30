<?php
declare(strict_types=1);
$pageNum=max(1,min(100000,(int)($_GET['p']??1))); $offset=($pageNum-1)*25;
$from=(string)($_GET['from']??date('Y-m-01')); $to=(string)($_GET['to']??date('Y-m-t'));
foreach([$from,$to] as $filterDate) { $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$filterDate); if(!$parsed || $parsed->format('Y-m-d')!==$filterDate) throw new DomainException('Invalid date filter.',422); }
$filters=$_GET; $filters['starts_at']=$from.' 00:00:00'; $filters['ends_at']=date('Y-m-d',strtotime($to.' +1 day')).' 00:00:00';
switch($page) {
    case 'dashboard': require __DIR__.'/pages/dashboard.php'; break;
    case 'schedule': case 'calendar': require __DIR__.'/pages/schedule.php'; break;
    case 'deployment': case 'activities': require __DIR__.'/pages/deployment.php'; break;
    case 'leave': case 'availability': require __DIR__.'/pages/availability.php'; break;
    case 'users': case 'nurses': case 'profile': require __DIR__.'/pages/users.php'; break;
    case 'locations': case 'configuration': case 'settings': require __DIR__.'/pages/configuration.php'; break;
    case 'notifications': require __DIR__.'/pages/notifications.php'; break;
    case 'reports': case 'audit': require __DIR__.'/pages/reports.php'; break;
}
