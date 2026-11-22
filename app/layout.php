<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=e(csrf_token())?>"><title><?=e(ucwords(str_replace('_',' ',$page)))?> · DALOY</title><link rel="stylesheet" href="<?=e(asset_url('app.css'))?>"><script src="<?=e(asset_url('app.js'))?>" defer></script></head><body data-page="<?=e($page)?>">
<?php $labels=['dashboard'=>$service->manager()?'Overview':'Home','schedule'=>$service->manager()?'Master schedule':'My schedule','calendar'=>'Calendar','nurses'=>'Nursing personnel','locations'=>'Schools & locations','deployment'=>'Deployment center','deployment-history'=>'Assignment history','activities'=>'Activities & events','availability'=>'Availability','leave'=>'Leave & unavailability','reports'=>'Reports','notifications'=>'Notifications','profile'=>'My profile','users'=>'User accounts','configuration'=>'Configuration','audit'=>'Audit trail','settings'=>'Settings','admin-guide'=>'How to use DALOY']; $unread=$db->one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND read_at IS NULL',[$user['id']])['n']; ?>
<?php
$navigationGroups = [
    ['Scheduling & leave', 'calendar', ['schedule', 'calendar', 'availability', 'leave']],
    ['Deployments & events', 'deployment', ['deployment', 'deployment-history', 'activities']],
    ['People & locations', 'nurses', ['nurses', 'locations', 'users']],
    ['Communications', 'notifications', ['notifications', 'memo-acknowledgments']],
    ['Administration', 'settings', ['reports', 'configuration', 'audit', 'settings', 'admin-guide']],
];
$labels['memo-acknowledgments'] = 'Memo acknowledgments';
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="./"><span class="brand-mark">D</span><span>DALOY<small>SDO AURORA</small></span></a>
    <nav class="sidebar-tree" aria-label="Main navigation">
        <a class="sidebar-leaf<?=$page==='dashboard'?' active':''?>" href="?page=dashboard" <?=$page==='dashboard'?'aria-current="page"':''?>>
            <span class="sidebar-branch-icon"><?=icon('dashboard')?></span><span><?=e($labels['dashboard'])?></span>
        </a>
        <?php foreach ($navigationGroups as [$title, $glyph, $items]):
            $items = array_intersect($items, $allowed);
            if (!$items) continue;
            $currentGroup = in_array($page, $items, true);
        ?>
            <details class="sidebar-branch<?=$currentGroup?' current-branch':''?>" <?=$currentGroup?'open':''?>>
                <summary class="sidebar-branch-trigger">
                    <span class="sidebar-branch-icon"><?=icon($glyph)?></span>
                    <span><?=e($title)?></span>
                    <?php if ($unread && in_array('notifications', $items, true)): ?><span class="sidebar-unread sidebar-branch-unread" aria-label="<?=$unread?> unread"><?=$unread?></span><?php endif ?>
                </summary>
                <ul class="sidebar-branch-links">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <a href="?page=<?=$item?>" class="<?=$page===$item?'active':''?>" <?=$page===$item?'aria-current="page"':''?>>
                                <span><?=e($labels[$item])?></span>
                                <?php if ($item==='notifications' && $unread): ?><span class="sidebar-unread" aria-label="<?=$unread?> unread"><?=$unread?></span><?php endif ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            </details>
        <?php endforeach ?>
        <a class="sidebar-leaf<?=$page==='profile'?' active':''?>" href="?page=profile" <?=$page==='profile'?'aria-current="page"':''?>>
            <span class="sidebar-branch-icon"><?=icon('profile')?></span><span><?=e($labels['profile'])?></span>
        </a>
    </nav>
    <div class="sidebar-footer"><span class="live-dot"></span> Connected care, better flow.<small>Nursing Workforce Management</small></div>
</aside>
<div class="app-shell">
<header class="topbar">
    <div class="topbar-location">
        <button type="button" class="icon-button menu-toggle" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">☰</button>
        <strong><?=e($labels[$page])?></strong>
    </div>
    <div class="top-actions">
        <time class="topbar-date" datetime="<?=date('Y-m-d')?>"><?=icon('calendar')?> <?=date('D, M j, Y')?></time>
        <details class="top-dropdown">
            <summary class="top-trigger notification-button" aria-label="Notifications, <?=$unread?> unread">
                <?=icon('notifications')?><?php if($unread): ?><i aria-hidden="true"></i><?php endif ?>
            </summary>
            <div class="top-dropdown-card">
                <div class="top-dropdown-heading">
                    <strong>Notifications</strong>
                    <small><?=$unread ? "$unread unread" : 'No unread notifications'?></small>
                </div>
                <?php $recentNotifications = $db->all(
                    'SELECT title, message, link, read_at FROM notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 5',
                    [$user['id']]
                ); ?>
                <?php if ($recentNotifications): ?>
                    <ul class="top-notification-list" aria-label="Recent notifications">
                        <?php foreach ($recentNotifications as $notification): ?>
                            <li>
                                <a class="top-dropdown-action top-notification" href="<?=e($notification['link'] ?: '?page=notifications')?>">
                                    <strong><?=e($notification['title'])?></strong>
                                    <?php if ($notification['read_at'] === null): ?><span class="top-notification-unread">Unread</span><?php endif ?>
                                    <span class="top-notification-message"><?=e(notification_message($notification['message']))?></span>
                                </a>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php else: ?>
                    <p class="top-notification-empty">No notifications yet.</p>
                <?php endif ?>
                <a class="top-dropdown-action" href="?page=notifications"><?=icon('notifications')?> View all notifications</a>
            </div>
        </details>
        <details class="top-dropdown">
            <summary class="top-trigger top-account" aria-label="Account for <?=e($user['name'])?>">
                <span class="avatar top-account-avatar" aria-hidden="true">
                    <?php if (!empty($user['photo'])): ?>
                        <img class="top-account-photo" src="download.php?type=photo&amp;id=<?=(int)$user['id']?>" alt="" width="30" height="30" hidden>
                    <?php endif ?>
                    <span><?=e(initials($user['name']))?></span>
                </span>
                <strong><?=e($user['name'])?></strong>
            </summary>
            <div class="top-dropdown-card">
                <div class="top-dropdown-heading">
                    <strong><?=e($user['name'])?></strong>
                    <small><?=e(['admin'=>'Administrator','head'=>'Nurse Head','nurse'=>'Nursing personnel'][$user['role']])?></small>
                </div>
                <a class="top-dropdown-action" href="?page=profile"><?=icon('profile')?> My profile</a>
                <button type="button" class="theme-toggle top-dropdown-action"><?=icon('settings')?> Toggle color theme</button>
            </div>
        </details>
        <form method="post">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="logout">
            <button class="top-logout" type="submit">Logout</button>
        </form>
    </div>
</header>
<main id="main-content"><?php $notice=$db->one("SELECT setting_value FROM system_settings WHERE setting_key='system_notice'")['setting_value']??''; if($notice): ?><div class="alert notice"><?=e($notice)?></div><?php endif ?><?php if($error): ?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif ?><?php if(isset($_SESSION['flash'])): ?><div class="alert success" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif ?><?=$content?></main><footer class="app-footer"><span>DALOY · <?=e($installed['setting_value'])?></span><span>All times are Asia/Manila (UTC+8)</span></footer></div>
<nav class="mobile-bottom" aria-label="Quick navigation"><?php foreach(['dashboard','calendar','deployment','profile'] as $item): ?><a href="?page=<?=$item?>" class="<?=$page===$item?'active':''?>"><?=icon($item)?><span><?=e($item==='dashboard'?'Home':ucfirst($item))?></span></a><?php endforeach ?></nav></body></html>
