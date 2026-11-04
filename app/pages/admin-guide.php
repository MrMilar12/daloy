<?php
page_heading('ADMINISTRATOR GUIDE','How to use DALOY','Follow these steps to prepare your workspace and support day-to-day nursing operations.');
?>
<section class="card inset">
    <h2>Start with the organization</h2>
    <p>Use this guide after the application has been installed and your administrator account is active. Enter official records only; the optional demonstration data is fictional.</p>
</section>
<section class="card inset">
    <h2>1. Configure your organization</h2>
    <ol>
        <li>Open <a href="?page=configuration">Configuration</a> and review districts / areas, municipalities, duty types, deployment reasons, and shifts.</li>
        <li>Add or update the districts and duty categories your team actually uses. Keep names consistent so reports are easy to understand.</li>
        <li>Set up duty shifts, including overnight shifts where the end time is on the following day.</li>
    </ol>
</section>
<section class="card inset">
    <h2>2. Add schools and duty locations</h2>
    <ol>
        <li>Open <a href="?page=locations">Schools &amp; locations</a> and add each school, office, and venue where staff may be assigned.</li>
        <li>Choose its destination type and, when known, district and municipality. Set a daily coverage target only when your organization monitors one.</li>
        <li>Keep records current. Mark a location inactive instead of deleting it so historical assignments remain readable.</li>
    </ol>
</section>
<section class="card inset">
    <h2>3. Create and review user accounts</h2>
    <ol>
        <li>Open <a href="?page=users">User accounts</a> to create administrator, Nurse Head, and nurse accounts. Add each nurse's employee details, home assignment, additional schools, and relevant skills or competencies.</li>
        <li>Provide temporary passwords through an approved private channel. Users must change them at first sign-in.</li>
        <li>Review pending registration requests in User accounts before approving them. An approved request creates a nurse account.</li>
        <li>Deactivate accounts when access should end. Do not delete or repurpose an account with workforce history; its records are retained.</li>
    </ol>
</section>
<section class="card inset">
    <h2>4. Record schedules and availability</h2>
    <ol>
        <li>Use <a href="?page=schedule">Master schedule</a> to record authorized regular duty. Confirm the nurse, destination, duty type, and exact start and end times.</li>
        <li>Nurses record their own availability and submit leave from their account. A Nurse Head or administrator reviews leave requests; approved leave blocks overlapping assignments.</li>
        <li>Check <a href="?page=calendar">Calendar</a> and <a href="?page=availability">Availability</a> for conflicts. DALOY checks conflicts again when a record is saved; back-to-back intervals are allowed.</li>
    </ol>
</section>
<section class="card inset">
    <h2>5. Create a staffing need and deploy staff</h2>
    <ol>
        <li>Open <a href="?page=activities">Activities &amp; events</a> and create a requirement with its destination, dates and times, reason, number of nurses, and any required competency.</li>
        <li>Go to <a href="?page=deployment">Deployment center</a>, open the requirement, and review candidate eligibility and any conflict explanations.</li>
        <li>Confirm each assignment only after checking that the nurse, interval, and instructions are correct. DALOY checks conflicts and remaining capacity when saving.</li>
        <li>The assigned nurse reviews and acknowledges the instructions in their Deployment center. Check <a href="?page=deployment-history">Assignment history</a> for status and recorded acknowledgment.</li>
        <li>If an assignment must change, cancel it with a reason and create a replacement. DALOY retains the original assignment history.</li>
    </ol>
</section>
<section class="card inset">
    <h2>6. Monitor activity and report results</h2>
    <ol>
        <li>Use <a href="?page=dashboard">Overview</a> to review the selected day's workforce and open staffing needs.</li>
        <li>Check <a href="?page=notifications">Notifications</a> for account and workflow updates. Notifications are in-app; DALOY does not send email or SMS.</li>
        <li>Open <a href="?page=reports">Reports</a> to filter rosters, assignment history, availability, leave, and conflicts, then export or print the results. Narrow the date range for large histories.</li>
        <li>Use <a href="?page=audit">Audit trail</a> to review recorded administrative and workforce changes.</li>
    </ol>
</section>
<section class="card inset">
    <h2>7. Maintain settings and recovery copies</h2>
    <ol>
        <li>Use <a href="?page=settings">Settings</a> to update the organization name and workspace notice.</li>
        <li>Back up the database and referenced uploads regularly using the procedure in the project README. Store backup files encrypted and access-controlled; they contain personal information.</li>
        <li>Database credentials, HTTPS cookies, and server settings are managed outside DALOY in the server configuration. Coordinate those changes with the deployment administrator.</li>
    </ol>
</section>
