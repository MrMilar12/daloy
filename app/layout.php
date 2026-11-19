<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=e(csrf_token())?>"><title><?=e(ucwords(str_replace('_',' ',$page)))?> · DALOY</title><link rel="stylesheet" href="<?=e(asset_url('app.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('theme-transition.css'))?>"><script src="<?=e(asset_url('app.js'))?>" defer></script></head><body data-page="<?=e($page)?>" data-user-id="<?=(int)$user['id']?>">
<?php $labels=['dashboard'=>$service->manager()?'Overview':'Home','schedule'=>$service->manager()?'Master schedule':'My schedule','calendar'=>'Calendar','nurses'=>'Nursing personnel','locations'=>'Schools & locations','deployment'=>'Deployment center','deployment-history'=>'Assignment history','activities'=>'Activities & events','availability'=>'Availability','leave'=>'Leave & unavailability','reports'=>'Reports','notifications'=>'Notifications','profile'=>'My profile','users'=>'User accounts','configuration'=>'Configuration','audit'=>'Audit trail','settings'=>'Settings','memo-acknowledgments'=>'Memo acknowledgments','admin-guide'=>'How to use DALOY']; $unread=$db->one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND read_at IS NULL',[$user['id']])['n']; ?>
<aside class="sidebar" id="sidebar"><a class="brand" href="./"><span class="brand-mark">D</span><span>DALOY<small>SDO AURORA</small></span></a><div class="workspace-label">WORKSPACE</div><nav aria-label="Main navigation"><?php foreach(['dashboard','schedule','calendar','nurses','locations','deployment','deployment-history','activities','availability','leave','reports'] as $item): if(!in_array($item,$allowed)) continue; ?><a href="?page=<?=$item?>" class="<?=$page===$item?'active':''?>" <?=$page===$item?'aria-current="page"':''?>><?=icon($item==='deployment-history'?'deployment':$item)?><span><?=e($labels[$item])?></span><?php if($item==='deployment'): ?><span class="nav-dot"></span><?php endif ?></a><?php endforeach ?>
<div class="workspace-label">MANAGEMENT</div><?php foreach(['notifications','users','configuration','audit','settings','memo-acknowledgments','admin-guide','profile'] as $item): if(!in_array($item,$allowed)) continue; ?><a href="?page=<?=$item?>" class="<?=$page===$item?'active':''?>"><?=icon($item==='memo-acknowledgments'?'reports':$item)?><span><?=e($labels[$item])?></span><?php if($item==='notifications' && $unread): ?><span class="count"><?=$unread?></span><?php endif ?></a><?php endforeach ?></nav><div class="sidebar-footer"><span class="live-dot"></span> Connected care, better flow.<small>Nursing Workforce Management</small></div></aside>
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
        <time class="today-label" datetime="<?=date('Y-m-d')?>"><?=icon('calendar')?> <span><?=date('D, M j, Y')?></span></time>
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
        <div class="top-user">
            <?=avatar($user['name'])?>
            <span class="top-user-copy"><strong><?=e($user['name'])?></strong><small><?=e(['admin'=>'Administrator','head'=>'Nurse Head','nurse'=>'Nursing personnel'][$user['role']])?></small></span>
        </div>
        <form method="post" class="signout-form">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="logout">
            <button class="signout" type="submit" title="Sign out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg><span>Sign out</span></button>
        </form>
    </div>
</header>
<main id="main-content"><?php if($error): ?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif ?><?php if(isset($_SESSION['flash'])): ?><div class="alert success" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif ?><?=$content?></main><footer class="app-footer"><span>DALOY · <?=e($installed['setting_value'])?></span><span>All times are Asia/Manila (UTC+8)</span></footer></div>
<nav class="mobile-bottom" aria-label="Quick navigation"><?php foreach(['dashboard','calendar','deployment','profile'] as $item): ?><a href="?page=<?=$item?>" class="<?=$page===$item?'active':''?>"><?=icon($item)?><span><?=e($item==='dashboard'?'Home':ucfirst($item))?></span></a><?php endforeach ?></nav><div class="system-update-toasts" data-system-update-toasts role="region" aria-label="System update notifications" aria-live="polite"></div></body></html>
