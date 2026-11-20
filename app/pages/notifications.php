<?php
$status=in_array($_GET['status']??'all',['all','unread','read'],true)?($_GET['status']??'all'):'all';
$counts=$db->one('SELECT COUNT(*) total, COALESCE(SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END),0) unread FROM notifications WHERE user_id=?',[$user['id']]);
$total=(int)$counts['total']; $unreadCount=(int)$counts['unread'];
$condition=match($status) {'unread'=>' AND read_at IS NULL','read'=>' AND read_at IS NOT NULL',default=>''};
$rows=$db->all("SELECT * FROM notifications WHERE user_id=? $condition ORDER BY created_at DESC,id DESC LIMIT 26 OFFSET $offset",[$user['id']]);
$return='?page=notifications&status='.$status;
page_heading('YOUR WORKSPACE','Notifications','Stay up to date with your duties, assignments, and team updates.');
?>
<section class="inbox-summary" aria-label="Notification overview">
    <div class="inbox-summary-icon" aria-hidden="true"><?=icon('notifications')?></div>
    <div class="inbox-summary-copy"><span class="eyebrow">YOU’RE IN THE LOOP</span><h2><?=$unreadCount?number_format($unreadCount).' unread update'.($unreadCount===1?'':'s'):'You’re all caught up'?></h2><p><?=$unreadCount?'Take a moment to review what’s new.':'New updates will appear here when there’s something to share.'?></p></div>
    <div class="inbox-total"><strong><?=number_format($total)?></strong><span>Total updates</span></div>
</section>
<section class="card inbox-panel">
    <div class="inbox-toolbar"><nav class="inbox-tabs" aria-label="Filter notifications"><?php foreach(['all'=>['All',$total],'unread'=>['Unread',$unreadCount],'read'=>['Read',$total-$unreadCount]] as $key=>[$label,$count]): ?><a href="?page=notifications&status=<?=$key?>" class="<?=$status===$key?'selected':''?>" <?=$status===$key?'aria-current="page"':''?>><?=$label?><span><?=number_format($count)?></span></a><?php endforeach ?></nav>
        <?php if($unreadCount): ?><form method="post" class="inbox-mark-all"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="read_notifications"><input type="hidden" name="return" value="<?=e($return)?>"><button type="submit"><span aria-hidden="true">✓</span> Mark all as read</button></form><?php endif ?>
    </div>
    <?php if(!$rows): ?><div class="inbox-empty"><span class="inbox-empty-icon" aria-hidden="true"><?=icon('notifications')?></span><h2><?=$status==='unread'?'Nothing unread':($status==='read'?'No read updates yet':'Your inbox is ready')?></h2><p><?=$status==='unread'?'You’ve seen all your updates. Check back later for new activity.':($status==='read'?'Updates you mark as read will appear here.':'Duty schedules, deployments, and team updates will appear here.')?></p><?php if($status!=='all' || $pageNum>1): ?><a class="button small" href="?page=notifications">View all updates</a><?php endif ?></div><?php endif ?>
    <?php $previousDay=''; foreach(array_slice($rows,0,25) as $r):
        $day=substr($r['created_at'],0,10);
        if($day!==$previousDay): $previousDay=$day; $group=$day===date('Y-m-d')?'Today':($day===date('Y-m-d',strtotime('-1 day'))?'Yesterday':date('F j, Y',strtotime($day))); ?>
            <h2 class="inbox-date"><?=e($group)?></h2>
        <?php endif;
        $target=[]; parse_str(ltrim($r['link'],'?'),$target);
        [$category,$glyph]=match($target['page']??'') {'schedule'=>['Duty schedule','calendar'],'deployment'=>['Deployment','deployment'],'leave'=>['Leave update','leave'],'activities'=>['Team activity','activities'],default=>['Workspace update','notifications']};
        ?>
        <article class="inbox-item <?=$r['read_at']?'is-read':'is-unread'?>">
            <span class="inbox-item-icon" aria-hidden="true"><?=icon($glyph)?></span>
            <div class="inbox-item-content"><div class="inbox-item-meta"><span><?=e($category)?></span><time datetime="<?=e(date('c',strtotime($r['created_at'])))?>" title="<?=e(dt($r['created_at']))?>"><?=e(date('g:i A',strtotime($r['created_at'])))?></time></div><h3><?=e($r['title'])?><?php if(!$r['read_at']): ?><span class="inbox-unread-dot"><span class="sr-only">Unread</span></span><?php endif ?></h3><p><?=e($r['message'])?></p><div class="inbox-item-actions"><a href="<?=e($r['link'])?>">View details <span aria-hidden="true">↗</span></a><?php if(!$r['read_at']): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="read_notification"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="return" value="<?=e($return)?>"><button type="submit" aria-label="<?=e('Mark as read: '.$r['title'])?>">Mark as read</button></form><?php else: ?><span class="inbox-read-label">✓ Read</span><?php endif ?></div></div>
        </article>
    <?php endforeach ?>
</section>
<?php pagination(count($rows),$pageNum); ?>
