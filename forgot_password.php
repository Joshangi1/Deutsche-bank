<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/brevo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    flash('success', 'Password reset by email is currently disabled. Please contact support to recover account access.');
    header('Location: forgot_password.php');
    exit;
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/includes/public_header.php';
?>
<section class="auth-shell">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>
    <div class="mb-4"><?= lead_logo('dark') ?></div>
    <h1 class="h3 fw-bold">Reset access</h1>
    <p class="muted">Email password reset is currently disabled. Submit your registered email and support will help recover account access.</p>
    <input name="email" type="email" class="form-control mb-3" placeholder="you@example.com" required>
    <button class="btn btn-gold w-100">Request support help</button>
    <a class="d-block small mt-3" href="login.php">Back to sign in</a>
  </form>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
