<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=e(csrf_token())?>"><title><?=e(ucwords(str_replace('_',' ',$page)))?> · DALOY</title><link rel="stylesheet" href="<?=e(asset_url('app.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('theme-transition.css'))?>"><script src="<?=e(asset_url('app.js'))?>" defer></script></head><body data-page="<?=e($page)?>" data-user-id="<?=(int)$user['id']?>">
<?php $labels=['dashboard'=>$service->manager()?'Overview':'Home','schedule'=>$service->manager()?'Master schedule':'My schedule','calendar'=>'Calendar','nurses'=>'Nursing personnel','locations'=>'Schools & locations','deployment'=>'Deployment center','deployment-history'=>'Assignment history','activities'=>'Activities & events','availability'=>'Availability','leave'=>'Leave & unavailability','reports'=>'Reports','notifications'=>'Notifications','profile'=>'My profile','users'=>'User accounts','configuration'=>'Configuration','audit'=>'Audit trail','settings'=>'Settings','memo-acknowledgments'=>'Memo acknowledgments','admin-guide'=>'How to use DALOY']; $unread=$db->one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND read_at IS NULL',[$user['id']])['n']; ?>
<?php
$navGroups=[
    ['Scheduling & leave','calendar',['schedule','calendar','availability','leave']],
    ['Deployments & events','deployment',['deployment','deployment-history','activities']],
    ['People & locations','nurses',['nurses','locations','users']],
    ['Communications','notifications',['notifications','memo-acknowledgments']],
    ['Administration','settings',['reports','configuration','audit','settings','admin-guide']],
];
$navIcons=['deployment-history'=>'deployment','memo-acknowledgments'=>'reports'];
$navLink=static function(string $item) use($page,$labels,$unread,$navIcons): void {
    $current=$page===$item;
    ?>
    <a href="?page=<?=e($item)?>" class="<?=$current?'active':''?>" <?php if(in_array($item,['dashboard','profile'],true)): ?>aria-label="<?=e($labels[$item])?>" title="<?=e($labels[$item])?>"<?php endif ?> <?=$current?'aria-current="page"':''?>>
        <?=icon($navIcons[$item]??$item)?><span><?=e($labels[$item])?></span>
        <?php if($item==='notifications' && $unread): ?><span class="count"><?=$unread?></span><?php endif ?>
    </a>
    <?php
};
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="./" aria-label="DALOY home" title="DALOY home"><span class="brand-mark">D</span><span>DALOY<small>SDO AURORA</small></span></a>
    <nav class="sidebar-tree" aria-label="Main navigation">
        <?php $navLink('dashboard'); ?>
        <?php foreach($navGroups as [$groupLabel,$groupIcon,$items]):
            $items=array_intersect($items,$allowed);
            if(!$items) continue;
            $activeGroup=in_array($page,$items,true);
        ?>
            <details class="sidebar-branch<?=$activeGroup?' has-current':''?>" <?=$activeGroup?'open':''?>>
                <summary aria-label="<?=e($groupLabel)?>" title="<?=e($groupLabel)?>"><?=icon($groupIcon)?><span><?=e($groupLabel)?></span></summary>
                <div class="sidebar-branch-links">
                    <?php foreach($items as $item) $navLink($item); ?>
                </div>
            </details>
        <?php endforeach ?>
        <?php $navLink('profile'); ?>
    </nav>
    <div class="sidebar-bottom">
        <button class="sidebar-collapse-toggle" type="button" data-sidebar-collapse hidden aria-label="Collapse sidebar" title="Collapse sidebar" aria-controls="sidebar" aria-expanded="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m7-11-3 3 3 3"/></svg>
            <span aria-hidden="true">Collapse</span>
        </button>
        <div class="sidebar-footer"><span class="live-dot"></span> Connected care, better flow.<small>Nursing Workforce Management</small></div>
    </div>
</aside>
<div class="app-shell">
<header class="topbar">
    <div class="topbar-brandline">
        <button class="icon-button menu-toggle" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">☰</button>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <span class="breadcrumb-context">Workspace</span>
            <span class="breadcrumb-separator" aria-hidden="true">/</span>
            <strong><?=e($labels[$page])?></strong>
        </nav>
    </div>
    <div class="top-actions">
        <?php require __DIR__.'/header-calendar.php'; ?>
        <span class="topbar-divider" aria-hidden="true"></span>
        <button class="theme-toggle icon-button" type="button" aria-label="Toggle color theme">◐</button>
        <div class="notification-menu" data-notification-menu>
            <button class="icon-button notification-button" type="button" aria-label="Notifications, <?=$unread?> unread" aria-expanded="false" aria-controls="notification-dropdown" data-notification-toggle>
                <?=icon('notifications')?><span class="notification-label">Notifications</span><?php if($unread): ?><span class="notification-count"><?=$unread?></span><?php endif ?>
            </button>
            <section class="notification-dropdown" id="notification-dropdown" aria-labelledby="notification-dropdown-title" hidden>
                <header class="notification-dropdown-header">
                    <h2 id="notification-dropdown-title">Notifications</h2>
                    <span class="notification-dropdown-unread" data-notification-unread></span>
                </header>
                <p class="notification-dropdown-status" data-notification-status role="status"></p>
                <div class="notification-dropdown-list" data-notification-list></div>
                <footer class="notification-dropdown-footer"><a href="?page=notifications">View all notifications <span aria-hidden="true">→</span></a></footer>
            </section>
        </div>
        <?php $profileRole=['admin'=>'Administrator','head'=>'Nurse Head','nurse'=>'Nursing personnel'][$user['role']]; ?>
        <details class="header-profile" data-header-profile data-header-disclosure>
            <summary class="top-user" aria-label="Quick profile for <?=e($user['name'])?>">
                <?php if($user['photo']): ?>
                    <img class="avatar top-user-photo" src="download.php?type=photo&amp;id=<?=(int)$user['id']?>" alt="" width="39" height="39">
                <?php else: ?>
                    <?=avatar($user['name'])?>
                <?php endif ?>
                <span class="top-user-copy"><strong><?=e($user['name'])?></strong><small><?=e($profileRole)?></small></span>
            </summary>
            <section class="header-profile-card" aria-labelledby="header-profile-name">
                <header>
                    <span class="header-profile-portrait" aria-hidden="true">
                        <?php if($user['photo']): ?>
                            <img src="download.php?type=photo&amp;id=<?=(int)$user['id']?>" alt="" width="52" height="52" loading="lazy">
                        <?php else: ?>
                            <?=avatar($user['name'])?>
                        <?php endif ?>
                    </span>
                    <div><h2 id="header-profile-name"><?=e($user['name'])?></h2><p><?=e($profileRole)?></p></div>
                </header>
                <dl>
                    <?php if(!empty($user['designation'])): ?><div><dt>Designation</dt><dd><?=e($user['designation'])?></dd></div><?php endif ?>
                    <div><dt>Email</dt><dd><?=e($user['email'])?></dd></div>
                </dl>
                <a href="?page=profile">My profile <?=icon('arrow')?></a>
            </section>
        </details>
        <form method="post" class="signout-form">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="logout">
            <button class="signout" type="submit" title="Sign out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg><span>Sign out</span></button>
        </form>
    </div>
</header>
<main id="main-content"><?php if($error): ?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif ?><?php if(isset($_SESSION['flash'])): ?><div class="alert success" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif ?><?=$content?></main><footer class="app-footer"><span>DALOY · <?=e($installed['setting_value'])?></span><span>All times are Asia/Manila (UTC+8)</span></footer></div>
<nav class="mobile-bottom" aria-label="Quick navigation"><?php foreach(['dashboard','calendar','deployment','profile'] as $item): ?><a href="?page=<?=$item?>" class="<?=$page===$item?'active':''?>"><?=icon($item)?><span><?=e($item==='dashboard'?'Home':ucfirst($item))?></span></a><?php endforeach ?></nav><div class="system-update-toasts" data-system-update-toasts role="region" aria-label="System update notifications" aria-live="polite"></div></body></html>
