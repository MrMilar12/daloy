<?php
$attachmentUrl='download.php?type=memo&id='.(int)$displayMemo['id'];
$attachmentExtension=pathinfo($displayMemo['attachment'],PATHINFO_EXTENSION);
?>
<?php if(in_array($attachmentExtension,['jpg','png'],true)): ?>
    <figure class="memo-image-preview">
        <img src="<?=e($attachmentUrl.'&preview=1')?>" alt="Image attached to <?=e($displayMemo['title'])?>" loading="lazy">
    </figure>
<?php elseif($attachmentExtension==='pdf'): ?>
    <a class="memo-attachment button primary" href="<?=e($attachmentUrl.'&preview=1')?>" target="_blank" rel="noopener"><?=icon('reports')?> Open PDF <span>(new tab)</span></a>
<?php endif ?>
<a class="memo-attachment button" href="<?=e($attachmentUrl)?>"><?=icon('reports')?> Download attachment</a>
