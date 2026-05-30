<?php
$pageTitle = 'Profile & Security';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sms.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['profile'])) {
        $avatarAttempted = isset($_FILES['avatar']) && is_array($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $avatar = secure_upload($_FILES['avatar'] ?? [], __DIR__ . '/../uploads/avatars');
        if ($avatarAttempted && $avatar === null) {
            flash('danger', 'We could not upload that profile picture. Please choose a JPG, PNG, WEBP, or PDF file under 5 MB.');
            header('Location: profile.php');
            exit;
        }
        $phoneRaw = trim((string) $_POST['phone']);
        $countryCode = trim((string) ($_POST['phone_country_code'] ?? '+49'));
        $phone = str_starts_with($phoneRaw, '+') ? normalize_sms_phone($phoneRaw) : normalize_sms_phone($countryCode . ltrim($phoneRaw, '0'));
        if ($phone === '') {
            flash('danger', 'Enter your phone number with country code, for example +4915123456789.');
            header('Location: profile.php');
            exit;
        }
        db()->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, avatar=COALESCE(?, avatar) WHERE id=?')->execute([
            trim((string) $_POST['first_name']),
            trim((string) $_POST['last_name']),
            $phone,
            $avatar,
            $user['id'],
        ]);
        flash('success', 'Profile updated.');
    }
    if (isset($_POST['password']) && strlen($_POST['new_password'] ?? '') >= 8) {
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['new_password'], PASSWORD_BCRYPT), $user['id']]);
        flash('success', 'Password updated.');
    }
    if (isset($_POST['pin']) && preg_match('/^\d{4}$/', (string) ($_POST['new_pin'] ?? ''))) {
        db()->prepare('UPDATE users SET transaction_pin_hash=? WHERE id=?')->execute([password_hash((string) $_POST['new_pin'], PASSWORD_BCRYPT), $user['id']]);
        flash('success', 'Transaction code updated.');
    }
    header('Location: profile.php');
    exit;
}
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<?= deposit_protection_badge($user, user_account((int) $user['id']), 'mb-4') ?>
<div class="row g-4">
    <div class="col-lg-6">
        <form class="premium-card p-4" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="d-flex align-items-center gap-3 mb-3">
                <img class="avatar-lg" src="<?= e(avatar_url($user['avatar'] ?? null)) ?>" alt="Profile picture">
                <div><h5 class="fw-bold mb-1">Profile picture</h5><p class="muted mb-0">Upload a JPG, PNG, or WebP image up to 4MB.</p></div>
            </div>
            <input type="hidden" name="profile" value="1">
            <input name="first_name" class="form-control mb-3" value="<?= e($user['first_name']) ?>">
            <input name="last_name" class="form-control mb-3" value="<?= e($user['last_name']) ?>">
            <div class="input-group mb-3">
                <select name="phone_country_code" class="form-select" style="max-width: 145px"><option value="+49">DE +49</option><option value="+43">AT +43</option><option value="+41">CH +41</option><option value="+33">FR +33</option><option value="+31">NL +31</option><option value="+234">NG +234</option><option value="+44">UK +44</option></select>
                <input name="phone" class="form-control" value="<?= e($user['phone']) ?>" placeholder="+4915123456789">
            </div>
            <input name="avatar" type="file" accept="image/*" class="form-control mb-3">
            <button class="btn btn-navy">Save profile</button>
        </form>
    </div>
    <div class="col-lg-6">
        <form class="premium-card p-4" method="post">
            <?= csrf_field() ?>
            <h5 class="fw-bold">Security settings</h5>
            <input type="hidden" name="password" value="1">
            <input name="new_password" type="password" minlength="8" class="form-control mb-3" placeholder="New password">
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">2FA placeholder enabled</label></div>
            <p class="muted"><i class="fa-solid fa-laptop me-2"></i>Linked device: Chrome on Windows</p>
            <button class="btn btn-gold">Update security</button>
        </form>
        <form class="premium-card p-4 mt-4" method="post">
            <?= csrf_field() ?>
            <h5 class="fw-bold">Transaction code</h5>
            <p class="muted">This 4-digit code verifies transactions before admin review.</p>
            <input type="hidden" name="pin" value="1">
            <input name="new_pin" type="password" inputmode="numeric" minlength="4" maxlength="4" pattern="\d{4}" class="form-control mb-3" placeholder="4-digit transaction code" required>
            <button class="btn btn-navy">Update PIN</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
