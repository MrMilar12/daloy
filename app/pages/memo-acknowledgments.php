<?php
$memos=$db->all('SELECT id,title,published_at FROM workspace_memos ORDER BY id DESC');
$selectedId=(int)($_GET['memo_id']??($memos[0]['id']??0));
$selected=$selectedId?$db->one('SELECT * FROM workspace_memos WHERE id=?',[$selectedId]):null;
$memoFormReturn='?'.http_build_query(['page'=>'memo-acknowledgments']+($selected && isset($_GET['memo_id'])?['memo_id'=>$selectedId]:[]));
$publishUrl=$memoFormReturn.'&publish=1';
page_heading('SYSTEM ADMINISTRATION','Memo acknowledgments','See who marked each workspace memo as read.','<a class="button primary" href="'.e($publishUrl).'">Publish</a>');
?>
<?php if(($_GET['publish']??'')==='1'): ?>
    <dialog class="record-modal memo-publish-modal" data-record-modal aria-labelledby="memo-publish-title">
        <section class="card editor record-modal-card">
            <a class="record-modal-close" href="<?=e($memoFormReturn)?>" aria-label="Close publish form">×</a>
            <header class="record-modal-heading">
                <span class="record-modal-mark"><?=icon('reports')?></span>
                <div><h2 id="memo-publish-title">Publish a workspace memo</h2><p>Users will see this memo before entering the workspace.</p></div>
            </header>
            <?php if($error): ?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif ?>
            <?php $memoPublishReturn='?page=memo-acknowledgments'; require __DIR__.'/../memo-publish-form.php'; ?>
        </section>
    </dialog>
<?php endif ?>
<?php if(!$selected): ?>
    <div class="card empty"><h2><?=$memos?'Memo unavailable':'No memos published yet'?></h2><a class="button" href="<?=$memos?'?page=memo-acknowledgments':'?page=settings'?>"><?=$memos?'View latest memo':'Go to settings'?></a></div>
<?php else:
    $total=(int)$db->one('SELECT COUNT(*) AS total FROM memo_acknowledgments WHERE memo_id=?',[$selectedId])['total'];
    $readers=$db->all("SELECT u.name,u.role,a.acknowledged_at FROM memo_acknowledgments a JOIN users u ON u.id=a.user_id WHERE a.memo_id=? ORDER BY a.acknowledged_at DESC,a.user_id LIMIT 26 OFFSET $offset",[$selectedId]);
?>
    <form class="filters" method="get">
        <input type="hidden" name="page" value="memo-acknowledgments">
        <label>Memo<select name="memo_id">
            <?php foreach($memos as $item): ?>
                <option value="<?=(int)$item['id']?>" <?=(int)$item['id']===$selectedId?'selected':''?>><?=e($item['title'].' · '.dt($item['published_at']))?></option>
            <?php endforeach ?>
        </select></label>
        <button type="submit">View acknowledgments</button>
    </form>
    <div class="memo-report-layout">
    <article class="card memo-report-message" aria-labelledby="published-memo-title">
        <header class="section-heading">
            <div><h2 id="published-memo-title"><?=e($selected['title'])?></h2><p>Published <?=e(dt($selected['published_at']))?></p></div>
        </header>
        <div class="memo-report-body">
            <div class="memo-message"><?=e($selected['body'])?></div>
            <?php if($selected['attachment']): ?>
                <?php $displayMemo=$selected; require __DIR__.'/../memo-attachment.php'; ?>
            <?php endif ?>
        </div>
    </article>
    <aside class="card memo-report-readers" aria-labelledby="memo-readers-title">
        <header class="section-heading">
            <h2 id="memo-readers-title">Acknowledgments</h2>
            <span class="pill-count"><?=number_format($total)?> acknowledged</span>
        </header>
        <?php if($readers): ?>
        <ul class="memo-reader-list">
            <?php foreach(array_slice($readers,0,25) as $reader): ?>
                <li>
                    <span aria-hidden="true"><?=avatar($reader['name'])?></span>
                    <div><strong><?=e($reader['name'])?></strong><small><?=e(['admin'=>'Administrator','head'=>'Nurse Head','nurse'=>'Nursing personnel'][$reader['role']]??$reader['role'])?></small>
                        <time datetime="<?=e(date('c',strtotime($reader['acknowledged_at'])))?>"><?=e(dt($reader['acknowledged_at']))?></time>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
        <?php else: ?><div class="empty"><p><?=$pageNum>1?'No acknowledgments on this page.':'No one has acknowledged this memo yet.'?></p></div><?php endif ?>
        <?php pagination(count($readers),$pageNum); ?>
    </aside>
    </div>
<?php endif ?>
