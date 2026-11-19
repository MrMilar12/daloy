<?php
$attachmentUrl='download.php?type=memo&id='.(int)$displayMemo['id'];
$attachmentExtension=pathinfo($displayMemo['attachment'],PATHINFO_EXTENSION);
?>
<?php if(in_array($attachmentExtension,['jpg','png'],true)): ?>
    <figure class="memo-image-preview">
        <img src="<?=e($attachmentUrl.'&preview=1')?>" alt="Image attached to <?=e($displayMemo['title'])?>" loading="lazy">
    </figure>
<?php endif ?>
<a class="memo-attachment button" href="<?=e($attachmentUrl)?>"><?=icon('reports')?> Download attachment</a>
