<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/brevo.php';
require_once __DIR__ . '/includes/helpers.php';
ensure_banking_schema();

$scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'register.php'));
$authRegion = $GLOBALS['authRegion'] ?? (str_contains($scriptName, '_us') ? 'us' : (str_contains($scriptName, '_ca') ? 'ca' : (str_contains($scriptName, '_uk') ? 'uk' : (str_contains($scriptName, '_ch') ? 'ch' : (str_contains($scriptName, '_de') ? 'de' : 'us')))));
$regionConfig = banking_region_config($authRegion);
$isUsPortal = $authRegion === 'us';
$isGermanPortal = false;
$usesIbanOnboarding = in_array($authRegion, ['de', 'ch'], true);
$requiresTaxId = $authRegion !== 'us';
$defaultPhoneCode = match ($authRegion) {
    'us', 'ca' => '+1',
    'uk' => '+44',
    'ch' => '+41',
    default => '+49',
};
$forcedCountry = $regionConfig['country'];
$pageLanguage = 'en';
$pageLoginUrl = $regionConfig['login'];
$pageRegisterUrl = $regionConfig['register'];
$GLOBALS['pageLanguage'] = $pageLanguage;
$GLOBALS['pageLoginUrl'] = $pageLoginUrl;
$GLOBALS['forcePageLanguage'] = true;
$GLOBALS['disableTranslate'] = true;
$signupErrors = [];
$oldSignup = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim(preg_replace('/\s+/', ' ', (string) ($_POST['full_name'] ?? '')));
    $nameParts = $fullName !== '' ? explode(' ', $fullName, 2) : ['', ''];
    $first = $nameParts[0] ?? '';
    $last = $nameParts[1] ?? '';
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $country = $forcedCountry;
    $region = $regionConfig['region'];
    $isUsOnboarding = $region === 'us';
    $usesIbanOnboarding = in_array($region, ['de', 'ch'], true);
    $defaultPhoneCode = match ($region) {
        'us', 'ca' => '+1',
        'uk' => '+44',
        'ch' => '+41',
        default => '+49',
    };
    $phoneRaw = trim((string) ($_POST['phone'] ?? ''));
    $phoneCountryCode = trim((string) ($_POST['phone_country_code'] ?? $defaultPhoneCode));
    $phone = str_starts_with($phoneRaw, '+') ? normalize_sms_phone($phoneRaw) : normalize_sms_phone($phoneCountryCode . ltrim($phoneRaw, '0'));
    $password = (string) ($_POST['password'] ?? '');
    $dob = trim((string) ($_POST['date_of_birth'] ?? ''));
    $taxId = normalize_german_tax_id((string) ($_POST['tax_id'] ?? ''));
    $ssnDigits = normalize_us_ssn((string) ($_POST['ssn'] ?? ''));
    $iban = normalize_iban((string) ($_POST['iban'] ?? ''));
    $linkJointAccount = !empty($_POST['link_joint_account']);
    $linkedInstitution = trim((string) ($_POST['linked_institution_name'] ?? ''));
    $jointOwnerName = trim((string) ($_POST['joint_owner_name'] ?? ''));
    $externalRouting = preg_replace('/\D+/', '', (string) ($_POST['routing_number'] ?? ''));
    $externalAccount = preg_replace('/\D+/', '', (string) ($_POST['external_account_number'] ?? ''));
    $address1 = trim((string) ($_POST['address_line1'] ?? ''));
    $address2 = trim((string) ($_POST['address_line2'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $state = in_array($region, ['us', 'ca'], true) ? strtoupper(substr(trim((string) ($_POST['state_code'] ?? '')), 0, 2)) : strtoupper($region);
    $postal = trim((string) ($_POST['postal_code'] ?? ''));
    $employment = trim((string) ($_POST['employment_status'] ?? ''));
    $income = trim((string) ($_POST['annual_income_range'] ?? ''));
    $transactionPin = trim((string) ($_POST['transaction_pin'] ?? ''));
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $biometricCaptures = [
        'forward' => (string) ($_POST['biometric_forward'] ?? ''),
        'left' => (string) ($_POST['biometric_left'] ?? ''),
        'right' => (string) ($_POST['biometric_right'] ?? ''),
        'blink' => (string) ($_POST['biometric_blink'] ?? ''),
    ];

    $allowedDocuments = in_array($region, ['us', 'ca', 'uk'], true) ? ['id_card', 'driver_license', 'passport'] : ['national_id', 'passport'];
    $documentType = in_array(($_POST['document_type'] ?? ''), $allowedDocuments, true) ? (string) $_POST['document_type'] : $allowedDocuments[0];
    $hasKycUpload = !empty($_FILES['identity_document']['name']);
    $hasBiometricCapture = trim($biometricCaptures['forward']) !== '';
    $identityOk = $isUsOnboarding ? is_valid_us_ssn($ssnDigits) : ($region === 'de' ? is_valid_german_tax_id($taxId) : strlen($taxId) >= 4);
    $bankingOk = !$usesIbanOnboarding
        ? (!$linkJointAccount || ($linkedInstitution !== '' && preg_match('/^\d{9}$/', $externalRouting) && preg_match('/^\d{4,17}$/', $externalAccount)))
        : ($iban === '' || ($region === 'ch' ? preg_match('/^CH[0-9A-Z]{19}$/', $iban) : is_valid_german_iban($iban)));
    $postalOk = match ($region) {
        'us' => preg_match('/^\d{5}(-\d{4})?$/', $postal),
        'ca' => preg_match('/^[A-Z]\d[A-Z][ -]?\d[A-Z]\d$/i', $postal),
        'uk' => preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', $postal),
        'ch' => preg_match('/^\d{4}$/', $postal),
        default => preg_match('/^\d{5}$/', $postal),
    };
    $stateOk = $region === 'us' ? preg_match('/^[A-Z]{2}$/', $state) : true;
    if ($fullName === '' || strlen($fullName) < 3 || strlen($fullName) > 120 || !$first || !$last) {
        $signupErrors['full_name'] = 'Enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signupErrors['email'] = 'Enter a valid email address.';
    }
    if ($phoneRaw === '' || !is_valid_sms_phone($phone)) {
        $signupErrors['phone'] = 'Enter a valid phone number with country code.';
    }
    if (strlen($password) < 8) {
        $signupErrors['password'] = 'Password must be at least 8 characters.';
    }
    if ($confirmPassword === '' || !hash_equals($password, $confirmPassword)) {
        $signupErrors['confirm_password'] = 'Passwords do not match.';
    }
    if ($dob === '') {
        $signupErrors['date_of_birth'] = 'Enter your date of birth.';
    }
    if ($country === '') {
        $signupErrors['country'] = 'Select your country.';
    }
    if (!$identityOk) {
        $signupErrors[$isUsOnboarding ? 'ssn' : 'tax_id'] = $isUsOnboarding ? 'Enter a valid 9-digit SSN.' : 'Enter a valid identity or tax number.';
    }
    if (!$bankingOk) {
        if ($usesIbanOnboarding) {
            $signupErrors['iban'] = $region === 'ch' ? 'Enter a valid Swiss IBAN or leave it blank.' : 'Enter a valid German IBAN or leave it blank.';
        } else {
            if ($linkJointAccount && $linkedInstitution === '') $signupErrors['linked_institution_name'] = 'Enter the external bank name.';
            if ($linkJointAccount && !preg_match('/^\d{9}$/', $externalRouting)) $signupErrors['routing_number'] = 'Routing number must contain 9 digits.';
            if ($linkJointAccount && !preg_match('/^\d{4,17}$/', $externalAccount)) $signupErrors['external_account_number'] = 'Account number must contain 4 to 17 digits.';
        }
    }
    if (!preg_match('/^\d{4}$/', $transactionPin)) {
        $signupErrors['transaction_pin'] = 'Create a 4-digit transaction code.';
    }
    if ($address1 === '') {
        $signupErrors['address_line1'] = 'Enter your residential address.';
    }
    if ($city === '') {
        $signupErrors['city'] = 'Enter your city.';
    }
    if (!$postalOk) {
        $signupErrors['postal_code'] = 'Enter a valid postal code.';
    }
    if (!$stateOk) {
        $signupErrors['state_code'] = 'Enter a valid 2-letter state code.';
    }
    if (!$hasKycUpload) {
        $signupErrors['identity_document'] = 'Upload one identity document.';
    }
    if (!$hasBiometricCapture) {
        $signupErrors['biometric'] = 'Complete biometric verification before submitting.';
    }

    if (!$signupErrors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            db()->beginTransaction();
            $pinHash = password_hash($transactionPin, PASSWORD_BCRYPT);
            $stmt = db()->prepare('INSERT INTO users (first_name,last_name,email,phone,date_of_birth,ssn_last4,tax_id,iban,address_line1,address_line2,city,state_code,postal_code,country,employment_status,annual_income_range,verification_status,risk_status,password_hash,transaction_pin_hash,email_verified,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"pending","verification_review",?,?,0,"active")');
            $storedTaxId = $isUsOnboarding ? null : $taxId;
            $storedIban = $usesIbanOnboarding ? ($iban !== '' ? $iban : null) : null;
            $storedSsnLast4 = $isUsOnboarding ? substr($ssnDigits, -4) : substr($taxId, -4);
            $stmt->execute([$first,$last,$email,$phone,$dob,$storedSsnLast4,$storedTaxId,$storedIban,$address1,$address2,$city,$state,$postal,$country,$employment,$income,$hash,$pinHash]);
            $userId = (int) db()->lastInsertId();
            if (!$usesIbanOnboarding) {
                $acct = ($region === 'uk' ? (string) random_int(10000000, 99999999) : '904' . random_int(100000000, 999999999));
                db()->prepare('INSERT INTO accounts (user_id, account_number, routing_number, iban, bic, account_type, available_balance, pending_balance, savings_balance) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$userId,$acct,$regionConfig['routing'],null,null,$regionConfig['account_type'],0,0,0]);
                if ($linkJointAccount) {
                    db()->prepare('INSERT INTO linked_accounts (user_id, institution_name, joint_owner_name, account_type, account_mask, routing_number, verification_method, status, last_synced_at) VALUES (?, ?, ?, "Joint Checking", ?, ?, "micro_deposit", "review", NULL)')
                        ->execute([$userId, $linkedInstitution, $jointOwnerName !== '' ? $jointOwnerName : null, substr($externalAccount, -4), $externalRouting]);
                }
            } else {
                $accountIban = $iban !== '' ? $iban : ($region === 'ch' ? 'CH9300762011623852957' : generated_german_iban());
                $acct = substr($accountIban, -10);
                db()->prepare('INSERT INTO accounts (user_id, account_number, routing_number, iban, bic, account_type, available_balance, pending_balance, savings_balance) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$userId,$acct,$regionConfig['routing'],$accountIban,$regionConfig['routing'],$regionConfig['account_type'],0,0,0]);
            }
            db()->prepare('INSERT INTO cards (user_id, card_last4, card_type, status, spending_limit, spent_month) VALUES (?,?,?,?,?,?)')->execute([$userId,substr((string) random_int(1000,9999), -4),'Debit Card','active',0,0]);
            banking_submit_kyc_document($userId, $documentType, $_FILES['identity_document'], banking_actor('customer', $userId));
            if (!banking_submit_biometric_verification($userId, $biometricCaptures, banking_actor('customer', $userId))) {
                throw new RuntimeException('Biometric verification capture could not be saved.');
            }
            banking_create_signup_bonus($userId, banking_actor('system'));
            create_customer_notification($userId, 'Account application received', 'Your account application was received and identity verification is pending.', 'info', 'security', 'normal');
            db()->commit();
            flash('success', 'Account created successfully. Awaiting verification.');
            header('Location: ' . $pageLoginUrl . '?email=' . urlencode($email));
            exit;
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('danger', 'We could not complete the application. The email may already be registered or the upload may be invalid.');
        }
    } else {
        flash('danger', 'Please review the highlighted fields before continuing.');
    }
}
$fieldClass = static fn (string $name): string => isset($signupErrors[$name]) ? ' is-invalid' : '';
$fieldValue = static fn (string $name, string $default = ''): string => (string) ($oldSignup[$name] ?? $default);
$fieldErrorHtml = static function (string $name) use (&$signupErrors): string {
    return isset($signupErrors[$name]) ? '<div class="field-error">' . e($signupErrors[$name]) . '</div>' : '';
};
$postalPattern = match ($authRegion) {
    'us' => '\d{5}(-\d{4})?',
    'ca' => '[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d',
    'uk' => '[A-Za-z]{1,2}\d[A-Za-z\d]?\s*\d[A-Za-z]{2}',
    'ch' => '\d{4}',
    default => '\d{5}',
};
$postalPlaceholder = match ($authRegion) {
    'us' => '10001',
    'ca' => 'M5V 2T6',
    'uk' => 'SW1A 1AA',
    'ch' => '8001',
    default => '40549',
};
$regionTitle = match ($authRegion) {
    'us' => 'Open U.S. Account',
    'ca' => 'Open Canadian Account',
    'uk' => 'Open UK Account',
    'ch' => 'Open Swiss Account',
    default => 'Open Germany Account',
};
$pageTitle = $regionTitle;
$onboardingEyebrow = match ($authRegion) {
    'us' => 'U.S. account opening',
    'ca' => 'Canada account opening',
    'uk' => 'UK account opening',
    'ch' => 'Swiss account opening',
    default => 'Germany account opening',
};
$onboardingTitle = match ($authRegion) {
    'us' => 'Open your U.S. Deutsche account',
    'ca' => 'Open your Canadian Deutsche account',
    'uk' => 'Open your UK Deutsche account',
    'ch' => 'Open your Swiss Deutsche account',
    default => 'Open your Germany Deutsche account',
};
$onboardingCopy = match ($authRegion) {
    'us' => 'Complete U.S. onboarding with SSN, address, phone, and identity verification.',
    'ca' => 'Complete Canadian onboarding with address, phone, identity verification, Interac, EFT, and wire tools.',
    'uk' => 'Complete UK onboarding with address, phone, identity verification, sort code, Faster Payments, and CHAPS tools.',
    'ch' => 'Complete Swiss onboarding with address, phone, identity verification, IBAN, SIC, QR-bill, and transfer tools.',
    default => 'Complete Germany onboarding with tax ID, address, phone, identity verification, IBAN, and SEPA tools.',
};
$bonusCopy = $regionConfig['currency'] . ' 250 signup bonus pending after account opening';
$identityLabel = match ($authRegion) {
    'us' => 'SSN',
    'ca' => 'Social Insurance Number',
    'uk' => 'National Insurance Number',
    'ch' => 'Tax identification number',
    default => 'Tax identification number',
};
$identityPlaceholder = match ($authRegion) {
    'us' => 'XXX-XX-XXXX',
    'ca' => '123 456 789',
    'uk' => 'QQ 12 34 56 C',
    'ch' => 'Swiss tax ID',
    default => '11 digits',
};
$ibanPlaceholder = $authRegion === 'ch' ? 'CH93 0076 2011 6238 5295 7' : 'DE89 3704 0044 0532 0130 00';
$incomeOptions = match ($regionConfig['currency']) {
    'CAD' => ['Under CAD 35,000', 'CAD 35,000 - 74,999', 'CAD 75,000 - 124,999', 'CAD 125,000 - 199,999', 'CAD 200,000+'],
    'GBP' => ['Under GBP 25,000', 'GBP 25,000 - 49,999', 'GBP 50,000 - 99,999', 'GBP 100,000 - 149,999', 'GBP 150,000+'],
    'CHF' => ['Under CHF 40,000', 'CHF 40,000 - 79,999', 'CHF 80,000 - 149,999', 'CHF 150,000 - 249,999', 'CHF 250,000+'],
    'USD' => ['Under USD 35,000', 'USD 35,000 - 74,999', 'USD 75,000 - 124,999', 'USD 125,000 - 199,999', 'USD 200,000+'],
    default => ['Under EUR 25,000', 'EUR 25,000 - 49,999', 'EUR 50,000 - 99,999', 'EUR 100,000 - 149,999', 'EUR 150,000+'],
};
?>
<?php include __DIR__ . '/includes/public_header.php'; ?>
<section class="onboarding-modern-shell">
    <form class="onboarding-flow" method="post" enctype="multipart/form-data" data-onboarding-form data-auth-region="<?= e($authRegion) ?>">
        <?= csrf_field() ?>
        <aside class="onboarding-rail">
            <?= lead_logo('light') ?>
            <div>
                <div class="eyebrow"><?= e($onboardingEyebrow) ?></div>
                <h1><?= e($onboardingTitle) ?></h1>
                <p><?= e($onboardingCopy) ?></p>
                <div class="referral-chip"><i class="fa-solid fa-gift"></i><span><?= e($bonusCopy) ?></span></div>
            </div>
            <div class="onboarding-assurance">
                <span><i class="fa-solid fa-key"></i> 4-digit code</span>
                <span><i class="fa-solid fa-fingerprint"></i> Biometric review</span>
                <span><i class="fa-solid fa-id-card"></i> KYC review</span>
            </div>
            <div class="onboarding-stepper" data-stepper>
                <span class="active">Personal</span><span>Address</span><span>Security</span><span>Financial</span><span>Identity</span><span>Review</span>
            </div>
        </aside>
        <div class="onboarding-card-modern">
            <div class="onboarding-mobile-progress"><span data-step-label>Personal information</span><strong data-step-count>1 of 6</strong></div>
            <div class="onboarding-progress"><span data-onboarding-meter></span></div>

            <section class="onboarding-slide active" data-step-panel data-step-title="Personal information">
                <div class="slide-heading"><span class="eyebrow">Step 1</span><h2>Personal information</h2><p>Use your full legal name as it appears on your ID document.</p></div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Banking region</label>
                        <input type="hidden" name="country" value="<?= e($forcedCountry) ?>" data-country-select>
                        <div class="region-lock-panel">
                            <i class="fa-solid <?= $isUsPortal ? 'fa-flag-usa' : 'fa-building-columns' ?>"></i>
                            <div><strong><?= e($forcedCountry) ?></strong><span><?= e($regionConfig['rail_primary'] . ', ' . $regionConfig['rail_bank'] . ', ' . $regionConfig['rail_wire'] . ' and local account details.') ?></span></div>
                        </div>
                    </div>
                    <div class="col-md-8"><label class="form-label">Full legal name</label><input name="full_name" class="form-control<?= e($fieldClass('full_name')) ?>" autocomplete="name" minlength="3" maxlength="120" value="<?= e($fieldValue('full_name')) ?>" required><?= $fieldErrorHtml('full_name') ?></div>
                    <div class="col-md-4"><label class="form-label">Date of birth</label><input name="date_of_birth" type="date" class="form-control<?= e($fieldClass('date_of_birth')) ?>" value="<?= e($fieldValue('date_of_birth')) ?>" required><?= $fieldErrorHtml('date_of_birth') ?></div>
                    <div class="col-md-6"><label class="form-label">Email address</label><input name="email" type="email" class="form-control<?= e($fieldClass('email')) ?>" autocomplete="email" value="<?= e($fieldValue('email')) ?>" required><?= $fieldErrorHtml('email') ?></div>
                    <div class="col-md-6">
                        <label class="form-label">Phone number</label>
                        <div class="input-group">
                            <select name="phone_country_code" class="form-select" style="max-width: 145px">
                                <option value="+49" <?= $defaultPhoneCode === '+49' ? 'selected' : '' ?>>DE +49</option><option value="+1" <?= $defaultPhoneCode === '+1' ? 'selected' : '' ?>>US/CA +1</option><option value="+44" <?= $defaultPhoneCode === '+44' ? 'selected' : '' ?>>UK +44</option><option value="+41" <?= $defaultPhoneCode === '+41' ? 'selected' : '' ?>>CH +41</option><option value="+43">AT +43</option><option value="+33">FR +33</option><option value="+31">NL +31</option><option value="+32">BE +32</option><option value="+34">ES +34</option><option value="+39">IT +39</option><option value="+351">PT +351</option><option value="+234">NG +234</option>
                            </select>
                            <input name="phone" class="form-control<?= e($fieldClass('phone')) ?>" autocomplete="tel" placeholder="<?= $defaultPhoneCode === '+1' ? '2125550147' : ($defaultPhoneCode === '+44' ? '7700900123' : ($defaultPhoneCode === '+41' ? '791234567' : '15123456789')) ?>" value="<?= e($fieldValue('phone')) ?>" required>
                        </div>
                        <?= $fieldErrorHtml('phone') ?>
                    </div>
                    <?php if ($requiresTaxId): ?><div class="col-md-6" data-region-block="tax"><label class="form-label"><?= e($identityLabel) ?></label><input name="tax_id" class="form-control<?= e($fieldClass('tax_id')) ?>" maxlength="32" placeholder="<?= e($identityPlaceholder) ?>" value="<?= e($fieldValue('tax_id')) ?>" required><?= $fieldErrorHtml('tax_id') ?></div><?php endif; ?>
                    <?php if ($usesIbanOnboarding): ?><div class="col-md-6" data-region-block="iban"><label class="form-label">IBAN optional</label><input name="iban" class="form-control text-uppercase<?= e($fieldClass('iban')) ?>" placeholder="<?= e($ibanPlaceholder) ?>" value="<?= e($fieldValue('iban')) ?>" data-format-iban><?= $fieldErrorHtml('iban') ?></div><?php endif; ?>
                    <div class="col-md-6" data-region-block="us" hidden><label class="form-label">SSN</label><input name="ssn" class="form-control<?= e($fieldClass('ssn')) ?>" inputmode="numeric" maxlength="11" placeholder="XXX-XX-XXXX" value="<?= e($fieldValue('ssn')) ?>" data-mask-ssn><?= $fieldErrorHtml('ssn') ?></div>
                </div>
            </section>

            <section class="onboarding-slide" data-step-panel data-step-title="Residential address">
                <div class="slide-heading"><span class="eyebrow">Step 2</span><h2>Residential address</h2><p>Enter the address tied to your residential registration.</p></div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" data-address-line-label>Street name and house number</label>
                        <input name="address_line1" class="form-control<?= e($fieldClass('address_line1')) ?>" autocomplete="address-line1" placeholder="Hansaallee 3" list="addressSuggestions" value="<?= e($fieldValue('address_line1')) ?>" data-address-help required>
                        <?= $fieldErrorHtml('address_line1') ?>
                        <datalist id="addressSuggestions" data-address-suggestions></datalist>
                        <div class="address-assist-panel" data-address-assist>
                            <i class="fa-solid fa-location-dot"></i>
                            <span>Start typing an address to see matching examples.</span>
                        </div>
                    </div>
                    <div class="col-12" data-region-block="us" hidden><label class="form-label">Apartment, suite, unit</label><input name="address_line2" class="form-control" autocomplete="address-line2" value="<?= e($fieldValue('address_line2')) ?>"></div>
                    <div class="col-md-4"><label class="form-label" data-postal-label><?= $isUsPortal ? 'ZIP code' : 'Postal code' ?></label><input name="postal_code" class="form-control<?= e($fieldClass('postal_code')) ?>" maxlength="10" pattern="<?= e($postalPattern) ?>" placeholder="<?= e($postalPlaceholder) ?>" autocomplete="postal-code" value="<?= e($fieldValue('postal_code')) ?>" required><?= $fieldErrorHtml('postal_code') ?></div>
                    <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control<?= e($fieldClass('city')) ?>" autocomplete="address-level2" placeholder="Duesseldorf" value="<?= e($fieldValue('city')) ?>" required><?= $fieldErrorHtml('city') ?></div>
                    <div class="col-md-4" data-region-block="us" hidden><label class="form-label">State</label><input name="state_code" maxlength="2" class="form-control text-uppercase<?= e($fieldClass('state_code')) ?>" autocomplete="address-level1" placeholder="NY" value="<?= e($fieldValue('state_code')) ?>"><?= $fieldErrorHtml('state_code') ?></div>
                    <div class="col-md-4"><label class="form-label">Country</label><input class="form-control" value="<?= e($forcedCountry) ?>" disabled></div>
                </div>
            </section>

            <section class="onboarding-slide" data-step-panel data-step-title="Security setup">
                <div class="slide-heading"><span class="eyebrow">Step 3</span><h2>Security setup</h2><p>Create credentials that protect transfers, statements, and profile access.</p></div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <div class="secure-input"><input name="password" type="password" minlength="8" class="form-control<?= e($fieldClass('password')) ?>" data-password-field required><button type="button" data-visibility-toggle aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div>
                        <?= $fieldErrorHtml('password') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm password</label>
                        <div class="secure-input"><input name="confirm_password" type="password" minlength="8" class="form-control<?= e($fieldClass('confirm_password')) ?>" data-confirm-password required><button type="button" data-visibility-toggle aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div>
                        <?= $fieldErrorHtml('confirm_password') ?>
                    </div>
                    <div class="col-12">
                        <div class="password-meter"><span data-password-meter></span></div>
                        <div class="password-rules">
                            <span data-password-rule="length">8+ characters</span>
                            <span data-password-rule="upper">Uppercase suggested</span>
                            <span data-password-rule="lower">Lowercase suggested</span>
                            <span data-password-rule="number">Number suggested</span>
                            <span data-password-rule="special">Special character suggested</span>
                            <span data-password-match>Passwords match</span>
                        </div>
                        <div class="password-advice" data-password-warning></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">4-digit transaction code</label>
                        <div class="secure-input"><input name="transaction_pin" type="password" inputmode="numeric" minlength="4" maxlength="4" pattern="\d{4}" class="form-control<?= e($fieldClass('transaction_pin')) ?>" placeholder="Create a 4-digit code" data-pin-field required><button type="button" data-visibility-toggle aria-label="Show code"><i class="fa-solid fa-eye"></i></button></div>
                        <?= $fieldErrorHtml('transaction_pin') ?>
                    </div>
                </div>
            </section>

            <section class="onboarding-slide" data-step-panel data-step-title="Financial profile">
                <div class="slide-heading"><span class="eyebrow">Step 4</span><h2>Financial profile</h2><p>These details help with account setup, transfer limits, and KYC review.</p></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Employment status</label><select name="employment_status" class="form-select"><option>Employed</option><option>Self-employed</option><option>Retired</option><option>Student</option><option>Not currently employed</option></select></div>
                    <div class="col-md-6"><label class="form-label">Annual income range</label><select name="annual_income_range" class="form-select"><?php foreach ($incomeOptions as $incomeOption): ?><option><?= e($incomeOption) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12" data-region-block="us" hidden>
                        <div class="form-check premium-check mt-2">
                            <input class="form-check-input" type="checkbox" id="linkJointAccount" name="link_joint_account" value="1" data-joint-account-toggle>
                            <label class="form-check-label" for="linkJointAccount">
                                Link a joint or external account for admin review
                                <span>Add this only if another bank account should be connected to this profile.</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-12" data-region-block="us" data-joint-account-fields hidden>
                        <div class="row g-3 linked-account-inline">
                            <div class="col-md-6"><label class="form-label">External bank name</label><input name="linked_institution_name" class="form-control<?= e($fieldClass('linked_institution_name')) ?>" placeholder="Bank name" value="<?= e($fieldValue('linked_institution_name')) ?>"><?= $fieldErrorHtml('linked_institution_name') ?></div>
                            <div class="col-md-6"><label class="form-label">Joint owner name</label><input name="joint_owner_name" class="form-control" placeholder="Optional joint owner" value="<?= e($fieldValue('joint_owner_name')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Routing number</label><input name="routing_number" class="form-control<?= e($fieldClass('routing_number')) ?>" inputmode="numeric" maxlength="9" placeholder="071923846" value="<?= e($fieldValue('routing_number')) ?>"><?= $fieldErrorHtml('routing_number') ?></div>
                            <div class="col-md-6"><label class="form-label">Account number</label><input name="external_account_number" class="form-control<?= e($fieldClass('external_account_number')) ?>" inputmode="numeric" maxlength="17" placeholder="External account number" value="<?= e($fieldValue('external_account_number')) ?>"><?= $fieldErrorHtml('external_account_number') ?></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="onboarding-slide" data-step-panel data-step-title="Identity verification">
                <div class="slide-heading"><span class="eyebrow">Step 5</span><h2>Identity verification</h2><p>Choose your ID document type and upload one file. The secure face check begins after Continue.</p></div>
                <div class="kyc-upload-panel mb-3"><strong>Accepted documents</strong><span class="muted small">National ID or passport as image or PDF.</span></div>
                <div class="row g-3">
                    <div class="col-md-5"><label class="form-label">Document type</label><select name="document_type" class="form-select" data-document-type-select><option value="national_id" data-region-option="de,ch">National ID</option><option value="passport" data-region-option="both">Passport</option><option value="id_card" data-region-option="us,ca,uk">Government ID</option><option value="driver_license" data-region-option="us,ca,uk">Driver license</option></select></div>
                    <div class="col-md-7"><label class="form-label">Upload document</label><input name="identity_document" type="file" accept="image/*,.pdf" class="form-control<?= e($fieldClass('identity_document')) ?>" data-document-upload required><?= $fieldErrorHtml('identity_document') ?></div>
                    <div class="col-12"><div class="upload-progress"><span></span></div></div>
                </div>
            </section>

            <section class="onboarding-slide" data-step-panel data-step-title="Review and submit">
                <div class="slide-heading"><span class="eyebrow">Step 6</span><h2>Review and submit</h2><p>Confirm your details before submitting your membership application.</p></div>
                <div class="review-summary-grid">
                    <div><span>Name</span><strong data-review="name">-</strong></div>
                    <div><span>Email</span><strong data-review="email">-</strong></div>
                    <div><span>Phone</span><strong data-review="phone">-</strong></div>
                    <div><span data-review-identity-label>Tax ID</span><strong data-review="tax_id">-</strong></div>
                    <div><span>Residential address</span><strong data-review="address">-</strong></div>
                    <div><span data-review-bank-label>IBAN</span><strong data-review="iban">Automatic</strong></div>
                    <div><span>Document</span><strong data-review="documents">-</strong></div>
                </div>
                <div class="verification-ready <?= e($fieldClass('biometric')) ?>"><i class="fa-solid fa-shield-check"></i><div><strong>Biometric verification complete</strong><span>Your face check will be submitted with this application for pending review.</span><?= $fieldErrorHtml('biometric') ?></div></div>
                <input type="hidden" name="biometric_forward" data-biometric-input="forward">
                <input type="hidden" name="biometric_left" data-biometric-input="left">
                <input type="hidden" name="biometric_right" data-biometric-input="right">
                <input type="hidden" name="biometric_blink" data-biometric-input="blink">
                <button type="submit" class="d-none" data-kyc-submit>Submit Application</button>
            </section>

            <div class="onboarding-actions">
                <button type="button" class="btn btn-light border" data-step-back>Back</button>
                <button type="button" class="btn btn-gold" data-step-next>Continue</button>
            </div>
            <div class="onboarding-step-message" data-step-message aria-live="polite"></div>
        </div>
    </form>
</section>
<div class="biometric-overlay" data-biometric-flow aria-hidden="true">
    <div class="biometric-atmosphere"></div>
    <div class="biometric-stage">
        <div class="biometric-topbar">
            <?= lead_logo('light') ?>
            <span class="biometric-secure"><i class="fa-solid fa-lock"></i> Bank-grade verification</span>
        </div>
        <div class="biometric-experience">
            <div class="biometric-copy">
                <span class="eyebrow">Identity verification</span>
                <h2 data-biometric-title>Secure face check</h2>
                <p data-biometric-help>We will open your camera and guide you through a quick liveness check.</p>
                <div class="biometric-progress-bar"><span data-biometric-meter></span></div>
            </div>
            <div class="biometric-camera">
                <video data-biometric-video autoplay playsinline muted></video>
                <canvas data-biometric-canvas hidden></canvas>
                <div class="face-frame"><span></span><span></span><span></span><span></span></div>
                <div class="scan-line"></div>
                <div class="biometric-orbit"></div>
            </div>
            <div class="biometric-prompt-wrap">
                <div class="biometric-prompt" data-biometric-prompt>Preparing secure camera session</div>
                <div class="biometric-status" data-biometric-status>Ready</div>
            </div>
            <div class="biometric-success" data-biometric-success hidden>
                <div class="verification-badge"><i class="fa-solid fa-shield-check"></i></div>
                <h2>Face verification complete.</h2>
                <p>Review your application details before submitting to Deutsche.</p>
            </div>
            <div class="biometric-actions">
                <button type="button" class="btn btn-light border" data-biometric-cancel>Return to application</button>
                <button type="button" class="btn btn-gold" data-biometric-retry hidden>Try again</button>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
