<?php $register=isset($_GET['register']); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?=$register?'Request access':'Sign in'?> &middot; DALOY</title>
    <link rel="stylesheet" href="<?=e(asset_url('login.css'))?>">
    <script src="<?=e(asset_url('app.js'))?>" defer></script>
</head>
<body class="auth-page">
    <header class="auth-topbar">
        <a class="auth-wordmark" href="./" aria-label="DALOY home">DALOY<span>+</span></a>
        <div class="auth-topbar-right"><span>SDO AURORA</span><button class="theme-toggle" type="button" aria-label="Toggle color theme"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg></button></div>
    </header>
    <main class="auth-shell">
        <section class="auth-story" aria-labelledby="auth-story-title">
            <div class="auth-story-waves" aria-hidden="true"></div>
            <div class="auth-story-kicker"><span></span> NURSING WORKFORCE MANAGEMENT</div>
            <div class="auth-logo-frame"><img class="auth-logo" src="<?=e(asset_url('daloy-logo.png'))?>" alt="DALOY: Duty Allocation &amp; Logistics Operations for Your Workforce" width="1536" height="1024" fetchpriority="high"></div>
            <div class="auth-story-copy">
                <span class="auth-story-label">DALOY · SDO AURORA</span>
                <h1 id="auth-story-title">One team.<br><strong>Better care.</strong></h1>
                <p>Plan nursing duties, coordinate school coverage, and keep every assignment in view.</p>
            </div>

            <div class="auth-story-footer"><span class="auth-plus" aria-hidden="true">+</span><span>Supporting the people who care for our schools.</span></div>
        </section>
        <section class="login-form auth-panel" aria-labelledby="auth-title">
            <div class="auth-panel-content">
                <div class="auth-welcome-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg></div>
                <span class="auth-kicker"><?=$register?'JOIN YOUR WORKSPACE':'YOUR WORKSPACE, CONNECTED'?></span>
                <h2 id="auth-title"><?=$register?'Request access':'Welcome back'?></h2>
                <p class="auth-intro"><?=$register?'Start your DALOY registration. An administrator will review your request.':'Sign in to manage your day with DALOY.'?></p>
                <?php if($error): ?><div class="auth-alert" role="alert" tabindex="-1" data-focus-error><?=e($error)?></div><?php endif ?>
                <?php if(isset($_SESSION['flash'])): ?><div class="auth-alert auth-success" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="action" value="<?=$register?'register':'login'?>">
                    <?php if($register): ?>
                    <label for="auth-name">Full name</label>
                    <input id="auth-name" name="name" required maxlength="120" autocomplete="name" value="<?=e($_POST['name']??'')?>" placeholder="Enter your full name">
                    <?php endif ?>
                    <label for="auth-email">Email address</label>
                    <input id="auth-email" name="email" type="email" required maxlength="190" autocomplete="<?=$register?'email':'username'?>" value="<?=e($_POST['email']??'')?>" placeholder="you@example.gov.ph">
                    <div class="auth-password-label"><label for="auth-password"><?=$register?'Temporary password':'Password'?></label><button class="auth-password-toggle" type="button" data-password-toggle data-show-label="Show password" data-hide-label="Hide password" aria-pressed="false" aria-controls="auth-password">Show password</button></div>
                    <input id="auth-password" name="password" type="password" required maxlength="72" <?=$register?'minlength="12" aria-describedby="auth-password-help"':''?> autocomplete="<?=$register?'new-password':'current-password'?>" placeholder="<?=$register?'Create a temporary password':'Enter your password'?>">
                    <?php if($register): ?><p class="auth-help" id="auth-password-help">Use 12 or more characters with uppercase, lowercase, and a number. After approval, you will replace this password at first sign-in.</p><?php endif ?>
                    <button class="auth-submit" type="submit"><?=$register?'Submit registration':'Sign in to DALOY'?><span aria-hidden="true">&rarr;</span></button>
                </form>
                <div class="auth-register">
                    <span><?=$register?'Already have an account?':'New to DALOY?'?></span>
                    <a href="<?=$register?'./':'?register=1'?>"><?=$register?'Back to sign in':'Register for access'?> <span aria-hidden="true">&rarr;</span></a>
                </div>
                <p class="auth-support">Need help signing in?<br>Contact your DALOY system administrator.</p>
            </div>
            <footer class="auth-panel-footer">Department of Education<strong>Schools Division Office of Aurora</strong></footer>
        </section>
    </main>
    <footer class="auth-footer">DALOY &middot; Duty Allocation &amp; Logistics Operations for Your Workforce</footer>
</body>
</html>
