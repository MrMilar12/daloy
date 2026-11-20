<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Workspace memo &middot; DALOY</title>
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="<?=e(asset_url('app.css'))?>">
    <script src="<?=e(asset_url('app.js'))?>" defer></script>
</head>
<body class="memo-screen" data-page="memo">
    <main class="memo-sheet" aria-labelledby="memo-title">
        <header class="memo-letterhead">
            <span class="memo-letterhead-icon"><?=icon('reports')?></span>
            <div><strong>DALOY</strong><span><?=e($installed['setting_value'])?></span></div>
        </header>
        <article class="memo-document" tabindex="0" aria-labelledby="memo-title">
            <div class="memo-reading-column">
                <header class="memo-heading">
                    <p class="eyebrow">WORKSPACE MEMO</p>
                    <h1 id="memo-title"><?=e($memo['title'])?></h1>
                    <time datetime="<?=e(date('c',strtotime($memo['published_at'])))?>"><?=e(dt($memo['published_at']))?></time>
                </header>
                <div class="memo-message"><?=e($memo['body'])?></div>
                <?php if($memo['attachment']): ?>
                    <?php $displayMemo=$memo; require __DIR__.'/memo-attachment.php'; ?>
                <?php endif ?>
                <?php if($error): ?><p class="alert danger" role="alert"><?=e($error)?></p><?php endif ?>
            </div>
        </article>
        <footer class="memo-actions">
            <form method="post" action="?page=memo">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Sign out</button>
            </form>
            <form method="post" action="?page=memo">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="acknowledge_memo">
                <input type="hidden" name="memo_id" value="<?=(int)$memo['id']?>">
                <button class="primary" type="submit"><?=icon('availability')?> Mark as read</button>
            </form>
        </footer>
    </main>
</body>
</html>
