<?php
$pageTitle = 'Client Content & Settings';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$admin = require_admin();

$editableSettings = [
    'brand_name' => 'Full brand name',
    'brand_short' => 'Short logo name',
    'home_meta_title' => 'Homepage browser title',
    'home_hero_title' => 'Homepage hero title',
    'home_hero_subtitle' => 'Homepage hero subtitle',
    'home_apy' => 'Homepage APY stat',
    'home_rating' => 'Homepage rating stat',
    'home_services_title' => 'Services section title',
    'home_services_copy' => 'Services section copy',
    'contact_intro' => 'Contact page intro',
    'support_phone' => 'Support phone',
    'support_call_number' => 'Click-to-call number',
    'support_whatsapp_number' => 'WhatsApp number',
    'support_email' => 'Support email',
    'footer_summary' => 'Footer summary',
    'announcement' => 'Member announcement',
    'deposit_protection_overrides' => 'Deposit protection country overrides (JSON)',
    'theme_navy' => 'Primary brand blue',
    'theme_gold' => 'Sky blue accent',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($editableSettings as $key => $label) {
        if (array_key_exists($key, $_POST)) {
            save_setting($key, trim((string) $_POST[$key]));
        }
    }
    log_admin((int) $admin['id'], 'settings_update', 'Updated client-facing content and platform settings');
    flash('success', 'Client-side content and settings updated.');
    header('Location: settings.php');
    exit;
}
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<form class="premium-card p-4 mb-4" method="post">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h5 class="fw-bold mb-1">Client-side content manager</h5>
            <p class="muted mb-0">Edit public website copy, support details, announcement text, and theme colors from one admin screen.</p>
        </div>
        <button class="btn btn-gold"><i class="fa-solid fa-floppy-disk me-1"></i>Save changes</button>
    </div>
    <div class="row g-3">
        <?php foreach ($editableSettings as $key => $label): ?>
            <div class="<?= in_array($key, ['home_hero_subtitle','home_services_copy','contact_intro','footer_summary','announcement','deposit_protection_overrides'], true) ? 'col-12' : 'col-md-6' ?>">
                <label class="form-label"><?= e($label) ?></label>
                <?php if (in_array($key, ['home_hero_subtitle','home_services_copy','contact_intro','footer_summary','announcement','deposit_protection_overrides'], true)): ?>
                    <textarea name="<?= e($key) ?>" class="form-control" rows="<?= $key === 'deposit_protection_overrides' ? 5 : 3 ?>" placeholder="<?= $key === 'deposit_protection_overrides' ? e('{"country_code":{"agency":"Agency","name":"Agency Protected","text":"Short approved disclosure wording"}}') : '' ?>"><?= e(setting($key)) ?></textarea>
                    <?php if ($key === 'deposit_protection_overrides'): ?><div class="small muted mt-2">Optional. Agency claims display only when a country mapping sets enabled to true.</div><?php endif; ?>
                <?php elseif (str_ends_with($key, '_color') || str_starts_with($key, 'theme_')): ?>
                    <input name="<?= e($key) ?>" type="color" class="form-control form-control-color" value="<?= e(setting($key, $key === 'theme_navy' ? '#0052FF' : '#2F7DFF')) ?>">
                <?php else: ?>
                    <input name="<?= e($key) ?>" class="form-control" value="<?= e(setting($key)) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</form>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="premium-card p-4">
            <h5 class="fw-bold">Role permissions</h5>
            <p class="muted">Super Admin: full access. Operations: deposits, transfers, monitoring. Support: notifications and user view.</p>
            <div class="form-check"><input class="form-check-input" checked type="checkbox"><label class="form-check-label">Require transaction codes on transfers</label></div>
            <div class="form-check"><input class="form-check-input" checked type="checkbox"><label class="form-check-label">Log all admin actions</label></div>
            <div class="form-check"><input class="form-check-input" checked type="checkbox"><label class="form-check-label">Throttle failed logins</label></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="premium-card p-4">
            <h5 class="fw-bold">Platform settings</h5>
            <p class="muted">Default BIC/SWIFT <?= DEFAULT_BIC ?>. Upload limit 4MB. Session cookie httponly and SameSite Lax.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
