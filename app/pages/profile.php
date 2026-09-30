<?php
$person=$db->one('SELECT u.*,d.name district_name,l.name home_name,m.name municipality_name FROM users u LEFT JOIN districts d ON d.id=u.district_id LEFT JOIN locations l ON l.id=u.location_id LEFT JOIN municipalities m ON m.id=l.municipality_id WHERE u.id=?',[$user['id']]);
$assignments=$service->assignedLocations((int)$user['id']);
$additional=array_filter($assignments,fn($school)=>(int)$school['id']!==(int)$person['location_id']);
$municipalities=options('municipalities','1=1');
$roleLabel=['admin'=>'Administrator','head'=>'Nurse Head / Supervisor','nurse'=>'Nurse'][$person['role']];
page_heading('YOUR ACCOUNT','My profile','Your personnel record, school coverage, and account details.',$user['role']==='admin'?add_link('?page=users&edit='.$user['id'],'Edit personnel details'):'');
?>
<div class="person-profile">
    <aside class="profile-id-sidebar">
        <section class="employee-id" aria-label="Your employee profile card">
            <div class="employee-id-red">
                <img class="employee-id-seal employee-id-deped-seal" src="<?=e(asset_url('deped-seal.png'))?>" alt="Department of Education (DepEd) seal" width="102" height="102">
                <h2><?=e($person['name'])?></h2>
                <p class="employee-id-department"><?=e($person['district_name']?:'Schools Division Office of Aurora')?></p>
                <p class="employee-id-number"><span>ID NO.</span> <?=e($person['employee_number']?:'Not recorded')?></p>
                <div class="employee-id-designation"><?=e($person['designation']?:$roleLabel)?></div>
            </div>
            <div class="employee-id-bottom">
                <div class="employee-id-employment"><strong><?=e($roleLabel)?></strong><span><?=e($person['employment_status'])?> employee</span><?=badge($person['active']?'Active':'Inactive')?></div>
                <a class="employee-id-photo" href="#profile-photo-editor" aria-label="Change your profile photo"><?php if($person['photo']): ?><img src="download.php?type=photo&id=<?=$user['id']?>" alt="Your profile photo"><?php else: ?><?=avatar($person['name'])?><?php endif ?><span class="employee-id-camera" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 5l2-2h4l2 2h4v15H4V5z"/><circle cx="12" cy="12" r="4"/></svg></span></a>
                <div class="employee-id-brand"><strong>DALOY<span>+</span></strong><small>NURSING WORKFORCE<br>SDO AURORA</small></div>
                <a class="employee-id-photo-link" href="#profile-photo-editor">Change photo <span aria-hidden="true">↗</span></a>
            </div>
        </section>
        <section class="card person-profile-identity profile-id-contact" aria-label="Contact details">
        <dl class="person-profile-contact"><dt>Email address</dt><dd><?=e($person['email'])?></dd><dt>Contact number</dt><dd><?=e($person['phone']?:'Not recorded')?></dd><dt>Employee number</dt><dd><?=e($person['employee_number']?:'Not recorded')?></dd></dl>
        <div class="person-profile-count"><strong><?=count($assignments)?></strong><span>Assigned school<?=count($assignments)===1?'':'s'?> / office<?=count($assignments)===1?'':'s'?></span></div>
        <p class="person-profile-note"><?=$user['role']==='admin'?'Use Edit personnel details to update your official record.':'Contact your administrator to update your official personnel details and school assignments.'?></p>
        </section>
    </aside>
    <div class="person-profile-main">
        <section class="card person-profile-section"><div class="person-profile-section-heading"><?=icon('profile')?><div><h2>Personnel details</h2><p>Your official work information.</p></div></div><dl class="person-profile-fields">
            <?php foreach(['Full name'=>$person['name'],'Position / designation'=>$person['designation'],'Employee number'=>$person['employee_number'],'Employment status'=>$person['employment_status'],'Account role'=>$roleLabel,'District / area'=>$person['district_name'],'Account created'=>date('F j, Y',strtotime($person['created_at'])),'Account status'=>$person['active']?'Active':'Inactive'] as $label=>$value): ?><div><dt><?=e($label)?></dt><dd class="<?=$value?'':'not-recorded'?>"><?=e($value?:'Not recorded')?></dd></div><?php endforeach ?>
        </dl></section>
        <section class="card person-profile-section"><div class="person-profile-section-heading"><?=icon('locations')?><div><h2>Schools & assignments</h2><p>Your home location and additional school coverage.</p></div><span class="pill-count"><?=count($assignments)?></span></div>
            <div class="profile-home-school"><span class="eyebrow">HOME SCHOOL / OFFICE</span><strong><?=e($person['home_name']?:'Not assigned')?></strong><?php if($person['municipality_name']): ?><small><?=e($person['municipality_name'])?>, Aurora</small><?php endif ?></div>
            <h3 class="profile-additional-title">Additional assigned schools <span><?=count($additional)?></span></h3>
            <?php if(!$additional): ?><p class="person-profile-note">No additional schools assigned.</p><?php else: ?><ul class="profile-school-list"><?php foreach($additional as $school): ?><li><span class="profile-school-icon" aria-hidden="true"><?=icon('locations')?></span><div><strong><?=e($school['name'])?></strong><small><?=e($municipalities[$school['municipality_id']]??'Municipality not recorded')?><?=$school['active']?'':' · Inactive'?></small></div></li><?php endforeach ?></ul><?php endif ?>
        </section>
        <section class="card person-profile-section"><div class="person-profile-section-heading"><?=icon('nurses')?><div><h2>Specialization & competencies</h2><p>Professional skills recorded for duty assignments.</p></div></div><dl class="person-profile-fields"><div><dt>Specialization</dt><dd><?=e($person['specialization']?:'Not recorded')?></dd></div><div><dt>Relevant skills / competencies</dt><dd class="profile-skills"><?=e($person['skills']?:'Not recorded')?></dd></div></dl></section>
        <section class="card editor person-profile-section"><div class="person-profile-section-heading"><?=icon('settings')?><div><h2>Contact & security</h2><p>Update your phone, photo, or password.</p></div></div>
            <?php form_start('profile','?page=profile'); input('phone','Contact number',$error?($_POST['phone']??$person['phone']):$person['phone'],'tel',false,'maxlength="40" autocomplete="tel"'); ?><label id="profile-photo-editor">Profile photo<input type="file" name="photo" accept=".jpg,.jpeg,.png"><small>JPEG or PNG, up to 5 MB.</small></label><div class="span-2 profile-password-heading"><h3>Change password</h3><p>Leave these fields blank to keep your current password. Use 12–72 bytes for a new password.</p></div><?php input('current_password','Current password','','password',false,'autocomplete="current-password"'); input('new_password','New password','','password',false,'autocomplete="new-password" minlength="12" maxlength="72"'); input('confirm_password','Confirm new password','','password',false,'autocomplete="new-password" minlength="12" maxlength="72"'); form_end('?page=profile','Save profile'); ?>
        </section>
    </div>
</div>
