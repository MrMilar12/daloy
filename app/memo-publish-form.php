<?php
$memoFormData=($_POST['action']??'')==='publish_memo'?$_POST:[];
form_start('publish_memo',$memoPublishReturn??$memoFormReturn);
?>
<label class="span-2">Subject<input name="title" maxlength="190" required value="<?=e($memoFormData['title']??'')?>"></label>
<label class="span-2 memo-file-field">Attach a file (optional)
    <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="memo-file-help">
    <small id="memo-file-help">PDF, JPEG, or PNG up to 5 MB.</small>
</label>
<label class="span-2">Memo message<textarea name="body" rows="5" maxlength="2000" required><?=e($memoFormData['body']??'')?></textarea></label>
<?php form_end($memoFormReturn,'Publish memo'); ?>
