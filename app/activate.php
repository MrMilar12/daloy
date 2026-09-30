<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Finish account setup · DALOY</title>
    <link rel="stylesheet" href="<?=e(asset_url('app.css'))?>">
    <script src="<?=e(asset_url('app.js'))?>" defer></script>
</head>
<body class="activation-body" data-page="activate">
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
        <span class="eyebrow">FIRST SIGN-IN · ACCOUNT SETUP</span>
        <h1>Set your password <br>to open your workspace.</h1>
        <p>You’re signed in as <strong><?=e($user['name'])?></strong>.<br>Replace your temporary password once to finish setting up your account.</p>
        <div class="activation-explanation">
            <strong>Your workspace opens after this step.</strong>
            <p>The dashboard, calendar, schedules, and other pages will be available as soon as you save your new password.</p>
        </div>
        <?php if($error): ?><div class="alert danger" role="alert" tabindex="-1" data-focus-error><?=e($error)?></div><?php endif ?>
        <form method="post" action="?page=activate" class="activation-form" data-validate>
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="username" value="<?=e($user['email'])?>" autocomplete="username">
            <label for="setup-current-password">Temporary password you used to sign in</label>
            <input id="setup-current-password" name="current_password" type="password" required autocomplete="current-password" maxlength="72" autofocus>
            <label for="setup-new-password">New password</label>
            <input id="setup-new-password" name="new_password" type="password" required autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="setup-password-help">
            <p id="setup-password-help" class="help">Use at least 12 characters. A memorable phrase works well. Maximum 72 bytes.</p>
            <label for="setup-confirm-password">Confirm new password</label>
            <input id="setup-confirm-password" name="confirm_password" type="password" required autocomplete="new-password" minlength="12" maxlength="72">
            <button type="button" class="password-toggle" data-password-toggle aria-pressed="false" aria-controls="setup-current-password setup-new-password setup-confirm-password">Show passwords</button>
            <button type="submit" class="primary full">Save password & open dashboard <span aria-hidden="true">→</span></button>
        </form>
        <p class="activation-account"><?=e($user['email'])?> · <?=e(['admin'=>'Administrator','head'=>'Nurse Head','nurse'=>'Nursing personnel'][$user['role']])?></p>
    </main>
</body>
</html>
