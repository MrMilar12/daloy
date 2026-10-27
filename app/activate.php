<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Finish account setup | DALOY</title>
    <link rel="stylesheet" href="<?=e(asset_url('app.css'))?>">
    <script src="<?=e(asset_url('app.js'))?>" defer></script>
</head>
<body class="activation-body" data-page="activate" data-setup-initial-step="<?=e(($error && ($_POST['setup_step']??'')==='2')?'2':'1')?>">
<?php $setupDistricts=$db->all('SELECT id,name FROM districts WHERE active=1 ORDER BY name'); $setupLocations=$db->all('SELECT id,name FROM locations WHERE active=1 ORDER BY name'); ?>
    <header class="activation-header">
        <div class="brand"><span class="brand-mark">D</span><span>DALOY<small>SDO AURORA</small></span></div>
        <div class="top-actions">
            <button class="theme-toggle icon-button" type="button" aria-label="Toggle color theme">◐</button>
            <form method="post" action="?page=activate">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Sign out</button>
            </form>
        </div>
    </header>
    <main class="activation-card card" id="account-setup">
        <span class="eyebrow">ONE-TIME ACCOUNT SETUP</span>
        <div class="setup-steps" aria-label="Account setup progress"><span class="active" data-setup-indicator="1">1. Password</span><span data-setup-indicator="2">2. Personnel details</span></div>
        <?php if($error): ?><div class="alert danger" role="alert" tabindex="-1" data-focus-error><?=e($error)?></div><?php endif ?>
        <section data-setup-panel="1">
            <h1>Choose a new password</h1>
            <p>You’re signed in as <strong><?=e($user['name'])?></strong>. Replace your temporary password, then add your personnel details to open the workspace.</p>
            <form class="activation-form" id="activation-password-form" novalidate>
                <label for="setup-current-password">Temporary password</label>
                <input id="setup-current-password" name="current_password" type="password" required autocomplete="current-password" maxlength="72" autofocus>
                <label for="setup-new-password">New password</label>
                <input id="setup-new-password" name="new_password" type="password" required autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="setup-password-help">
                <p id="setup-password-help" class="help">At least 12 characters. A memorable phrase works well.</p>
                <label for="setup-confirm-password">Confirm new password</label>
                <input id="setup-confirm-password" name="confirm_password" type="password" required autocomplete="new-password" minlength="12" maxlength="72">
                <button type="button" class="password-toggle" data-password-toggle aria-pressed="false" aria-controls="setup-current-password setup-new-password setup-confirm-password">Show passwords</button>
                <button type="button" class="primary full setup-next" data-setup-next>Next step <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <section data-setup-panel="2" hidden>
            <h1>Add your personnel details</h1>
            <p>Complete the required details below. You can add optional information later from your profile.</p>
            <form method="post" action="?page=activate" class="activation-form" id="activation-personnel-form">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="activate">
                <input type="hidden" name="username" value="<?=e($user['email'])?>" autocomplete="username">
                <input type="hidden" name="setup_step" value="2">
                <input type="hidden" name="current_password" data-password-value="current_password">
                <input type="hidden" name="new_password" data-password-value="new_password">
                <input type="hidden" name="confirm_password" data-password-value="confirm_password">
                <p class="help">Fields marked required must be completed before your dashboard opens.</p>
                <label for="setup-employee-number">Employee number <span>(optional)</span></label>
                <input id="setup-employee-number" name="employee_number" maxlength="60" value="<?=e($_POST['employee_number']??'')?>">
                <label for="setup-designation">Position / designation <span>(required)</span></label>
                <input id="setup-designation" name="designation" required maxlength="120" value="<?=e($_POST['designation']??'Nurse')?>">
                <label for="setup-employment-status">Employment status <span>(required)</span></label>
                <select id="setup-employment-status" name="employment_status" required><?php foreach(['Permanent','Contractual','Job Order','Temporary'] as $status): ?><option<?=($_POST['employment_status']??'Permanent')===$status?' selected':''?>><?=e($status)?></option><?php endforeach ?></select>
                <label for="setup-district">Assigned district / area <span>(<?= $setupDistricts ? 'required' : 'optional' ?>)</span></label>
                <select id="setup-district" name="district_id"<?= $setupDistricts ? ' required' : '' ?>><option value="">Select district / area<?= $setupDistricts ? '' : ' (not configured yet)' ?></option><?php foreach($setupDistricts as $district): ?><option value="<?=$district['id']?>"<?=($_POST['district_id']??'')==(string)$district['id']?' selected':''?>><?=e($district['name'])?></option><?php endforeach ?></select>
                <label for="setup-location">Home school / office <span>(<?= $setupLocations ? 'required' : 'optional' ?>)</span></label>
                <select id="setup-location" name="location_id"<?= $setupLocations ? ' required' : '' ?>><option value="">Select home school / office<?= $setupLocations ? '' : ' (not configured yet)' ?></option><?php foreach($setupLocations as $location): ?><option value="<?=$location['id']?>"<?=($_POST['location_id']??'')==(string)$location['id']?' selected':''?>><?=e($location['name'])?></option><?php endforeach ?></select>
                <label for="setup-phone">Contact number <span>(optional)</span></label>
                <input id="setup-phone" name="phone" maxlength="40" value="<?=e($_POST['phone']??'')?>">
                <label for="setup-specialization">Specialization <span>(optional)</span></label>
                <input id="setup-specialization" name="specialization" maxlength="190" value="<?=e($_POST['specialization']??'')?>">
                <label for="setup-skills">Relevant skills / competencies <span>(optional)</span></label>
                <textarea id="setup-skills" name="skills" rows="3" maxlength="5000"><?=e($_POST['skills']??'')?></textarea>
                <div class="setup-actions"><button type="button" class="button" data-setup-back>Back</button><button type="submit" class="primary">Save details and open dashboard <span aria-hidden="true">→</span></button></div>
            </form>
        </section>
        <p class="activation-account"><?=e($user['email'])?> · <?=e(['admin'=>'Administrator','head'=>'Nurse Head','nurse'=>'Nursing personnel'][$user['role']])?></p>
    </main>
</body>
</html>