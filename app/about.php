<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About &amp; privacy · DALOY</title>
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="<?=e(asset_url('login.css'))?>">
</head>
<body class="auth-page auth-registration-page">
    <header class="auth-topbar"><a class="auth-wordmark" href="./">DALOY</a></header>
    <main class="auth-shell">
        <section class="auth-panel" aria-labelledby="about-title">
            <div class="auth-panel-content">
                <h1 id="about-title">About DALOY</h1>
                <p>DALOY is a nursing workforce scheduling and deployment application intended for the SDO Aurora nursing workforce. Access requests are reviewed by a DALOY administrator.</p>
                <h2>Accounts and hosting</h2>
                <p>The production address is <code>https://sdohealth.page.gd/</code>, a third-party domain. This application uses its own DALOY accounts. It does not sign you in to DepEd, Google, or another service. Use a unique DALOY password; never enter another service’s password here.</p>
                <h2>Data used by the application</h2>
                <p>Access requests collect your name, email address, requested role, and a password stored as a password hash. The workspace stores personnel, school assignments, schedules, deployments, leave records, and uploaded supporting documents supplied by users and administrators.</p>
                <p>Access depends on account role. Authorized managers and administrators can view records needed for workforce management. The application records account activity and sign-in attempts for access control and auditing, and uses a session cookie to keep you signed in.</p>
                <h2>Help and privacy requests</h2>
                <p>Contact your DALOY system administrator through your established workplace contact channel to verify this installation, request account assistance, or ask about access, correction, retention, and deletion of your records. Verify the address with your administrator before submitting personal information.</p>
                <p><a href="./">Return to sign in</a></p>
            </div>
        </section>
    </main>
</body>
</html>
