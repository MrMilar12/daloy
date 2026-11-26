<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Sign in · DALOY+</title>
    <link rel="stylesheet" href="<?=e(asset_url('login.css'))?>">
    <script src="<?=e(asset_url('app.js'))?>" defer></script>
</head>
<body class="auth-page">
    <header class="auth-topbar">
        <a class="auth-wordmark" href="./" aria-label="DALOY home">DALOY<span>+</span></a>
        <div class="auth-topbar-right"><button class="theme-toggle" type="button" aria-label="Toggle color theme"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg></button></div>
    </header>
    <main class="auth-shell">
        <section class="auth-story" aria-labelledby="auth-story-title">
            <div class="auth-story-waves" aria-hidden="true"></div>
            <div class="auth-story-kicker"><span></span> NURSING WORKFORCE MANAGEMENT</div>
            <div class="auth-logo-frame"><img class="auth-logo" src="<?=e(asset_url('daloy-mark.svg'))?>" alt="DALOY+" width="512" height="512" fetchpriority="high"></div>
            <div class="auth-story-copy">
                <span class="auth-story-label">DALOY+ · NURSING WORKFORCE MANAGEMENT</span>
                <h1 id="auth-story-title">One team.<br><strong>Better care.</strong></h1>
                <p>Plan nursing duties, coordinate school coverage, and keep every assignment in view.</p>
            </div>

            <div class="auth-story-footer"><span class="auth-plus" aria-hidden="true">+</span><span>Supporting the people who care for our schools.</span></div>
        </section>
        <section class="login-form auth-panel" aria-labelledby="auth-title">
            <div class="auth-panel-content">
                <div class="auth-welcome-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg></div>
                <span class="auth-kicker">YOUR WORKSPACE, CONNECTED</span>
                <h2 id="auth-title">Welcome back</h2>
                <p class="auth-intro">Sign in to manage your day with DALOY+.</p>
                <?php if($error): ?><div class="auth-alert" role="alert" tabindex="-1" data-focus-error><?=e($error)?></div><?php endif ?>
                <?php if(isset($_SESSION['flash'])): ?><div class="auth-alert auth-success" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="action" value="login">
                    <label for="auth-email">Email address</label>
                    <input id="auth-email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?=e($_POST['email']??'')?>" placeholder="Email address">
                    <div class="auth-password-label"><label for="auth-password">Password</label><button class="auth-password-toggle" type="button" data-password-toggle data-show-label="Show password" data-hide-label="Hide password" aria-pressed="false" aria-controls="auth-password">Show password</button></div>
                    <input id="auth-password" name="password" type="password" required maxlength="72" autocomplete="current-password" placeholder="Enter your password">
                    <button class="auth-submit" type="submit">Sign in to DALOY+<span aria-hidden="true">&rarr;</span></button>
                </form>
                <p class="auth-support">Need help signing in?<br>Contact your DALOY system administrator.</p>
            </div>
            <footer class="auth-panel-footer">DALOY+<strong>Nursing Workforce Management</strong></footer>
        </section>
    </main>
    <footer class="auth-footer">DALOY &middot; Duty Allocation &amp; Logistics Operations for Your Workforce</footer>
</body>
</html>
