<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

const APP_NAME = 'Deutsche';
const ROUTING_NUMBER = 'DEUTDEFFXXX';
const DEFAULT_BIC = 'DEUTDEFFXXX';
const US_ROUTING_NUMBER = '071923846';
const SESSION_IDLE_TIMEOUT = 600;

function ensure_banking_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = db();

    $tables = [
        'payment_recipients' => 'CREATE TABLE IF NOT EXISTS payment_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(140) NOT NULL,
            email VARCHAR(160) DEFAULT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            iban VARCHAR(34) DEFAULT NULL,
            bic VARCHAR(16) DEFAULT NULL,
            nickname VARCHAR(80) DEFAULT NULL,
            status ENUM("active","pending","blocked") DEFAULT "active",
            last_used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_payment_recipients_user (user_id),
            CONSTRAINT payment_recipients_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'billers' => 'CREATE TABLE IF NOT EXISTS billers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(140) NOT NULL,
            category VARCHAR(80) NOT NULL,
            account_mask VARCHAR(32) NOT NULL,
            due_day TINYINT DEFAULT NULL,
            autopay TINYINT(1) DEFAULT 0,
            status ENUM("active","scheduled","paused") DEFAULT "active",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_billers_user (user_id),
            CONSTRAINT billers_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'banking_payments' => 'CREATE TABLE IF NOT EXISTS banking_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            account_id INT NOT NULL,
            payment_type ENUM("zelle","bill_pay","ach","transfer","sepa","sepa_instant","standing_order","card_link","credit_card_fund_account","credit_card_fund_card","referral_bonus") NOT NULL,
            payee_name VARCHAR(160) NOT NULL,
            descriptor VARCHAR(255) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            direction ENUM("inbound","outbound") DEFAULT "outbound",
            status ENUM("scheduled","processing","pending_review","completed","failed","cancelled","rejected") DEFAULT "processing",
            scheduled_for DATE DEFAULT NULL,
            recurring TINYINT(1) DEFAULT 0,
            frequency VARCHAR(40) DEFAULT NULL,
            confirmation_code VARCHAR(24) DEFAULT NULL,
            review_note TEXT DEFAULT NULL,
            proof_file VARCHAR(255) DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            transaction_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_banking_payments_user (user_id, payment_type, status),
            CONSTRAINT banking_payments_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT banking_payments_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'linked_accounts' => 'CREATE TABLE IF NOT EXISTS linked_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            institution_name VARCHAR(140) NOT NULL,
            joint_owner_name VARCHAR(140) DEFAULT NULL,
            account_type VARCHAR(80) NOT NULL,
            account_mask VARCHAR(16) NOT NULL,
            routing_number VARCHAR(16) DEFAULT NULL,
            iban VARCHAR(34) DEFAULT NULL,
            bic VARCHAR(16) DEFAULT NULL,
            verification_method ENUM("instant","micro_deposit") DEFAULT "micro_deposit",
            status ENUM("connected","pending_verification","review","disabled") DEFAULT "pending_verification",
            last_synced_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_linked_accounts_user (user_id),
            CONSTRAINT linked_accounts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'documents' => 'CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            document_type VARCHAR(80) NOT NULL,
            title VARCHAR(180) NOT NULL,
            period_label VARCHAR(80) DEFAULT NULL,
            file_name VARCHAR(180) NOT NULL,
            status ENUM("available","new","archived") DEFAULT "available",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_documents_user (user_id, document_type),
            CONSTRAINT documents_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'security_events' => 'CREATE TABLE IF NOT EXISTS security_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_type VARCHAR(80) NOT NULL,
            title VARCHAR(160) NOT NULL,
            details VARCHAR(255) NOT NULL,
            device VARCHAR(140) DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            severity ENUM("info","success","warning","danger") DEFAULT "info",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_security_events_user (user_id),
            CONSTRAINT security_events_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'otp_verifications' => 'CREATE TABLE IF NOT EXISTS otp_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            phone VARCHAR(40) NOT NULL,
            purpose ENUM("signup","login","transfer") NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            verified_at DATETIME DEFAULT NULL,
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 5,
            resend_available_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            send_status ENUM("sent","failed") DEFAULT "sent",
            last_error VARCHAR(255) DEFAULT NULL,
            INDEX idx_otp_user_purpose (user_id, purpose, created_at),
            INDEX idx_otp_phone_purpose (phone, purpose, created_at),
            INDEX idx_otp_expiry (expires_at),
            CONSTRAINT otp_verifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'system_events' => 'CREATE TABLE IF NOT EXISTS system_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            affected_user_id INT DEFAULT NULL,
            severity ENUM("info","warning","danger") DEFAULT "info",
            details TEXT NOT NULL,
            metadata LONGTEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_system_events_created (created_at),
            INDEX idx_system_events_user (affected_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'card_controls' => 'CREATE TABLE IF NOT EXISTS card_controls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_id INT NOT NULL,
            online_enabled TINYINT(1) DEFAULT 1,
            international_enabled TINYINT(1) DEFAULT 0,
            atm_enabled TINYINT(1) DEFAULT 1,
            merchant_restrictions VARCHAR(255) DEFAULT NULL,
            travel_notice VARCHAR(255) DEFAULT NULL,
            virtual_card_last4 CHAR(4) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_card_control (card_id),
            CONSTRAINT card_controls_card_fk FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'banking_events' => 'CREATE TABLE IF NOT EXISTS banking_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(100) NOT NULL,
            aggregate_type VARCHAR(80) DEFAULT NULL,
            aggregate_id INT DEFAULT NULL,
            user_id INT DEFAULT NULL,
            actor_type ENUM("customer","admin","system","ai") DEFAULT "system",
            actor_id VARCHAR(80) DEFAULT NULL,
            payload LONGTEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_banking_events_name (event_name),
            INDEX idx_banking_events_user (user_id),
            INDEX idx_banking_events_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'service_actions' => 'CREATE TABLE IF NOT EXISTS service_actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action_name VARCHAR(100) NOT NULL,
            actor_type ENUM("customer","admin","system","ai") DEFAULT "system",
            actor_id VARCHAR(80) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            status ENUM("accepted","completed","rejected","failed") DEFAULT "accepted",
            request_payload LONGTEXT DEFAULT NULL,
            result_payload LONGTEXT DEFAULT NULL,
            rollback_payload LONGTEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_service_actions_actor (actor_type, actor_id),
            INDEX idx_service_actions_user (user_id),
            INDEX idx_service_actions_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'account_snapshots' => 'CREATE TABLE IF NOT EXISTS account_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            user_id INT NOT NULL,
            available_balance DECIMAL(14,2) NOT NULL,
            pending_balance DECIMAL(14,2) NOT NULL,
            savings_balance DECIMAL(14,2) NOT NULL,
            source_event_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account_snapshots_account (account_id, created_at),
            CONSTRAINT account_snapshots_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
            CONSTRAINT account_snapshots_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'kyc_documents' => 'CREATE TABLE IF NOT EXISTS kyc_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            document_type ENUM("id_card","driver_license","passport","selfie","national_id") NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            mime_type VARCHAR(120) DEFAULT NULL,
            status ENUM("pending","approved","rejected","reupload_requested") DEFAULT "pending",
            review_note TEXT DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kyc_documents_user (user_id, status),
            CONSTRAINT kyc_documents_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'biometric_verifications' => 'CREATE TABLE IF NOT EXISTS biometric_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token CHAR(32) NOT NULL,
            capture_forward VARCHAR(255) DEFAULT NULL,
            capture_left VARCHAR(255) DEFAULT NULL,
            capture_right VARCHAR(255) DEFAULT NULL,
            capture_blink VARCHAR(255) DEFAULT NULL,
            liveness_score TINYINT DEFAULT 0,
            status ENUM("pending","verified","failed") DEFAULT "pending",
            review_note TEXT DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            device_info VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_biometric_verifications_user (user_id, status),
            CONSTRAINT biometric_verifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'linked_cards' => 'CREATE TABLE IF NOT EXISTS linked_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token CHAR(40) NOT NULL,
            cardholder_name VARCHAR(160) DEFAULT NULL,
            card_brand VARCHAR(40) DEFAULT NULL,
            card_number_encrypted TEXT DEFAULT NULL,
            card_last4 CHAR(4) DEFAULT NULL,
            expiry_month CHAR(2) DEFAULT NULL,
            expiry_year CHAR(4) DEFAULT NULL,
            cvv_provided TINYINT(1) DEFAULT 0,
            billing_address VARCHAR(255) DEFAULT NULL,
            issuing_bank VARCHAR(160) DEFAULT NULL,
            card_country VARCHAR(80) DEFAULT NULL,
            front_image VARCHAR(255) DEFAULT NULL,
            back_image VARCHAR(255) DEFAULT NULL,
            status ENUM("link_created","pending_review","approved","rejected","disabled","expired") DEFAULT "link_created",
            link_status ENUM("active","used","expired") DEFAULT "active",
            expires_at DATETIME DEFAULT NULL,
            review_note TEXT DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            submitted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_linked_cards_token (token),
            INDEX idx_linked_cards_user (user_id, status),
            CONSTRAINT linked_cards_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'referral_signup_bonuses' => 'CREATE TABLE IF NOT EXISTS referral_signup_bonuses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            transaction_id INT NOT NULL,
            referral_code VARCHAR(80) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            currency CHAR(3) NOT NULL,
            reference_code VARCHAR(32) NOT NULL,
            status ENUM("pending","completed","rejected") DEFAULT "pending",
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            review_note TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_referral_bonus_user (user_id),
            UNIQUE KEY unique_referral_bonus_ref (reference_code),
            INDEX idx_referral_bonus_status (status, created_at),
            CONSTRAINT referral_bonus_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT referral_bonus_tx_fk FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'admin_onboarding_links' => 'CREATE TABLE IF NOT EXISTS admin_onboarding_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token CHAR(64) NOT NULL,
            admin_id INT NOT NULL,
            client_name VARCHAR(140) DEFAULT NULL,
            client_email VARCHAR(160) DEFAULT NULL,
            country VARCHAR(80) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM("active","used","expired","disabled") DEFAULT "active",
            UNIQUE KEY idx_admin_onboarding_token (token),
            INDEX idx_admin_onboarding_admin (admin_id, created_at),
            INDEX idx_admin_onboarding_status (status, expires_at, used_at),
            CONSTRAINT admin_onboarding_admin_fk FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'loan_applications' => 'CREATE TABLE IF NOT EXISTS loan_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            loan_type VARCHAR(80) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            currency CHAR(3) NOT NULL,
            term_months INT NOT NULL,
            purpose VARCHAR(180) DEFAULT NULL,
            status ENUM("pending_review","approved","rejected","cancelled") DEFAULT "pending_review",
            reference_code VARCHAR(32) NOT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            review_note TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_loan_reference (reference_code),
            INDEX idx_loan_user_status (user_id, status),
            CONSTRAINT loan_applications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'password_resets' => 'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pr_user (user_id),
            INDEX idx_pr_token (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'user_banking_details' => 'CREATE TABLE IF NOT EXISTS user_banking_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            account_id INT DEFAULT NULL,
            country VARCHAR(40) NOT NULL,
            detail_label VARCHAR(120) NOT NULL,
            detail_value VARCHAR(255) NOT NULL,
            display_order INT DEFAULT 0,
            is_copyable TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_banking_details_user (user_id, country, display_order),
            INDEX idx_user_banking_details_account (account_id),
            CONSTRAINT user_banking_details_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT user_banking_details_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    $columns = [
        ['notifications', 'category', 'ALTER TABLE notifications ADD COLUMN category VARCHAR(60) DEFAULT "account" AFTER type'],
        ['notifications', 'priority', 'ALTER TABLE notifications ADD COLUMN priority ENUM("low","normal","high") DEFAULT "normal" AFTER category'],
        ['admin_logs', 'affected_user_id', 'ALTER TABLE admin_logs ADD COLUMN affected_user_id INT NULL AFTER action'],
        ['admin_logs', 'before_values', 'ALTER TABLE admin_logs ADD COLUMN before_values LONGTEXT NULL AFTER details'],
        ['admin_logs', 'after_values', 'ALTER TABLE admin_logs ADD COLUMN after_values LONGTEXT NULL AFTER before_values'],
        ['admins', 'display_name', 'ALTER TABLE admins ADD COLUMN display_name VARCHAR(140) NULL AFTER name'],
        ['admins', 'agent_id', 'ALTER TABLE admins ADD COLUMN agent_id VARCHAR(40) NULL AFTER display_name'],
        ['admins', 'profile_photo', 'ALTER TABLE admins ADD COLUMN profile_photo VARCHAR(255) NULL AFTER agent_id'],
        ['admins', 'failed_attempts', 'ALTER TABLE admins ADD COLUMN failed_attempts INT DEFAULT 0 AFTER role'],
        ['admins', 'locked_until', 'ALTER TABLE admins ADD COLUMN locked_until DATETIME NULL AFTER failed_attempts'],
        ['admins', 'last_login', 'ALTER TABLE admins ADD COLUMN last_login DATETIME NULL AFTER locked_until'],
        ['admins', 'status', 'ALTER TABLE admins ADD COLUMN status ENUM("active","disabled") DEFAULT "active" AFTER role'],
        ['users', 'date_of_birth', 'ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER phone'],
        ['users', 'ssn_last4', 'ALTER TABLE users ADD COLUMN ssn_last4 CHAR(4) NULL AFTER date_of_birth'],
        ['users', 'address_line1', 'ALTER TABLE users ADD COLUMN address_line1 VARCHAR(160) NULL AFTER ssn_last4'],
        ['users', 'address_line2', 'ALTER TABLE users ADD COLUMN address_line2 VARCHAR(120) NULL AFTER address_line1'],
        ['users', 'city', 'ALTER TABLE users ADD COLUMN city VARCHAR(90) NULL AFTER address_line2'],
        ['users', 'state_code', 'ALTER TABLE users ADD COLUMN state_code CHAR(2) NULL AFTER city'],
        ['users', 'postal_code', 'ALTER TABLE users ADD COLUMN postal_code VARCHAR(16) NULL AFTER state_code'],
        ['users', 'employment_status', 'ALTER TABLE users ADD COLUMN employment_status VARCHAR(80) NULL AFTER postal_code'],
        ['users', 'annual_income_range', 'ALTER TABLE users ADD COLUMN annual_income_range VARCHAR(80) NULL AFTER employment_status'],
        ['users', 'country', 'ALTER TABLE users ADD COLUMN country VARCHAR(80) NULL AFTER postal_code'],
        ['users', 'tax_id', 'ALTER TABLE users ADD COLUMN tax_id VARCHAR(32) NULL AFTER ssn_last4'],
        ['users', 'iban', 'ALTER TABLE users ADD COLUMN iban VARCHAR(34) NULL AFTER tax_id'],
        ['users', 'verification_status', 'ALTER TABLE users ADD COLUMN verification_status ENUM("not_started","pending","approved","rejected","reupload_requested") DEFAULT "not_started" AFTER annual_income_range'],
        ['users', 'risk_status', 'ALTER TABLE users ADD COLUMN risk_status ENUM("clear","fraud_review","verification_review","transfer_restricted") DEFAULT "clear" AFTER verification_status'],
        ['users', 'restriction_reason', 'ALTER TABLE users ADD COLUMN restriction_reason VARCHAR(255) NULL AFTER risk_status'],
        ['users', 'onboarded_by_admin_id', 'ALTER TABLE users ADD COLUMN onboarded_by_admin_id INT NULL AFTER restriction_reason'],
        ['users', 'onboarding_link_id', 'ALTER TABLE users ADD COLUMN onboarding_link_id INT NULL AFTER onboarded_by_admin_id'],
        ['linked_accounts', 'joint_owner_name', 'ALTER TABLE linked_accounts ADD COLUMN joint_owner_name VARCHAR(140) NULL AFTER institution_name'],
        ['linked_accounts', 'iban', 'ALTER TABLE linked_accounts ADD COLUMN iban VARCHAR(34) NULL AFTER routing_number'],
        ['linked_accounts', 'bic', 'ALTER TABLE linked_accounts ADD COLUMN bic VARCHAR(16) NULL AFTER iban'],
        ['payment_recipients', 'iban', 'ALTER TABLE payment_recipients ADD COLUMN iban VARCHAR(34) NULL AFTER phone'],
        ['payment_recipients', 'bic', 'ALTER TABLE payment_recipients ADD COLUMN bic VARCHAR(16) NULL AFTER iban'],
        ['accounts', 'iban', 'ALTER TABLE accounts ADD COLUMN iban VARCHAR(34) NULL AFTER routing_number'],
        ['accounts', 'bic', 'ALTER TABLE accounts ADD COLUMN bic VARCHAR(16) NULL AFTER iban'],
        ['linked_cards', 'card_number_encrypted', 'ALTER TABLE linked_cards ADD COLUMN card_number_encrypted TEXT NULL AFTER card_brand'],
        ['linked_cards', 'cvv_provided', 'ALTER TABLE linked_cards ADD COLUMN cvv_provided TINYINT(1) DEFAULT 0 AFTER expiry_year'],
        ['linked_cards', 'billing_address', 'ALTER TABLE linked_cards ADD COLUMN billing_address VARCHAR(255) NULL AFTER cvv_provided'],
        ['linked_cards', 'issuing_bank', 'ALTER TABLE linked_cards ADD COLUMN issuing_bank VARCHAR(160) NULL AFTER expiry_year'],
        ['linked_cards', 'card_country', 'ALTER TABLE linked_cards ADD COLUMN card_country VARCHAR(80) NULL AFTER issuing_bank'],
        ['linked_cards', 'front_image', 'ALTER TABLE linked_cards ADD COLUMN front_image VARCHAR(255) NULL AFTER card_country'],
        ['linked_cards', 'back_image', 'ALTER TABLE linked_cards ADD COLUMN back_image VARCHAR(255) NULL AFTER front_image'],
        ['linked_cards', 'link_status', 'ALTER TABLE linked_cards ADD COLUMN link_status ENUM("active","used","expired") DEFAULT "active" AFTER status'],
        ['linked_cards', 'expires_at', 'ALTER TABLE linked_cards ADD COLUMN expires_at DATETIME NULL AFTER link_status'],
        ['banking_payments', 'review_note', 'ALTER TABLE banking_payments ADD COLUMN review_note TEXT NULL AFTER confirmation_code'],
        ['banking_payments', 'proof_file', 'ALTER TABLE banking_payments ADD COLUMN proof_file VARCHAR(255) NULL AFTER review_note'],
        ['banking_payments', 'reviewed_by', 'ALTER TABLE banking_payments ADD COLUMN reviewed_by INT NULL AFTER proof_file'],
        ['banking_payments', 'reviewed_at', 'ALTER TABLE banking_payments ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by'],
        ['banking_payments', 'transaction_id', 'ALTER TABLE banking_payments ADD COLUMN transaction_id INT NULL AFTER reviewed_at'],
        ['otp_verifications', 'send_status', 'ALTER TABLE otp_verifications ADD COLUMN send_status ENUM("sent","failed") DEFAULT "sent" AFTER user_agent'],
        ['otp_verifications', 'last_error', 'ALTER TABLE otp_verifications ADD COLUMN last_error VARCHAR(255) NULL AFTER send_status'],
    ];
    foreach ($columns as [$table, $column, $sql]) {
        $check = $pdo->prepare('SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $check->execute([$table, $column]);
        if ((int) $check->fetch()['c'] === 0) {
            $pdo->exec($sql);
        }
    }

    try {
        $pdo->exec('ALTER TABLE banking_payments MODIFY payment_type ENUM("zelle","bill_pay","ach","transfer","sepa","sepa_instant","standing_order","card_link","credit_card_fund_account","credit_card_fund_card","referral_bonus") NOT NULL');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec('ALTER TABLE banking_payments MODIFY status ENUM("scheduled","processing","pending_review","completed","failed","cancelled","rejected") DEFAULT "processing"');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec('ALTER TABLE transactions MODIFY status ENUM("pending","completed","failed","rejected") DEFAULT "pending"');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec('ALTER TABLE linked_cards MODIFY status ENUM("link_created","pending_review","approved","rejected","disabled","expired") DEFAULT "link_created"');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec('ALTER TABLE kyc_documents MODIFY document_type ENUM("id_card","driver_license","passport","selfie","national_id") NOT NULL');
    } catch (Throwable $e) {
    }
}

function normalize_iban(string $iban): string
{
    return strtoupper(preg_replace('/\s+/', '', trim($iban)));
}

function format_iban_display(?string $iban): string
{
    $normalized = normalize_iban((string) $iban);
    return trim(chunk_split($normalized, 4, ' '));
}

function iban_mod97(string $iban): int
{
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $char) {
        $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
    }
    $remainder = 0;
    foreach (str_split($numeric) as $digit) {
        $remainder = ($remainder * 10 + (int) $digit) % 97;
    }
    return $remainder;
}

function is_valid_german_iban(string $iban): bool
{
    $iban = normalize_iban($iban);
    return preg_match('/^DE\d{20}$/', $iban) === 1 && iban_mod97($iban) === 1;
}

function normalize_bic(string $bic): string
{
    return strtoupper(preg_replace('/\s+/', '', trim($bic)));
}

function is_valid_bic(string $bic): bool
{
    return preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', normalize_bic($bic)) === 1;
}

function normalize_german_tax_id(string $taxId): string
{
    return preg_replace('/\D+/', '', $taxId);
}

function is_valid_german_tax_id(string $taxId): bool
{
    return preg_match('/^\d{11}$/', normalize_german_tax_id($taxId)) === 1;
}

function normalize_us_ssn(string $ssn): string
{
    return preg_replace('/\D+/', '', $ssn);
}

function is_valid_us_ssn(string $ssn): bool
{
    return preg_match('/^\d{9}$/', normalize_us_ssn($ssn)) === 1;
}

function generated_german_iban(): string
{
    $bankCode = '37040044';
    $account = str_pad((string) random_int(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    $bban = $bankCode . $account;
    $checkInput = $bban . 'DE00';
    $numeric = '';
    foreach (str_split($checkInput) as $char) {
        $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
    }
    $remainder = 0;
    foreach (str_split($numeric) as $digit) {
        $remainder = ($remainder * 10 + (int) $digit) % 97;
    }
    $check = str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT);
    return 'DE' . $check . $bban;
}

function banking_update_linked_account_name(int $linkedAccountId, ?string $jointOwnerName, array $actor): void
{
    $stmt = db()->prepare('SELECT * FROM linked_accounts WHERE id=?');
    $stmt->execute([$linkedAccountId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('Linked account not found.');
    }
    $name = trim((string) $jointOwnerName);
    db()->prepare('UPDATE linked_accounts SET joint_owner_name=? WHERE id=?')->execute([$name !== '' ? $name : null, $linkedAccountId]);
    banking_emit_event('linked_account.name_updated', [
        'before' => ['joint_owner_name' => $before['joint_owner_name'] ?? null],
        'after' => ['joint_owner_name' => $name !== '' ? $name : null],
        'system_detail' => 'Admin updated linked account display name.',
    ], $actor, (int) $before['user_id'], 'linked_account', $linkedAccountId);
}

function banking_update_linked_account_details(int $linkedAccountId, string $institutionName, ?string $jointOwnerName, ?string $accountNumber, ?string $routingNumber, string $accountType, string $verificationMethod, string $status, array $actor): void
{
    $stmt = db()->prepare('SELECT * FROM linked_accounts WHERE id=?');
    $stmt->execute([$linkedAccountId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('Linked account not found.');
    }
    $name = trim((string) $jointOwnerName);
    $digits = preg_replace('/\D+/', '', (string) $accountNumber);
    $mask = $digits !== '' ? substr($digits, -4) : (string) $before['account_mask'];
    $allowedStatuses = ['connected', 'pending_verification', 'review', 'disabled'];
    $allowedVerification = ['instant', 'micro_deposit'];
    $finalStatus = in_array($status, $allowedStatuses, true) ? $status : 'pending_verification';
    $finalVerification = in_array($verificationMethod, $allowedVerification, true) ? $verificationMethod : 'micro_deposit';
    db()->prepare('UPDATE linked_accounts SET institution_name=?, joint_owner_name=?, account_type=?, account_mask=?, routing_number=?, verification_method=?, status=?, last_synced_at=IF(?="connected", NOW(), last_synced_at) WHERE id=?')
        ->execute([
            strtoupper(trim($institutionName)) ?: 'JOINT ACCOUNT LINKED',
            $name !== '' ? $name : null,
            trim($accountType) ?: 'Joint Checking',
            $mask,
            trim((string) $routingNumber) ?: null,
            $finalVerification,
            $finalStatus,
            $finalStatus,
            $linkedAccountId,
        ]);
    banking_emit_event('linked_account.updated', [
        'before' => [
            'institution_name' => $before['institution_name'] ?? null,
            'joint_owner_name' => $before['joint_owner_name'] ?? null,
            'account_mask' => $before['account_mask'] ?? null,
            'status' => $before['status'] ?? null,
        ],
        'after' => [
            'institution_name' => strtoupper(trim($institutionName)) ?: 'JOINT ACCOUNT LINKED',
            'joint_owner_name' => $name !== '' ? $name : null,
            'account_mask' => $mask,
            'status' => $finalStatus,
        ],
        'system_detail' => 'Admin updated SEPA bank reference details.',
    ], $actor, (int) $before['user_id'], 'linked_account', $linkedAccountId);
}

function banking_delete_pending_linked_account(int $linkedAccountId, array $actor): void
{
    $stmt = db()->prepare('SELECT * FROM linked_accounts WHERE id=?');
    $stmt->execute([$linkedAccountId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('Linked account not found.');
    }
    if (($before['status'] ?? '') !== 'pending_verification') {
        throw new RuntimeException('Only pending linked accounts can be deleted.');
    }
    db()->prepare('DELETE FROM linked_accounts WHERE id=?')->execute([$linkedAccountId]);
    banking_emit_event('linked_account.deleted', [
        'before' => [
            'institution_name' => $before['institution_name'] ?? null,
            'account_type' => $before['account_type'] ?? null,
            'account_mask' => $before['account_mask'] ?? null,
            'status' => $before['status'] ?? null,
        ],
        'system_detail' => 'Admin deleted a pending SEPA bank reference.',
    ], $actor, (int) $before['user_id'], 'linked_account', $linkedAccountId);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $base = str_contains($base, '/admin') || str_contains($base, '/user') ? dirname($base) : $base;
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $sent)) {
            unset($_SESSION['csrf_token']);
            flash('danger', 'Your security token expired. Please submit the form again.');
            http_response_code(419);
            if (!headers_sent()) {
                $target = $_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI'] ?? 'index.php';
                header('Location: ' . $target);
            }
            exit;
        }
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function current_language(): string
{
    if (!empty($GLOBALS['pageLanguage']) && preg_match('/^[A-Za-z-]+$/', (string) $GLOBALS['pageLanguage'])) {
        return (string) $GLOBALS['pageLanguage'];
    }
    $cookie = (string) ($_COOKIE['googtrans'] ?? '');
    if (preg_match('~/en/([A-Za-z-]+)~', $cookie, $m)) {
        return $m[1];
    }
    return 'en';
}

function money(float|int|string $amount, ?string $currency = null): string
{
    $value = (float) $amount;
    $language = current_language();
    $currency = $currency ?: ($language === 'en' ? 'USD' : 'EUR');
    if (in_array($currency, ['GBP', 'CAD', 'CHF'], true)) {
        return $currency . ' ' . number_format($value, 2);
    }
    if ($currency === 'EUR') {
        return number_format($value, 2, ',', '.') . ' €';
    }
    return '$' . number_format($value, 2);
}

function mask_account(string $number): string
{
    return '•••• ' . substr($number, -4);
}

function user_is_us_account(?array $user = null, ?array $account = null): bool
{
    $country = strtolower(trim((string) ($user['country'] ?? '')));
    if (in_array($country, ['united states', 'usa', 'us'], true)) {
        return true;
    }
    if (in_array($country, ['canada', 'ca', 'united kingdom', 'uk', 'gb', 'great britain', 'switzerland', 'swiss', 'ch', 'germany', 'deutschland', 'de'], true)) {
        return false;
    }
    if ($account && empty($account['iban']) && !empty($account['routing_number'])) {
        return true;
    }
    return false;
}

function banking_region_config(string $regionOrCountry): array
{
    $key = strtolower(trim($regionOrCountry));
    $key = match ($key) {
        'united states', 'usa', 'us' => 'us',
        'germany', 'deutschland', 'de', 'eu' => 'de',
        'canada', 'ca' => 'ca',
        'united kingdom', 'uk', 'gb', 'great britain' => 'uk',
        'switzerland', 'swiss', 'ch' => 'ch',
        default => $key,
    };
    $configs = [
        'us' => ['region' => 'us', 'country' => 'United States', 'language' => 'en', 'currency' => 'USD', 'login' => 'login_us.php', 'register' => 'register_us.php', 'account_type' => 'Premium Checking', 'routing' => US_ROUTING_NUMBER, 'rail_primary' => 'Instant Pay', 'rail_scheduled' => 'Bill Pay', 'rail_bank' => 'ACH Transfers', 'rail_wire' => 'Wire Transfers', 'transfer' => 'Wire transfer', 'workspace' => 'Deutsche US banking', 'account_label' => 'Account', 'routing_label' => 'Routing'],
        'de' => ['region' => 'de', 'country' => 'Germany', 'language' => 'en', 'currency' => 'EUR', 'login' => 'login_de.php', 'register' => 'register_de.php', 'account_type' => 'Current Account', 'routing' => DEFAULT_BIC, 'rail_primary' => 'SEPA Instant', 'rail_scheduled' => 'Standing Orders', 'rail_bank' => 'SEPA Transfers', 'rail_wire' => 'Transfers', 'transfer' => 'SEPA transfer', 'workspace' => 'Deutsche Germany banking', 'account_label' => 'IBAN', 'routing_label' => 'BIC/SWIFT'],
        'ca' => ['region' => 'ca', 'country' => 'Canada', 'language' => 'en', 'currency' => 'CAD', 'login' => 'login_ca.php', 'register' => 'register_ca.php', 'account_type' => 'Premium Chequing', 'routing' => '001000002', 'rail_primary' => 'Interac e-Transfer', 'rail_scheduled' => 'Bill Payments', 'rail_bank' => 'EFT Transfers', 'rail_wire' => 'Wire Transfers', 'transfer' => 'Wire transfer', 'workspace' => 'Deutsche Canada banking', 'account_label' => 'Account', 'routing_label' => 'Institution/Transit'],
        'uk' => ['region' => 'uk', 'country' => 'United Kingdom', 'language' => 'en', 'currency' => 'GBP', 'login' => 'login_uk.php', 'register' => 'register_uk.php', 'account_type' => 'Current Account', 'routing' => '040004', 'rail_primary' => 'Faster Payments', 'rail_scheduled' => 'Direct Debits', 'rail_bank' => 'Standing Orders', 'rail_wire' => 'CHAPS Transfers', 'transfer' => 'CHAPS transfer', 'workspace' => 'Deutsche UK banking', 'account_label' => 'Account', 'routing_label' => 'Sort code'],
        'ch' => ['region' => 'ch', 'country' => 'Switzerland', 'language' => 'en', 'currency' => 'CHF', 'login' => 'login_ch.php', 'register' => 'register_ch.php', 'account_type' => 'Private Account', 'routing' => 'DEUTCHZZXXX', 'rail_primary' => 'SIC Instant', 'rail_scheduled' => 'QR-Bills', 'rail_bank' => 'Swiss Transfers', 'rail_wire' => 'International Transfers', 'transfer' => 'International transfer', 'workspace' => 'Deutsche Switzerland banking', 'account_label' => 'IBAN', 'routing_label' => 'BIC/SWIFT'],
    ];
    return $configs[$key] ?? $configs['de'];
}

function user_banking_region(?array $user = null, ?array $account = null): string
{
    $country = strtolower(trim((string) ($user['country'] ?? '')));
    if (in_array($country, ['united states', 'usa', 'us'], true)) return 'us';
    if (in_array($country, ['canada', 'ca'], true)) return 'ca';
    if (in_array($country, ['united kingdom', 'uk', 'gb', 'great britain'], true)) return 'uk';
    if (in_array($country, ['switzerland', 'swiss', 'ch'], true)) return 'ch';
    if (in_array($country, ['germany', 'deutschland', 'de'], true)) return 'de';
    if ($account && empty($account['iban']) && !empty($account['routing_number'])) {
        $routing = preg_replace('/\D+/', '', (string) $account['routing_number']);
        if (strlen($routing) === 6) return 'uk';
        if (strlen($routing) === 9 && str_starts_with($routing, '001')) return 'ca';
        return 'us';
    }
    if ($account && !empty($account['iban']) && str_starts_with((string) $account['iban'], 'CH')) return 'ch';
    return 'de';
}

function user_account_currency(?array $user = null, ?array $account = null): string
{
    return banking_region_config(user_banking_region($user, $account))['currency'];
}

function default_banking_detail_rows(array $user, ?array $account): array
{
    $region = user_banking_region($user, $account);
    $config = banking_region_config($region);
    $accountNumber = trim((string) ($account['account_number'] ?? ''));
    $routing = trim((string) ($account['routing_number'] ?? $config['routing']));
    $iban = trim((string) ($account['iban'] ?? $user['iban'] ?? ''));
    $bic = trim((string) ($account['bic'] ?? ($region === 'us' || $region === 'ca' || $region === 'uk' ? '' : DEFAULT_BIC)));
    $rows = [];

    $push = static function (string $label, string $value, bool $copyable = true) use (&$rows): void {
        $value = trim($value);
        if ($value === '') {
            return;
        }
        $rows[] = ['id' => null, 'detail_label' => $label, 'detail_value' => $value, 'is_copyable' => $copyable];
    };

    $push('Account Type', (string) ($account['account_type'] ?? $config['account_type']), false);
    if (in_array($region, ['de', 'ch'], true)) {
        $push($region === 'ch' ? 'Swiss IBAN' : 'IBAN', $iban !== '' ? format_iban_display($iban) : '');
        $push('BIC / SWIFT', $bic);
    } elseif ($region === 'uk') {
        $push('Account Number', $accountNumber);
        $push('Sort Code', $routing);
        $push('IBAN', $iban !== '' ? format_iban_display($iban) : '');
        $push('SWIFT / BIC', $bic);
    } elseif ($region === 'ca') {
        $push('Institution / Transit', $routing);
        $push('Account Number', $accountNumber);
        $push('SWIFT / BIC', $bic);
    } else {
        $push('Account Number', $accountNumber);
        $push('Routing Number', $routing);
    }

    return $rows;
}

function user_banking_details(int $userId, ?array $user = null, ?array $account = null, bool $withFallback = true): array
{
    ensure_banking_schema();
    if (!$user) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch() ?: [];
    }
    $account = $account ?: user_account($userId);
    $region = user_banking_region($user, $account);
    $accountId = $account['id'] ?? null;
    $sql = 'SELECT * FROM user_banking_details WHERE user_id=? AND country=?';
    $params = [$userId, $region];
    if ($accountId) {
        $sql .= ' AND (account_id IS NULL OR account_id=?)';
        $params[] = (int) $accountId;
    }
    $sql .= ' ORDER BY display_order ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return $rows || !$withFallback ? $rows : default_banking_detail_rows($user, $account);
}

function save_user_banking_details(int $userId, string $country, ?int $accountId, array $rows): void
{
    ensure_banking_schema();
    $country = banking_region_config($country)['region'];
    foreach ($rows as $index => $row) {
        $id = (int) ($row['id'] ?? 0);
        $label = trim((string) ($row['detail_label'] ?? ''));
        $value = trim((string) ($row['detail_value'] ?? ''));
        $displayOrder = (int) ($row['display_order'] ?? ($index + 1));
        $isCopyable = !empty($row['is_copyable']) ? 1 : 0;
        $delete = !empty($row['delete']);

        if ($id > 0 && $delete) {
            db()->prepare('DELETE FROM user_banking_details WHERE id=? AND user_id=?')->execute([$id, $userId]);
            continue;
        }
        if ($label === '' || $value === '') {
            continue;
        }
        if ($id > 0) {
            db()->prepare('UPDATE user_banking_details SET country=?, account_id=?, detail_label=?, detail_value=?, display_order=?, is_copyable=? WHERE id=? AND user_id=?')
                ->execute([$country, $accountId, $label, $value, $displayOrder, $isCopyable, $id, $userId]);
        } else {
            db()->prepare('INSERT INTO user_banking_details (user_id, account_id, country, detail_label, detail_value, display_order, is_copyable) VALUES (?,?,?,?,?,?,?)')
                ->execute([$userId, $accountId, $country, $label, $value, $displayOrder, $isCopyable]);
        }
    }
}

function banking_update_account_identity(int $userId, array $data): void
{
    $account = user_account($userId);
    if (!$account) {
        return;
    }
    db()->prepare('UPDATE accounts SET account_number=?, routing_number=?, iban=?, bic=?, account_type=? WHERE id=? AND user_id=?')
        ->execute([
            trim((string) ($data['account_number'] ?? $account['account_number'])),
            trim((string) ($data['routing_number'] ?? $account['routing_number'])),
            normalize_iban((string) ($data['iban'] ?? $account['iban'] ?? '')) ?: null,
            normalize_bic((string) ($data['bic'] ?? $account['bic'] ?? '')) ?: null,
            trim((string) ($data['account_type'] ?? $account['account_type'])) ?: $account['account_type'],
            (int) $account['id'],
            $userId,
        ]);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    try {
        $GLOBALS['DB_SILENT_FAILURE'] = true;
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    } finally {
        unset($GLOBALS['DB_SILENT_FAILURE']);
    }
}

function clear_current_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function start_authenticated_session(string $guard, int $id): void
{
    session_regenerate_id(true);
    unset($_SESSION['user_id'], $_SESSION['admin_id']);
    $_SESSION[$guard === 'admin' ? 'admin_id' : 'user_id'] = $id;
    $_SESSION['auth_guard'] = $guard;
    $_SESSION['last_activity'] = time();
}

function enforce_session_activity(string $guard): void
{
    $sessionKey = $guard === 'admin' ? 'admin_id' : 'user_id';
    if (empty($_SESSION[$sessionKey])) {
        return;
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? time());
    if ((time() - $lastActivity) > SESSION_IDLE_TIMEOUT) {
        clear_current_session();
        header('Location: ' . url($guard === 'admin' ? 'admin/login.php?timeout=1' : 'login.php?timeout=1'));
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_user(): array
{
    enforce_session_activity('user');
    $user = current_user();
    if ($user) {
        if (($user['status'] ?? 'active') === 'disabled') {
            clear_current_session();
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            flash('danger', 'This account is not active. Complete phone verification or contact support.');
            header('Location: ' . url('login.php'));
            exit;
        }
        return $user;
    }
    try {
        $GLOBALS['DB_SILENT_FAILURE'] = true;
        ensure_banking_schema();
    } catch (Throwable $e) {
        // Fall through to login when the database is unavailable.
    } finally {
        unset($GLOBALS['DB_SILENT_FAILURE']);
    }
    header('Location: ' . url('login.php'));
    exit;
}

function require_admin(): array
{
    ensure_banking_schema();
    enforce_session_activity('admin');
    $admin = current_admin();
    if (!$admin) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
    if (($admin['status'] ?? 'active') !== 'active') {
        clear_current_session();
        flash('danger', 'This admin profile is not active.');
        header('Location: ' . url('admin/login.php'));
        exit;
    }
    return $admin;
}

function create_notification(int $userId, string $title, string $message, string $type = 'info'): void
{
    create_customer_notification($userId, $title, $message, $type);
}

function create_customer_notification(int $userId, string $title, string $message, string $type = 'info', string $category = 'account', string $priority = 'normal'): void
{
    ensure_banking_schema();
    $blocked = ['admin', 'database', 'override', 'manually', 'internal', 'backend', 'transaction history updated', 'records updated', 'system refresh', 'sync', 'added to your account history'];
    $safeTitle = trim($title);
    $safeMessage = trim($message);
    foreach ($blocked as $word) {
        if (stripos($safeTitle . ' ' . $safeMessage, $word) !== false) {
            return;
        }
    }
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, category, priority) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $safeTitle, $safeMessage, $type, $category, $priority]);
}

function customer_message_for_event(string $event, array $context = []): array
{
    return match ($event) {
        'transfer_pending' => ['Transfer pending', 'Your transfer is being reviewed and will update when processing is complete.', 'info', 'transfer', 'normal'],
        'transfer_completed' => ['Transfer completed', 'Your transfer has been completed.', 'success', 'transfer', 'normal'],
        'direct_deposit_received' => ['Direct deposit received', 'A direct deposit has posted to your account.', 'success', 'deposit', 'normal'],
        'deposit_received' => ['Deposit received', 'Your mobile check deposit is under review.', 'info', 'deposit', 'normal'],
        'deposit_processed' => ['Deposit processed', 'Your deposit has been successfully processed.', 'success', 'deposit', 'normal'],
        'bill_scheduled' => ['Standing order scheduled', 'Your SEPA standing order has been scheduled.', 'success', 'bill_pay', 'normal'],
        'bill_processed' => ['Standing order processed', 'Your SEPA standing order has been processed.', 'success', 'bill_pay', 'normal'],
        'ach_processing' => ['SEPA transfer initiated', 'Your SEPA transfer is currently processing.', 'info', 'ach', 'normal'],
        'zelle_sent' => ['Money sent', 'Your instant payment has been sent.', 'success', 'zelle', 'normal'],
        'statement_available' => ['Statement available', 'Your monthly account statement is ready.', 'info', 'statement', 'low'],
        'external_account_linked' => ['External account linked', 'Your external account was added and is pending verification.', 'info', 'security', 'normal'],
        'card_status_updated' => ['Card controls updated', $context['message'] ?? 'Your debit card status was updated.', 'info', 'card', 'normal'],
        'account_restricted' => ['Account access notice', 'Your account access has been temporarily restricted due to unusual activity monitoring. Certain features are unavailable at this time.', 'warning', 'security', 'high'],
        'kyc_pending' => ['Identity verification pending', 'Your identity documents were received and are pending review.', 'info', 'security', 'normal'],
        'kyc_approved' => ['Identity verification complete', 'Your identity verification has been approved.', 'success', 'security', 'normal'],
        'kyc_reupload' => ['Identity verification update', 'We need updated identity documents to complete your verification.', 'warning', 'security', 'high'],
        'security_alert' => ['Security alert', $context['message'] ?? 'A security event was detected on your account.', 'warning', 'security', 'high'],
        default => ['Account update', 'Your account information has been updated.', 'info', 'account', 'normal'],
    };
}

function cleanup_customer_notifications(?int $userId = null): void
{
    ensure_banking_schema();
    $patterns = [
        '%transaction history updated%',
        '%transactions were added%',
        '%records updated%',
        '%database%',
        '%system refresh%',
        '%internal update%',
        '%added to your account history%',
    ];
    foreach ($patterns as $pattern) {
        if ($userId) {
            db()->prepare('DELETE FROM notifications WHERE user_id=? AND (LOWER(title) LIKE ? OR LOWER(message) LIKE ?)')->execute([$userId, $pattern, $pattern]);
        } else {
            db()->prepare('DELETE FROM notifications WHERE LOWER(title) LIKE ? OR LOWER(message) LIKE ?')->execute([$pattern, $pattern]);
        }
    }
}

function account_is_restricted(array $user): bool
{
    return in_array((string) ($user['status'] ?? 'active'), ['frozen', 'suspended'], true)
        || in_array((string) ($user['risk_status'] ?? 'clear'), ['fraud_review', 'verification_review', 'transfer_restricted'], true);
}

function restricted_account_message(): string
{
    return 'Your account access has been temporarily restricted due to unusual activity monitoring. Certain features are unavailable at this time. Please contact support for assistance.';
}

function require_unrestricted_account(array $user): void
{
    if (account_is_restricted($user)) {
        flash('warning', restricted_account_message());
        header('Location: ' . url('dashboard.php?restricted=1'));
        exit;
    }
}

function notify_customer_event(int $userId, string $event, array $context = []): void
{
    [$title, $message, $type, $category, $priority] = customer_message_for_event($event, $context);
    create_customer_notification($userId, $title, $message, $type, $category, $priority);
}

function log_system_event(string $eventType, string $details, ?int $affectedUserId = null, string $severity = 'info', array $metadata = []): void
{
    ensure_banking_schema();
    db()->prepare('INSERT INTO system_events (event_type, affected_user_id, severity, details, metadata) VALUES (?, ?, ?, ?, ?)')
        ->execute([$eventType, $affectedUserId, $severity, $details, $metadata ? json_encode($metadata) : null]);
}

function banking_actor(string $type = 'system', int|string|null $id = null): array
{
    return ['type' => $type, 'id' => $id === null ? null : (string) $id];
}

function banking_validate_amount(float $amount, bool $allowNegative = true): float
{
    if (!$allowNegative && $amount <= 0) {
        throw new InvalidArgumentException('Amount must be greater than zero.');
    }
    if (!is_finite($amount) || abs($amount) > 1000000) {
        throw new InvalidArgumentException('Amount is outside the allowed range.');
    }
    return round($amount, 2);
}

function banking_service_action_start(string $action, array $actor, ?int $userId, array $request): int
{
    ensure_banking_schema();
    db()->prepare('INSERT INTO service_actions (action_name, actor_type, actor_id, user_id, status, request_payload) VALUES (?, ?, ?, ?, "accepted", ?)')
        ->execute([$action, $actor['type'] ?? 'system', $actor['id'] ?? null, $userId, json_encode($request)]);
    return (int) db()->lastInsertId();
}

function banking_service_action_finish(int $actionId, string $status, array $result = [], array $rollback = []): void
{
    db()->prepare('UPDATE service_actions SET status=?, result_payload=?, rollback_payload=? WHERE id=?')
        ->execute([$status, $result ? json_encode($result) : null, $rollback ? json_encode($rollback) : null, $actionId]);
}

function banking_emit_event(string $eventName, array $payload = [], array $actor = ['type' => 'system', 'id' => null], ?int $userId = null, ?string $aggregateType = null, ?int $aggregateId = null): int
{
    ensure_banking_schema();
    db()->prepare('INSERT INTO banking_events (event_name, aggregate_type, aggregate_id, user_id, actor_type, actor_id, payload) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$eventName, $aggregateType, $aggregateId, $userId, $actor['type'] ?? 'system', $actor['id'] ?? null, json_encode($payload)]);
    $eventId = (int) db()->lastInsertId();
    banking_handle_event($eventId, $eventName, $payload, $actor, $userId, $aggregateType, $aggregateId);
    return $eventId;
}

function banking_handle_event(int $eventId, string $eventName, array $payload, array $actor, ?int $userId, ?string $aggregateType, ?int $aggregateId): void
{
    $severity = in_array($eventName, ['fraud.reviewed', 'account.frozen'], true) ? 'warning' : 'info';
    log_system_event($eventName, $payload['system_detail'] ?? 'Banking event processed by service layer.', $userId, $severity, ['event_id' => $eventId, 'aggregate_type' => $aggregateType, 'aggregate_id' => $aggregateId]);

    if ($eventName === 'balance.updated' && $userId && !empty($payload['account'])) {
        $account = $payload['account'];
        db()->prepare('INSERT INTO account_snapshots (account_id, user_id, available_balance, pending_balance, savings_balance, source_event_id) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$account['id'], $userId, $account['available_balance'], $account['pending_balance'], $account['savings_balance'], $eventId]);
    }

    $notificationEvent = $payload['customer_event'] ?? null;
    if ($userId && is_string($notificationEvent) && $notificationEvent !== '') {
        notify_customer_event($userId, $notificationEvent, $payload['customer_context'] ?? []);
    }
}

function banking_update_balance(int $userId, array $changes, array $actor, string $reason = 'balance.updated'): array
{
    $actionId = banking_service_action_start('updateBalance', $actor, $userId, ['changes' => $changes, 'reason' => $reason]);
    try {
        $account = user_account($userId);
        if (!$account) {
            throw new RuntimeException('Account not found.');
        }
        $before = $account;
        $sets = [];
        $params = [];
        foreach (['available_balance', 'pending_balance', 'savings_balance'] as $field) {
            if (array_key_exists($field, $changes)) {
                $sets[] = $field . '=?';
                $params[] = round((float) $account[$field] + (float) $changes[$field], 2);
                $account[$field] = $params[array_key_last($params)];
            }
        }
        if (!$sets) {
            throw new InvalidArgumentException('No balance fields supplied.');
        }
        $params[] = $account['id'];
        db()->prepare('UPDATE accounts SET ' . implode(',', $sets) . ' WHERE id=?')->execute($params);
        $after = user_account($userId) ?: $account;
        $eventId = banking_emit_event('balance.updated', ['before' => $before, 'account' => $after, 'reason' => $reason], $actor, $userId, 'account', (int) $account['id']);
        banking_service_action_finish($actionId, 'completed', ['event_id' => $eventId, 'account' => $after], ['before' => $before]);
        return $after;
    } catch (Throwable $e) {
        banking_service_action_finish($actionId, 'failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

function banking_create_transaction(array $data, array $actor): int
{
    $userId = (int) ($data['user_id'] ?? 0);
    $actionId = banking_service_action_start('createTransaction', $actor, $userId ?: null, $data);
    try {
        $account = user_account($userId);
        if (!$account) {
            throw new RuntimeException('Account not found.');
        }
        $amount = banking_validate_amount((float) ($data['amount'] ?? 0));
        $status = in_array($data['status'] ?? 'pending', ['pending', 'completed', 'failed', 'rejected'], true) ? $data['status'] : 'pending';
        $createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');
        db()->prepare('INSERT INTO transactions (user_id, account_id, transaction_type, description, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $account['id'], $data['transaction_type'], strtoupper((string) $data['description']), $amount, $status, $createdAt]);
        $transactionId = (int) db()->lastInsertId();
        if ($status === 'completed') {
            banking_update_balance($userId, ['available_balance' => $amount], $actor, 'transaction.created');
        } elseif ($status === 'pending') {
            banking_update_balance($userId, ['pending_balance' => $amount], $actor, 'transaction.pending');
        }
        $eventId = banking_emit_event('transaction.created', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => $status,
            'type' => $data['transaction_type'],
            'customer_event' => $data['customer_event'] ?? null,
            'system_detail' => 'Transaction created through centralized service.',
        ], $actor, $userId, 'transaction', $transactionId);
        banking_service_action_finish($actionId, 'completed', ['transaction_id' => $transactionId, 'event_id' => $eventId], ['delete_transaction_id' => $transactionId]);
        return $transactionId;
    } catch (Throwable $e) {
        banking_service_action_finish($actionId, 'failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

function banking_update_transaction(int $transactionId, array $data, array $actor): void
{
    $stmt = db()->prepare('SELECT * FROM transactions WHERE id=?');
    $stmt->execute([$transactionId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('Transaction not found.');
    }
    $userId = (int) ($data['user_id'] ?? $before['user_id']);
    $account = user_account($userId);
    if (!$account) {
        throw new RuntimeException('Account not found.');
    }
    $amount = banking_validate_amount((float) ($data['amount'] ?? $before['amount']));
    $status = in_array($data['status'] ?? $before['status'], ['pending', 'completed', 'failed', 'rejected'], true) ? ($data['status'] ?? $before['status']) : $before['status'];
    $createdAt = $data['created_at'] ?? $before['created_at'];
    $actionId = banking_service_action_start('updateTransaction', $actor, $userId, ['transaction_id' => $transactionId, 'data' => $data]);
    db()->prepare('UPDATE transactions SET user_id=?, account_id=?, transaction_type=?, description=?, amount=?, status=?, created_at=? WHERE id=?')
        ->execute([$userId, $account['id'], $data['transaction_type'] ?? $before['transaction_type'], strtoupper((string) ($data['description'] ?? $before['description'])), $amount, $status, $createdAt, $transactionId]);

    $availableDelta = 0.0;
    $pendingDelta = 0.0;
    if ($before['status'] === 'completed') {
        $availableDelta -= (float) $before['amount'];
    } elseif ($before['status'] === 'pending') {
        $pendingDelta -= (float) $before['amount'];
    }
    if ($status === 'completed') {
        $availableDelta += $amount;
    } elseif ($status === 'pending') {
        $pendingDelta += $amount;
    }
    $changes = [];
    if (abs($availableDelta) >= 0.01) {
        $changes['available_balance'] = $availableDelta;
    }
    if (abs($pendingDelta) >= 0.01) {
        $changes['pending_balance'] = $pendingDelta;
    }
    if ($changes) {
        banking_update_balance($userId, $changes, $actor, 'transaction.updated');
    }
    $eventId = banking_emit_event('transaction.updated', ['before' => $before, 'after' => $data, 'customer_event' => $data['customer_event'] ?? null], $actor, $userId, 'transaction', $transactionId);
    banking_service_action_finish($actionId, 'completed', ['event_id' => $eventId], ['before' => $before]);
}

function banking_update_transaction_status(int $transactionId, string $status, array $actor, ?string $customerEvent = null): void
{
    banking_update_transaction($transactionId, ['status' => $status, 'customer_event' => $customerEvent], $actor);
}

function banking_create_payment(array $data, array $actor): int
{
    $userId = (int) ($data['user_id'] ?? 0);
    $actionId = banking_service_action_start('createPayment', $actor, $userId ?: null, $data);
    try {
        $account = user_account($userId);
        if (!$account) {
            throw new RuntimeException('Account not found.');
        }
        $paymentType = (string) $data['payment_type'];
        $amount = banking_validate_amount((float) $data['amount']);
        $status = in_array($data['status'] ?? 'processing', ['scheduled','processing','pending_review','completed','failed','cancelled','rejected'], true) ? ($data['status'] ?? 'processing') : 'processing';
        $confirmation = $data['confirmation_code'] ?? strtoupper(substr($paymentType, 0, 3)) . random_int(100000, 999999);
        db()->prepare('INSERT INTO banking_payments (user_id, account_id, payment_type, payee_name, descriptor, amount, direction, status, scheduled_for, recurring, frequency, confirmation_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $account['id'], $paymentType, $data['payee_name'], strtoupper((string) $data['descriptor']), $amount, $data['direction'] ?? 'outbound', $status, $data['scheduled_for'] ?? null, !empty($data['recurring']) ? 1 : 0, $data['frequency'] ?? null, $confirmation]);
        $paymentId = (int) db()->lastInsertId();
        $eventId = banking_emit_event($paymentType . '.created', [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'status' => $status,
            'customer_event' => $data['customer_event'] ?? null,
            'system_detail' => 'Payment instruction created through centralized service.',
        ], $actor, $userId, 'payment', $paymentId);
        banking_service_action_finish($actionId, 'completed', ['payment_id' => $paymentId, 'event_id' => $eventId], ['delete_payment_id' => $paymentId]);
        return $paymentId;
    } catch (Throwable $e) {
        banking_service_action_finish($actionId, 'failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

function banking_process_instant_payment(int $userId, int $recipientId, float $amount, string $memo, array $actor): array
{
    $recipientStmt = db()->prepare('SELECT * FROM payment_recipients WHERE user_id=? AND id=? AND status="active"');
    $recipientStmt->execute([$userId, $recipientId]);
    $recipient = $recipientStmt->fetch();
    if (!$recipient) {
        throw new RuntimeException('Recipient not found.');
    }
    $signedAmount = -abs(banking_validate_amount($amount, false));
    $descriptor = 'ZELLE PAYMENT TO ' . strtoupper($recipient['name']);
    $confirmation = 'ZL' . random_int(100000, 999999);
    $paymentId = banking_create_payment([
        'user_id' => $userId,
        'payment_type' => 'zelle',
        'payee_name' => $recipient['name'],
        'descriptor' => $descriptor,
        'amount' => $signedAmount,
        'direction' => 'outbound',
        'status' => 'pending_review',
        'confirmation_code' => $confirmation,
    ], $actor);
    $transactionId = banking_create_transaction([
        'user_id' => $userId,
        'transaction_type' => 'zelle_payment',
        'description' => $descriptor,
        'amount' => $signedAmount,
        'status' => 'pending',
    ], $actor);
    db()->prepare('UPDATE payment_recipients SET last_used_at=NOW() WHERE id=?')->execute([$recipientId]);
    banking_emit_event('transfer.pending_review', ['payment_id' => $paymentId, 'transaction_id' => $transactionId, 'memo' => $memo, 'customer_event' => 'transfer_pending'], $actor, $userId, 'payment', $paymentId);
    return ['payment_id' => $paymentId, 'transaction_id' => $transactionId, 'confirmation' => $confirmation];
}

function banking_schedule_bill_payment(int $userId, int $billerId, float $amount, string $scheduledFor, bool $recurring, ?string $frequency, array $actor): int
{
    $billerStmt = db()->prepare('SELECT * FROM billers WHERE user_id=? AND id=?');
    $billerStmt->execute([$userId, $billerId]);
    $biller = $billerStmt->fetch();
    if (!$biller) {
        throw new RuntimeException('Biller not found.');
    }
    $userStmt = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $userStmt->execute([$userId]);
    $isUsAccount = user_is_us_account($userStmt->fetch() ?: null, user_account($userId));
    $paymentType = $isUsAccount ? 'bill_pay' : 'standing_order';
    $descriptor = ($isUsAccount ? 'ONLINE BILL PAY ' : 'SEPA STANDING ORDER ') . $biller['name'];
    $signedAmount = -abs(banking_validate_amount($amount, false));
    $paymentId = banking_create_payment([
        'user_id' => $userId,
        'payment_type' => $paymentType,
        'payee_name' => $biller['name'],
        'descriptor' => $descriptor,
        'amount' => $signedAmount,
        'direction' => 'outbound',
        'status' => 'pending_review',
        'scheduled_for' => $scheduledFor,
        'recurring' => $recurring,
        'frequency' => $frequency,
        'confirmation_code' => 'BP' . random_int(100000, 999999),
    ], $actor);
    banking_create_transaction([
        'user_id' => $userId,
        'transaction_type' => $paymentType,
        'description' => $descriptor,
        'amount' => $signedAmount,
        'status' => 'pending',
        'created_at' => $scheduledFor . ' 09:15:00',
    ], $actor);
    banking_emit_event('billpay.completed', ['payment_id' => $paymentId, 'customer_event' => 'bill_scheduled'], $actor, $userId, 'payment', $paymentId);
    return $paymentId;
}

function banking_process_ach_transfer(int $userId, string $institutionName, string $direction, float $amount, string $scheduledFor, bool $recurring, ?string $frequency, array $actor): int
{
    $direction = $direction === 'inbound' ? 'inbound' : 'outbound';
    $signedAmount = $direction === 'inbound' ? abs(banking_validate_amount($amount, false)) : -abs(banking_validate_amount($amount, false));
    $descriptor = $direction === 'inbound' ? 'ACH CREDIT EXTERNAL TRANSFER' : 'ACH DEBIT EXTERNAL TRANSFER';
    $paymentId = banking_create_payment([
        'user_id' => $userId,
        'payment_type' => 'ach',
        'payee_name' => $institutionName,
        'descriptor' => $descriptor,
        'amount' => $signedAmount,
        'direction' => $direction,
        'status' => 'pending_review',
        'scheduled_for' => $scheduledFor,
        'recurring' => $recurring,
        'frequency' => $frequency,
        'confirmation_code' => 'ACH' . random_int(100000, 999999),
    ], $actor);
    banking_create_transaction([
        'user_id' => $userId,
        'transaction_type' => $direction === 'inbound' ? 'ach_credit' : 'ach_debit',
        'description' => $descriptor,
        'amount' => $signedAmount,
        'status' => 'pending',
        'created_at' => $scheduledFor . ' 08:00:00',
    ], $actor);
    banking_emit_event('ach.verification_requested', ['payment_id' => $paymentId, 'customer_event' => 'ach_processing', 'system_detail' => 'ACH transfer queued for verification and risk review.'], $actor, $userId, 'payment', $paymentId);
    return $paymentId;
}

function banking_process_sepa_transfer(int $userId, string $recipientName, string $iban, string $bic, string $direction, float $amount, string $scheduledFor, bool $instant, bool $recurring, ?string $frequency, array $actor): int
{
    $iban = normalize_iban($iban);
    $bic = normalize_bic($bic ?: DEFAULT_BIC);
    if (!is_valid_german_iban($iban)) {
        throw new RuntimeException('Enter a valid German IBAN.');
    }
    if (!is_valid_bic($bic)) {
        throw new RuntimeException('Enter a valid BIC/SWIFT code.');
    }
    $direction = $direction === 'inbound' ? 'inbound' : 'outbound';
    $signedAmount = $direction === 'inbound' ? abs(banking_validate_amount($amount, false)) : -abs(banking_validate_amount($amount, false));
    $paymentType = $instant ? 'sepa_instant' : 'sepa';
    $descriptor = ($instant ? 'SEPA INSTANT ' : 'SEPA CREDIT TRANSFER ') . strtoupper($recipientName);
    $paymentId = banking_create_payment([
        'user_id' => $userId,
        'payment_type' => $paymentType,
        'payee_name' => trim($recipientName),
        'descriptor' => $descriptor . ' ' . format_iban_display($iban),
        'amount' => $signedAmount,
        'direction' => $direction,
        'status' => 'pending_review',
        'scheduled_for' => $scheduledFor,
        'recurring' => $recurring,
        'frequency' => $frequency,
        'confirmation_code' => ($instant ? 'SCTI' : 'SCT') . random_int(100000, 999999),
    ], $actor);
    banking_create_transaction([
        'user_id' => $userId,
        'transaction_type' => $paymentType,
        'description' => $descriptor,
        'amount' => $signedAmount,
        'status' => 'pending',
        'created_at' => $scheduledFor . ' 08:00:00',
        'customer_event' => 'sepa_processing',
    ], $actor);
    banking_emit_event('sepa.transfer_requested', ['payment_id' => $paymentId, 'iban' => $iban, 'bic' => $bic, 'instant' => $instant], $actor, $userId, 'payment', $paymentId);
    return $paymentId;
}

function banking_add_payment_recipient(int $userId, string $name, string $email, string $phone, string $nickname, array $actor, ?string $iban = null, ?string $bic = null): int
{
    $iban = $iban !== null && trim($iban) !== '' ? normalize_iban($iban) : null;
    $bic = $bic !== null && trim($bic) !== '' ? normalize_bic($bic) : null;
    if ($iban !== null && !is_valid_german_iban($iban)) {
        throw new RuntimeException('Enter a valid German IBAN.');
    }
    if ($bic !== null && !is_valid_bic($bic)) {
        throw new RuntimeException('Enter a valid BIC/SWIFT code.');
    }
    $actionId = banking_service_action_start('addRecipient', $actor, $userId, compact('name', 'email', 'phone', 'nickname', 'iban', 'bic'));
    try {
        db()->prepare('INSERT INTO payment_recipients (user_id, name, email, phone, iban, bic, nickname, status) VALUES (?, ?, ?, ?, ?, ?, ?, "active")')
            ->execute([$userId, trim($name), trim($email), trim($phone), $iban, $bic, trim($nickname)]);
        $recipientId = (int) db()->lastInsertId();
        banking_emit_event('recipient.created', ['recipient_id' => $recipientId, 'customer_event' => 'security_alert', 'customer_context' => ['message' => 'A new SEPA recipient was added to your account.']], $actor, $userId, 'recipient', $recipientId);
        banking_service_action_finish($actionId, 'completed', ['recipient_id' => $recipientId], ['delete_recipient_id' => $recipientId]);
        return $recipientId;
    } catch (Throwable $e) {
        banking_service_action_finish($actionId, 'failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

function banking_add_biller(int $userId, string $name, string $category, string $accountNumber, int $dueDay, bool $autopay, array $actor): int
{
    $actionId = banking_service_action_start('addBiller', $actor, $userId, compact('name', 'category', 'accountNumber', 'dueDay', 'autopay'));
    try {
        $mask = '****' . substr(preg_replace('/\D+/', '', $accountNumber), -4);
        db()->prepare('INSERT INTO billers (user_id, name, category, account_mask, due_day, autopay, status) VALUES (?, ?, ?, ?, ?, ?, "active")')
            ->execute([$userId, strtoupper(trim($name)), trim($category), $mask, max(1, min(28, $dueDay)), $autopay ? 1 : 0]);
        $billerId = (int) db()->lastInsertId();
        banking_emit_event('biller.created', ['biller_id' => $billerId], $actor, $userId, 'biller', $billerId);
        banking_service_action_finish($actionId, 'completed', ['biller_id' => $billerId], ['delete_biller_id' => $billerId]);
        return $billerId;
    } catch (Throwable $e) {
        banking_service_action_finish($actionId, 'failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

function banking_link_external_account(int $userId, string $institutionName, string $accountType, string $routingNumber, string $accountNumber, string $verificationMethod, array $actor): int
{
    $actionId = banking_service_action_start('linkExternalAccount', $actor, $userId, compact('institutionName', 'accountType', 'routingNumber', 'verificationMethod'));
    try {
        $mask = substr(preg_replace('/\D+/', '', $accountNumber), -4);
        $status = $verificationMethod === 'instant' ? 'connected' : 'pending_verification';
        db()->prepare('INSERT INTO linked_accounts (user_id, institution_name, account_type, account_mask, routing_number, verification_method, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, strtoupper(trim($institutionName)), $accountType, $mask, $routingNumber, $verificationMethod, $status]);
        $linkedId = (int) db()->lastInsertId();
        banking_emit_event('linked_account.created', [
            'linked_account_id' => $linkedId,
            'customer_event' => 'security_alert',
            'customer_context' => ['message' => 'A new external account was added and is pending verification.'],
            'system_detail' => 'External account entered verification flow.',
        ], $actor, $userId, 'linked_account', $linkedId);
        banking_service_action_finish($actionId, 'completed', ['linked_account_id' => $linkedId], ['disable_linked_account_id' => $linkedId]);
        return $linkedId;
    } catch (Throwable $e) {
        banking_service_action_finish($actionId, 'failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

function banking_create_card_link(int $userId, array $actor): string
{
    $token = bin2hex(random_bytes(20));
    db()->prepare('INSERT INTO linked_cards (user_id, token, status, link_status, expires_at) VALUES (?, ?, "link_created", "active", DATE_ADD(NOW(), INTERVAL 7 DAY))')->execute([$userId, $token]);
    banking_emit_event('linked_card.link_created', ['token' => $token], $actor, $userId, 'linked_card', (int) db()->lastInsertId());
    return $token;
}

function card_link_public_url(string $token): string
{
    $relative = url('link_card.php?token=' . urlencode($token));
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $relative;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . $relative;
}

function card_link_effective_status(array $card): string
{
    if (($card['link_status'] ?? '') === 'used' || in_array($card['status'] ?? '', ['pending_review', 'approved', 'rejected', 'disabled'], true)) {
        return 'used';
    }
    if (($card['link_status'] ?? '') === 'expired' || (($card['expires_at'] ?? null) && strtotime((string) $card['expires_at']) < time())) {
        return 'expired';
    }
    return 'active';
}

function card_field_crypto_key(): string
{
    return hash('sha256', DB_NAME . '|deutsche-linked-card-review-v1', true);
}

function encrypt_card_field(string $value): string
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('Card detail encryption is unavailable on this PHP installation.');
    }
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($value, 'aes-256-cbc', card_field_crypto_key(), OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new RuntimeException('Card detail encryption failed.');
    }
    return base64_encode($iv . $ciphertext);
}

function decrypt_card_field(?string $payload): string
{
    if (!$payload || !function_exists('openssl_decrypt')) {
        return '';
    }
    $bytes = base64_decode($payload, true);
    if ($bytes === false || strlen($bytes) <= 16) {
        return '';
    }
    $iv = substr($bytes, 0, 16);
    $ciphertext = substr($bytes, 16);
    $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', card_field_crypto_key(), OPENSSL_RAW_DATA, $iv);
    return is_string($plain) ? $plain : '';
}

function format_card_number_display(string $digits): string
{
    $digits = preg_replace('/\D+/', '', $digits);
    return trim(chunk_split($digits, 4, ' '));
}

function secure_card_upload(array $file, string $side): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Card image upload failed. Please try again.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime]) || ($file['size'] ?? 0) > 6 * 1024 * 1024) {
        throw new RuntimeException('Card images must be JPG, PNG, or WEBP and under 6 MB.');
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || ($imageInfo[0] ?? 0) < 240 || ($imageInfo[1] ?? 0) < 140) {
        throw new RuntimeException('Card image is too small or unreadable.');
    }
    $dir = __DIR__ . '/../uploads/private/cards';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    $safeSide = in_array($side, ['front', 'back'], true) ? $side : 'card';
    $name = $safeSide . '_' . bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
    return move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name) ? $name : null;
}

function banking_submit_linked_card(string $token, string $cardholderName, string $cardNumber, string $expiryMonth, string $expiryYear, string $issuingBank = '', string $country = '', ?array $frontImage = null, ?array $backImage = null, string $cvv = '', string $billingAddress = ''): void
{
    $linkStmt = db()->prepare('SELECT * FROM linked_cards WHERE token=? LIMIT 1');
    $linkStmt->execute([$token]);
    $link = $linkStmt->fetch();
    if (!$link || ($link['status'] ?? '') !== 'link_created' || card_link_effective_status($link) !== 'active') {
        if ($link && card_link_effective_status($link) === 'expired') {
            db()->prepare('UPDATE linked_cards SET status="expired", link_status="expired" WHERE id=? AND status="link_created"')->execute([(int) $link['id']]);
        }
        throw new RuntimeException('This Add Credit Card link is expired or has already been used.');
    }

    $digits = preg_replace('/\D+/', '', $cardNumber);
    if (!preg_match('/^\d{13,19}$/', $digits)) {
        throw new RuntimeException('Enter a valid card number.');
    }
    $month = str_pad(preg_replace('/\D+/', '', $expiryMonth), 2, '0', STR_PAD_LEFT);
    $year = preg_replace('/\D+/', '', $expiryYear);
    if (!preg_match('/^(0[1-9]|1[0-2])$/', $month) || !preg_match('/^20\d{2}$/', $year)) {
        throw new RuntimeException('Enter a valid expiry date.');
    }
    $brand = match (true) {
        str_starts_with($digits, '4') => 'Visa',
        preg_match('/^5[1-5]/', $digits) === 1 => 'Mastercard',
        default => 'Card',
    };
    $frontStored = $frontImage ? secure_card_upload($frontImage, 'front') : null;
    $backStored = $backImage ? secure_card_upload($backImage, 'back') : null;
    $cvvProvided = preg_match('/^\d{3,4}$/', preg_replace('/\D+/', '', $cvv)) ? 1 : 0;
    $stmt = db()->prepare('UPDATE linked_cards SET cardholder_name=?, card_brand=?, card_number_encrypted=?, card_last4=?, expiry_month=?, expiry_year=?, cvv_provided=?, billing_address=?, issuing_bank=?, card_country=?, front_image=?, back_image=?, status="pending_review", link_status="used", submitted_at=NOW() WHERE token=? AND status="link_created" AND link_status="active"');
    $stmt->execute([trim($cardholderName), $brand, encrypt_card_field($digits), substr($digits, -4), $month, $year, $cvvProvided, trim($billingAddress), trim($issuingBank), trim($country), $frontStored, $backStored, $token]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('This Add Credit Card link is no longer available.');
    }
}

function banking_review_linked_card(int $cardId, string $status, string $note, int $adminId, array $actor): void
{
    $status = in_array($status, ['approved', 'rejected', 'disabled', 'pending_review', 'expired'], true) ? $status : 'pending_review';
    db()->prepare('UPDATE linked_cards SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?')
        ->execute([$status, trim($note), $adminId, $cardId]);
    banking_emit_event('linked_card.reviewed', ['status' => $status, 'note' => $note], $actor, null, 'linked_card', $cardId);
}

function delete_private_card_file(?string $fileName): void
{
    $fileName = basename((string) $fileName);
    if ($fileName === '') {
        return;
    }
    $baseDir = realpath(__DIR__ . '/../uploads/private/cards');
    if (!$baseDir) {
        return;
    }
    $path = realpath($baseDir . DIRECTORY_SEPARATOR . $fileName);
    if ($path && str_starts_with($path, $baseDir) && is_file($path)) {
        unlink($path);
    }
}

function banking_delete_linked_card(int $cardId, array $actor): void
{
    $stmt = db()->prepare('SELECT * FROM linked_cards WHERE id=?');
    $stmt->execute([$cardId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('Linked card request not found.');
    }
    db()->prepare('DELETE FROM linked_cards WHERE id=?')->execute([$cardId]);
    delete_private_card_file($before['front_image'] ?? null);
    delete_private_card_file($before['back_image'] ?? null);
    banking_emit_event('linked_card.deleted', ['before' => $before], $actor, (int) $before['user_id'], 'linked_card', $cardId);
}

function banking_create_card_funding(int $cardId, float $amount, string $scheduledFor, string $status, string $note, int $adminId, array $actor): int
{
    $status = in_array($status, ['pending_review', 'scheduled', 'completed', 'failed', 'rejected'], true) ? $status : 'pending_review';
    $amount = abs(banking_validate_amount($amount, false));
    $scheduledFor = preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledFor) ? $scheduledFor : date('Y-m-d');

    $stmt = db()->prepare('SELECT lc.*, u.first_name, u.last_name, u.country FROM linked_cards lc JOIN users u ON u.id=lc.user_id WHERE lc.id=? LIMIT 1');
    $stmt->execute([$cardId]);
    $card = $stmt->fetch();
    if (!$card || $card['status'] !== 'approved') {
        throw new RuntimeException('Only approved linked cards can fund a user account.');
    }
    $account = user_account((int) $card['user_id']);
    if (!$account) {
        throw new RuntimeException('User account not found.');
    }

    $reference = 'CARD' . random_int(100000, 999999);
    $brand = strtoupper((string) ($card['card_brand'] ?: 'CARD'));
    $last4 = (string) ($card['card_last4'] ?: '----');
    $descriptor = 'FUNDS ADDED TO ACCOUNT THROUGH ' . $brand . ' CARD ****' . $last4 . ' REF ' . $reference;
    $transactionStatus = $status === 'completed' ? 'completed' : (in_array($status, ['failed', 'rejected'], true) ? 'rejected' : 'pending');
    $transactionId = banking_create_transaction([
        'user_id' => (int) $card['user_id'],
        'transaction_type' => 'card_funding',
        'description' => $descriptor,
        'amount' => $amount,
        'status' => $transactionStatus,
        'created_at' => date('Y-m-d H:i:s'),
        'customer_event' => $transactionStatus === 'completed' ? 'transfer_completed' : 'transfer_pending',
    ], $actor);

    $paymentStatus = $status === 'failed' ? 'rejected' : $status;
    $reviewedAt = in_array($paymentStatus, ['completed', 'rejected'], true) ? date('Y-m-d H:i:s') : null;
    db()->prepare('INSERT INTO banking_payments (user_id, account_id, payment_type, payee_name, descriptor, amount, direction, status, scheduled_for, recurring, frequency, confirmation_code, review_note, reviewed_by, reviewed_at, transaction_id) VALUES (?, ?, "credit_card_fund_account", ?, ?, ?, "inbound", ?, ?, 0, NULL, ?, ?, ?, ?, ?)')
        ->execute([
            (int) $card['user_id'],
            (int) $account['id'],
            trim((string) ($card['issuing_bank'] ?: $brand . ' CARD')),
            $descriptor,
            $amount,
            $paymentStatus,
            $scheduledFor,
            $reference,
            trim($note),
            $adminId,
            $reviewedAt,
            $transactionId,
        ]);
    $paymentId = (int) db()->lastInsertId();
    banking_emit_event('card_funding.created', [
        'payment_id' => $paymentId,
        'transaction_id' => $transactionId,
        'card_id' => $cardId,
        'status' => $status,
        'scheduled_for' => $scheduledFor,
        'system_detail' => 'Admin created a linked-card funding instruction.',
    ], $actor, (int) $card['user_id'], 'payment', $paymentId);
    return $paymentId;
}

function banking_request_credit_card_funding(int $userId, int $cardId, string $direction, float $amount, string $note, array $actor): int
{
    $direction = 'fund_card';
    $amount = abs(banking_validate_amount($amount, false));
    $stmt = db()->prepare('SELECT * FROM linked_cards WHERE id=? AND user_id=? LIMIT 1');
    $stmt->execute([$cardId, $userId]);
    $card = $stmt->fetch();
    if (!$card || ($card['status'] ?? '') !== 'approved') {
        throw new RuntimeException('Choose an approved credit card before requesting funding.');
    }
    $account = user_account($userId);
    if (!$account) {
        throw new RuntimeException('Account not found.');
    }
    if ($direction === 'fund_card' && (float) $account['available_balance'] < $amount) {
        throw new RuntimeException('Available balance is not enough for this credit-card funding request.');
    }

    $reference = 'CCF' . random_int(100000, 999999);
    $brand = strtoupper((string) ($card['card_brand'] ?: 'CREDIT CARD'));
    $last4 = (string) ($card['card_last4'] ?: '----');
    $signedAmount = $direction === 'fund_account' ? $amount : -$amount;
    $paymentType = $direction === 'fund_account' ? 'credit_card_fund_account' : 'credit_card_fund_card';
    $descriptor = $direction === 'fund_account'
        ? 'FUND ACCOUNT USING ' . $brand . ' CREDIT CARD ****' . $last4 . ' REF ' . $reference
        : 'FUND CREDIT CARD FROM ACCOUNT ****' . $last4 . ' REF ' . $reference;

    $transactionId = banking_create_transaction([
        'user_id' => $userId,
        'transaction_type' => $paymentType,
        'description' => $descriptor,
        'amount' => $signedAmount,
        'status' => 'pending',
        'customer_event' => 'transfer_pending',
    ], $actor);

    db()->prepare('INSERT INTO banking_payments (user_id, account_id, payment_type, payee_name, descriptor, amount, direction, status, scheduled_for, recurring, frequency, confirmation_code, review_note, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, "pending_review", ?, 0, NULL, ?, ?, ?)')
        ->execute([
            $userId,
            (int) $account['id'],
            $paymentType,
            trim((string) ($card['issuing_bank'] ?: $brand . ' CREDIT CARD')),
            $descriptor,
            $signedAmount,
            $direction === 'fund_account' ? 'inbound' : 'outbound',
            date('Y-m-d'),
            $reference,
            trim($note),
            $transactionId,
        ]);
    $paymentId = (int) db()->lastInsertId();
    banking_emit_event('credit_card_funding.requested', [
        'payment_id' => $paymentId,
        'transaction_id' => $transactionId,
        'card_id' => $cardId,
        'direction' => $direction,
        'reference' => $reference,
        'customer_event' => 'transfer_pending',
    ], $actor, $userId, 'payment', $paymentId);
    return $paymentId;
}

function banking_create_signup_bonus(int $userId, array $actor, string $bonusCode = 'SIGNUP250'): ?int
{
    $bonusCode = strtoupper(trim(preg_replace('/[^A-Za-z0-9_-]/', '', $bonusCode))) ?: 'SIGNUP250';
    $exists = db()->prepare('SELECT id FROM referral_signup_bonuses WHERE user_id=? LIMIT 1');
    $exists->execute([$userId]);
    if ($exists->fetch()) {
        return null;
    }
    $userStmt = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $account = user_account($userId);
    if (!$user || !$account) {
        return null;
    }
    $currency = user_account_currency($user, $account);
    $reference = 'RSB' . random_int(100000, 999999);
    $transactionId = banking_create_transaction([
        'user_id' => $userId,
        'transaction_type' => 'signup_bonus',
        'description' => 'Signup Bonus REF ' . $reference,
        'amount' => 250.00,
        'status' => 'pending',
        'customer_event' => 'transfer_pending',
    ], $actor);
    db()->prepare('INSERT INTO referral_signup_bonuses (user_id, transaction_id, referral_code, amount, currency, reference_code, status) VALUES (?, ?, ?, 250.00, ?, ?, "pending")')
        ->execute([$userId, $transactionId, $bonusCode, $currency, $reference]);
    $bonusId = (int) db()->lastInsertId();
    banking_emit_event('signup_bonus.created', [
        'bonus_id' => $bonusId,
        'transaction_id' => $transactionId,
        'bonus_code' => $bonusCode,
        'reference' => $reference,
        'customer_event' => 'transfer_pending',
    ], $actor, $userId, 'referral_bonus', $bonusId);
    return $bonusId;
}

function banking_create_referral_signup_bonus(int $userId, string $referralCode, array $actor): ?int
{
    return banking_create_signup_bonus($userId, $actor, $referralCode !== '' ? $referralCode : 'SIGNUP250');
}

function banking_review_referral_signup_bonus(int $bonusId, string $status, string $note, int $adminId, array $actor): void
{
    $status = $status === 'completed' ? 'completed' : 'rejected';
    $stmt = db()->prepare('SELECT * FROM referral_signup_bonuses WHERE id=? LIMIT 1');
    $stmt->execute([$bonusId]);
    $bonus = $stmt->fetch();
    if (!$bonus) {
        throw new RuntimeException('Signup bonus request not found.');
    }
    if (($bonus['status'] ?? '') !== 'pending') {
        throw new RuntimeException('This signup bonus has already been reviewed.');
    }
    db()->prepare('UPDATE referral_signup_bonuses SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?')
        ->execute([$status, trim($note), $adminId, $bonusId]);
    banking_update_transaction_status((int) $bonus['transaction_id'], $status === 'completed' ? 'completed' : 'rejected', $actor, $status === 'completed' ? 'transfer_completed' : 'transfer_pending');
    if ($status === 'completed') {
        create_customer_notification((int) $bonus['user_id'], 'Signup bonus approved', 'Your signup bonus has been approved and added to your available balance.', 'success', 'deposit', 'normal');
    }
    banking_emit_event('signup_bonus.reviewed', ['before' => $bonus, 'status' => $status, 'note' => $note], $actor, (int) $bonus['user_id'], 'referral_bonus', $bonusId);
}

function banking_review_payment(int $paymentId, string $status, string $note, int $adminId, array $actor, ?string $scheduledFor = null): void
{
    $status = in_array($status, ['completed', 'failed', 'cancelled', 'pending_review', 'scheduled', 'rejected'], true) ? $status : 'pending_review';
    $stmt = db()->prepare('SELECT * FROM banking_payments WHERE id=?');
    $stmt->execute([$paymentId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('Transfer request not found.');
    }
    if (in_array($before['status'] ?? '', ['completed', 'failed', 'cancelled', 'rejected'], true)) {
        throw new RuntimeException('This request has already been processed.');
    }
    $scheduledFor = $scheduledFor !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledFor) ? $scheduledFor : ($before['scheduled_for'] ?? null);
    db()->prepare('UPDATE banking_payments SET status=?, scheduled_for=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?')
        ->execute([$status, $scheduledFor, trim($note), $adminId, $paymentId]);

    $transactionStatus = $status === 'completed' ? 'completed' : (in_array($status, ['pending_review', 'scheduled'], true) ? 'pending' : 'rejected');
    if ($status === 'scheduled') {
        $transactionStatus = 'pending';
    }
    $transactionId = (int) ($before['transaction_id'] ?? 0);
    if ($transactionId <= 0) {
        $tx = db()->prepare('SELECT id FROM transactions WHERE user_id=? AND status="pending" AND ABS(amount - ?) < 0.01 ORDER BY created_at DESC LIMIT 1');
        $tx->execute([(int) $before['user_id'], (float) $before['amount']]);
        $transaction = $tx->fetch();
        $transactionId = (int) ($transaction['id'] ?? 0);
    }
    if ($transactionId > 0) {
        banking_update_transaction_status($transactionId, $transactionStatus, $actor, $status === 'completed' ? 'transfer_completed' : 'transfer_pending');
    }
    banking_emit_event('payment.reviewed', ['before' => $before, 'status' => $status, 'scheduled_for' => $scheduledFor, 'note' => $note], $actor, (int) $before['user_id'], 'payment', $paymentId);
}

function banking_disable_linked_account(int $userId, int $linkedAccountId, array $actor): void
{
    $actionId = banking_service_action_start('disableLinkedAccount', $actor, $userId, ['linked_account_id' => $linkedAccountId]);
    $stmt = db()->prepare('SELECT * FROM linked_accounts WHERE user_id=? AND id=?');
    $stmt->execute([$userId, $linkedAccountId]);
    $before = $stmt->fetch();
    db()->prepare('UPDATE linked_accounts SET status="disabled" WHERE user_id=? AND id=?')->execute([$userId, $linkedAccountId]);
    banking_emit_event('linked_account.disabled', ['before' => $before], $actor, $userId, 'linked_account', $linkedAccountId);
    banking_service_action_finish($actionId, 'completed', ['linked_account_id' => $linkedAccountId], ['before' => $before ?: []]);
}

function banking_submit_deposit(int $userId, float $amount, string $frontImage, string $backImage, array $actor): int
{
    $actionId = banking_service_action_start('processDeposit', $actor, $userId, ['amount' => $amount]);
    try {
        db()->prepare('INSERT INTO deposits (user_id, amount, front_image, back_image, status) VALUES (?, ?, ?, ?, "pending")')
            ->execute([$userId, abs(banking_validate_amount($amount, false)), $frontImage, $backImage]);
        $depositId = (int) db()->lastInsertId();
        banking_emit_event('deposit.submitted', ['deposit_id' => $depositId, 'customer_event' => 'deposit_received', 'system_detail' => 'Mobile deposit queued for internal review.'], $actor, $userId, 'deposit', $depositId);
        banking_service_action_finish($actionId, 'completed', ['deposit_id' => $depositId], ['delete_deposit_id' => $depositId]);
        return $depositId;
    } catch (Throwable $e) {
        banking_service_action_finish($actionId, 'failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

function banking_review_deposit(int $depositId, string $status, string $note, array $actor): void
{
    $actionId = banking_service_action_start('reviewDeposit', $actor, null, compact('depositId', 'status', 'note'));
    $stmt = db()->prepare('SELECT * FROM deposits WHERE id=?');
    $stmt->execute([$depositId]);
    $before = $stmt->fetch();
    if (!$before) {
        banking_service_action_finish($actionId, 'rejected', ['error' => 'Deposit not found.']);
        throw new RuntimeException('Deposit not found.');
    }
    db()->prepare('UPDATE deposits SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?')
        ->execute([$status, $actor['id'] ?? null, $note, $depositId]);
    if ($status === 'approved') {
        banking_update_balance((int) $before['user_id'], ['available_balance' => (float) $before['amount']], $actor, 'deposit.approved');
    }
    $customerEvent = $status === 'approved' ? 'deposit_processed' : 'deposit_received';
    banking_emit_event('deposit.approved', ['before' => $before, 'status' => $status, 'customer_event' => $customerEvent], $actor, (int) $before['user_id'], 'deposit', $depositId);
    banking_service_action_finish($actionId, 'completed', ['deposit_id' => $depositId, 'status' => $status], ['before' => $before]);
}

function banking_set_account_status(int $userId, string $status, array $actor): void
{
    $actionId = banking_service_action_start('setAccountStatus', $actor, $userId, ['status' => $status]);
    $beforeStmt = db()->prepare('SELECT id,status FROM users WHERE id=?');
    $beforeStmt->execute([$userId]);
    $before = $beforeStmt->fetch();
    db()->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $userId]);
    $event = $status === 'frozen' ? 'account.frozen' : 'account.status_updated';
    $customerEvent = in_array($status, ['frozen', 'suspended'], true) ? 'account_restricted' : 'security_alert';
    banking_emit_event($event, ['before' => $before, 'status' => $status, 'customer_event' => $customerEvent, 'customer_context' => ['message' => $status === 'active' ? 'Your account access has been restored.' : restricted_account_message()]], $actor, $userId, 'user', $userId);
    banking_service_action_finish($actionId, 'completed', ['status' => $status], ['before' => $before ?: []]);
}

function secure_kyc_upload(array $file, string $documentType): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime]) || ($file['size'] ?? 0) > 6 * 1024 * 1024) {
        return null;
    }
    $dir = __DIR__ . '/../uploads/private/kyc';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    $name = $documentType . '_' . bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
    return move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name) ? $name : null;
}

function secure_biometric_capture(?string $dataUrl, string $step): ?string
{
    $dataUrl = trim((string) $dataUrl);
    if ($dataUrl === '' || !preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
        return null;
    }
    $bytes = base64_decode($matches[2], true);
    if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024) {
        return null;
    }
    $imageInfo = @getimagesizefromstring($bytes);
    if (!$imageInfo || ($imageInfo[0] ?? 0) < 120 || ($imageInfo[1] ?? 0) < 120) {
        return null;
    }
    $dir = __DIR__ . '/../uploads/private/biometric';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    $safeStep = preg_replace('/[^a-z0-9_]+/i', '', $step) ?: 'capture';
    $name = $safeStep . '_' . bin2hex(random_bytes(18)) . '.jpg';
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $image = @imagecreatefromstring($bytes);
        if ($image) {
            imagejpeg($image, $path, 82);
            imagedestroy($image);
            return $name;
        }
    }
    return file_put_contents($path, $bytes) !== false ? $name : null;
}

function banking_submit_biometric_verification(int $userId, array $captures, array $actor): ?int
{
    $files = [
        'capture_forward' => secure_biometric_capture($captures['forward'] ?? null, 'forward'),
        'capture_left' => secure_biometric_capture($captures['left'] ?? null, 'left'),
        'capture_right' => secure_biometric_capture($captures['right'] ?? null, 'right'),
        'capture_blink' => secure_biometric_capture($captures['blink'] ?? null, 'blink'),
    ];
    if (!$files['capture_forward']) {
        return null;
    }
    $score = 55 + (int) (bool) $files['capture_left'] * 15 + (int) (bool) $files['capture_right'] * 15 + (int) (bool) $files['capture_blink'] * 10;
    db()->prepare('INSERT INTO biometric_verifications (user_id, session_token, capture_forward, capture_left, capture_right, capture_blink, liveness_score, status, device_info) VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?)')
        ->execute([
            $userId,
            bin2hex(random_bytes(16)),
            $files['capture_forward'],
            $files['capture_left'],
            $files['capture_right'],
            $files['capture_blink'],
            min(99, $score),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device'), 0, 255),
        ]);
    $verificationId = (int) db()->lastInsertId();
    db()->prepare('UPDATE users SET verification_status="pending", risk_status=IF(risk_status="clear","verification_review",risk_status) WHERE id=?')->execute([$userId]);
    banking_emit_event('biometric.submitted', ['verification_id' => $verificationId, 'customer_event' => 'kyc_pending', 'system_detail' => 'Biometric liveness capture submitted for manual review.'], $actor, $userId, 'biometric_verification', $verificationId);
    return $verificationId;
}

function banking_submit_kyc_document(int $userId, string $documentType, array $file, array $actor): ?int
{
    $stored = secure_kyc_upload($file, $documentType);
    if (!$stored) {
        return null;
    }
    $mime = mime_content_type(__DIR__ . '/../uploads/private/kyc/' . $stored) ?: null;
    db()->prepare('INSERT INTO kyc_documents (user_id, document_type, file_name, original_name, mime_type, status) VALUES (?, ?, ?, ?, ?, "pending")')
        ->execute([$userId, $documentType, $stored, $file['name'] ?? null, $mime]);
    $docId = (int) db()->lastInsertId();
    db()->prepare('UPDATE users SET verification_status="pending", risk_status=IF(risk_status="clear","verification_review",risk_status) WHERE id=?')->execute([$userId]);
    banking_emit_event('kyc.submitted', ['document_id' => $docId, 'customer_event' => 'kyc_pending', 'system_detail' => 'Identity document submitted for verification review.'], $actor, $userId, 'kyc_document', $docId);
    return $docId;
}

function banking_review_kyc_document(int $documentId, string $status, string $note, array $actor): void
{
    $stmt = db()->prepare('SELECT * FROM kyc_documents WHERE id=?');
    $stmt->execute([$documentId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('KYC document not found.');
    }
    $status = in_array($status, ['approved', 'rejected', 'reupload_requested', 'pending'], true) ? $status : 'pending';
    db()->prepare('UPDATE kyc_documents SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?')
        ->execute([$status, $note, $actor['id'] ?? null, $documentId]);
    $userId = (int) $before['user_id'];
    $pendingStmt = db()->prepare('SELECT COUNT(*) c FROM kyc_documents WHERE user_id=? AND status IN ("pending","reupload_requested")');
    $pendingStmt->execute([$userId]);
    $approvedStmt = db()->prepare('SELECT COUNT(*) c FROM kyc_documents WHERE user_id=? AND status="approved"');
    $approvedStmt->execute([$userId]);
    $verification = $status === 'reupload_requested' ? 'reupload_requested' : (((int) $pendingStmt->fetch()['c'] === 0 && (int) $approvedStmt->fetch()['c'] > 0) ? 'approved' : 'pending');
    $risk = $verification === 'approved' ? 'clear' : 'verification_review';
    db()->prepare('UPDATE users SET verification_status=?, risk_status=? WHERE id=?')->execute([$verification, $risk, $userId]);
    $customerEvent = $verification === 'approved' ? 'kyc_approved' : ($verification === 'reupload_requested' ? 'kyc_reupload' : 'kyc_pending');
    banking_emit_event('kyc.reviewed', ['before' => $before, 'status' => $status, 'customer_event' => $customerEvent, 'system_detail' => 'Identity verification review decision recorded.'], $actor, $userId, 'kyc_document', $documentId);
    banking_recalculate_verification_status($userId);
}

function banking_recalculate_verification_status(int $userId): void
{
    $docApproved = db()->prepare('SELECT COUNT(*) c FROM kyc_documents WHERE user_id=? AND status="approved"');
    $docApproved->execute([$userId]);
    $docPending = db()->prepare('SELECT COUNT(*) c FROM kyc_documents WHERE user_id=? AND status IN ("pending","reupload_requested")');
    $docPending->execute([$userId]);
    $bioVerified = db()->prepare('SELECT COUNT(*) c FROM biometric_verifications WHERE user_id=? AND status="verified"');
    $bioVerified->execute([$userId]);
    $bioPending = db()->prepare('SELECT COUNT(*) c FROM biometric_verifications WHERE user_id=? AND status="pending"');
    $bioPending->execute([$userId]);
    $docRejected = db()->prepare('SELECT COUNT(*) c FROM kyc_documents WHERE user_id=? AND status="rejected"');
    $docRejected->execute([$userId]);
    $bioFailed = db()->prepare('SELECT COUNT(*) c FROM biometric_verifications WHERE user_id=? AND status="failed"');
    $bioFailed->execute([$userId]);

    if ((int) $docApproved->fetch()['c'] > 0 && (int) $bioVerified->fetch()['c'] > 0 && (int) $docPending->fetch()['c'] === 0 && (int) $bioPending->fetch()['c'] === 0) {
        $verification = 'approved';
        $risk = 'clear';
    } elseif ((int) $docRejected->fetch()['c'] > 0 || (int) $bioFailed->fetch()['c'] > 0) {
        $verification = 'rejected';
        $risk = 'verification_review';
    } else {
        $verification = 'pending';
        $risk = 'verification_review';
    }
    db()->prepare('UPDATE users SET verification_status=?, risk_status=? WHERE id=?')->execute([$verification, $risk, $userId]);
}

function banking_review_biometric_verification(int $verificationId, string $status, string $note, array $actor): void
{
    $stmt = db()->prepare('SELECT * FROM biometric_verifications WHERE id=?');
    $stmt->execute([$verificationId]);
    $before = $stmt->fetch();
    if (!$before) {
        throw new RuntimeException('Biometric verification not found.');
    }
    $status = in_array($status, ['verified', 'failed', 'pending'], true) ? $status : 'pending';
    db()->prepare('UPDATE biometric_verifications SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?')
        ->execute([$status, $note, $actor['id'] ?? null, $verificationId]);
    $userId = (int) $before['user_id'];
    banking_recalculate_verification_status($userId);
    banking_emit_event('biometric.reviewed', ['before' => $before, 'status' => $status, 'customer_event' => $status === 'verified' ? 'kyc_approved' : 'kyc_pending', 'system_detail' => 'Biometric liveness review decision recorded.'], $actor, $userId, 'biometric_verification', $verificationId);
}

function banking_update_account_type(int $userId, string $accountType, array $actor): void
{
    $actionId = banking_service_action_start('updateAccountType', $actor, $userId, ['account_type' => $accountType]);
    $before = user_account($userId);
    if (!$before) {
        banking_service_action_finish($actionId, 'rejected', ['error' => 'Account not found.']);
        throw new RuntimeException('Account not found.');
    }
    db()->prepare('UPDATE accounts SET account_type=? WHERE user_id=?')->execute([trim($accountType), $userId]);
    $after = user_account($userId);
    banking_emit_event('account.updated', ['before' => $before, 'after' => $after], $actor, $userId, 'account', (int) $before['id']);
    banking_service_action_finish($actionId, 'completed', ['account' => $after], ['before' => $before]);
}

function banking_ai_action(string $action, array $payload, array $actor): array
{
    if (($actor['type'] ?? '') !== 'ai') {
        throw new InvalidArgumentException('AI actions require an AI actor.');
    }
    if ($action === 'create_transfer') {
        $paymentId = banking_process_ach_transfer((int) $payload['user_id'], (string) $payload['institution_name'], (string) $payload['direction'], (float) $payload['amount'], (string) ($payload['scheduled_for'] ?? date('Y-m-d')), (bool) ($payload['recurring'] ?? false), $payload['frequency'] ?? null, $actor);
        return ['status' => 'completed', 'payment_id' => $paymentId];
    }
    if ($action === 'create_notification') {
        create_customer_notification((int) $payload['user_id'], (string) $payload['title'], (string) $payload['message'], $payload['type'] ?? 'info', $payload['category'] ?? 'account', $payload['priority'] ?? 'normal');
        banking_emit_event('ai.notification_created', ['title' => $payload['title'] ?? 'Notification'], $actor, (int) $payload['user_id'], 'notification', null);
        return ['status' => 'completed'];
    }
    if ($action === 'update_balance') {
        return ['status' => 'completed', 'account' => banking_update_balance((int) $payload['user_id'], $payload['changes'] ?? [], $actor, 'ai.balance_update')];
    }
    throw new InvalidArgumentException('Unsupported AI action.');
}

function setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $settings[$row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (Throwable $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function avatar_url(?string $avatar): string
{
    return $avatar ? url('uploads/avatars/' . $avatar) : url('assets/icons/default-avatar.svg');
}

function admin_display_name(array $admin): string
{
    return trim((string) ($admin['display_name'] ?? '')) ?: trim((string) ($admin['name'] ?? 'Banking Agent')) ?: 'Banking Agent';
}

function admin_agent_id(array $admin): string
{
    $agentId = trim((string) ($admin['agent_id'] ?? ''));
    return $agentId !== '' ? $agentId : 'AGT-' . str_pad((string) ((int) ($admin['id'] ?? 0)), 5, '0', STR_PAD_LEFT);
}

function initials_from_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'DA';
}

function admin_profile_photo_url(?string $photo): ?string
{
    $photo = trim((string) $photo);
    return $photo !== '' ? url('uploads/admin_profiles/' . $photo) : null;
}

function secure_admin_profile_photo_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Agent profile photo upload failed.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime]) || ($file['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new RuntimeException('Agent photo must be JPG, PNG, or WEBP and under 4 MB.');
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || ($imageInfo[0] ?? 0) < 120 || ($imageInfo[1] ?? 0) < 120) {
        throw new RuntimeException('Agent photo must be a readable image at least 120px wide and tall.');
    }
    $dir = __DIR__ . '/../uploads/admin_profiles';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'agent_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    return move_uploaded_file($file['tmp_name'], $path) ? $name : null;
}

function onboarding_country_to_region(?string $country): string
{
    $country = strtolower(trim((string) $country));
    return match (true) {
        in_array($country, ['united states', 'usa', 'us'], true) => 'us',
        in_array($country, ['canada', 'ca'], true) => 'ca',
        in_array($country, ['united kingdom', 'uk', 'gb', 'great britain'], true) => 'uk',
        in_array($country, ['switzerland', 'ch'], true) => 'ch',
        in_array($country, ['germany', 'de', 'deutschland'], true) => 'de',
        default => '',
    };
}

function admin_onboarding_link_public_url(string $token): string
{
    $path = url('register.php?ref=' . rawurlencode($token));
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . $path;
}

function admin_onboarding_link_effective_status(array $link): string
{
    if (($link['status'] ?? '') !== 'active') {
        return (string) ($link['status'] ?? 'disabled');
    }
    if (!empty($link['used_at'])) {
        return 'used';
    }
    if (!empty($link['expires_at']) && strtotime((string) $link['expires_at']) < time()) {
        return 'expired';
    }
    return 'active';
}

function admin_onboarding_link_by_token(string $token): ?array
{
    $token = trim($token);
    if (!preg_match('/^[A-Fa-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare('SELECT l.*, a.name AS admin_name, a.display_name, a.agent_id, a.profile_photo, a.role AS admin_role, a.status AS admin_status
        FROM admin_onboarding_links l
        JOIN admins a ON a.id = l.admin_id
        WHERE l.token = ?
        LIMIT 1');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function admin_onboarding_link_validate(string $token): array
{
    $link = admin_onboarding_link_by_token($token);
    if (!$link) {
        return ['ok' => false, 'error' => 'This onboarding link is invalid or no longer available.', 'link' => null];
    }
    if (($link['admin_status'] ?? 'active') !== 'active') {
        return ['ok' => false, 'error' => 'This onboarding agent is currently unavailable.', 'link' => $link];
    }
    $status = admin_onboarding_link_effective_status($link);
    if ($status === 'expired') {
        return ['ok' => false, 'error' => 'This onboarding link has expired.', 'link' => $link];
    }
    if ($status === 'used') {
        return ['ok' => false, 'error' => 'This onboarding link has already been used.', 'link' => $link];
    }
    if ($status !== 'active') {
        return ['ok' => false, 'error' => 'This onboarding link is not active.', 'link' => $link];
    }
    return ['ok' => true, 'error' => '', 'link' => $link];
}

function admin_onboarding_create_link(int $adminId, array $data): string
{
    ensure_banking_schema();
    $clientEmail = strtolower(trim((string) ($data['client_email'] ?? '')));
    if ($clientEmail !== '' && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid client email address.');
    }
    $expiresAt = trim((string) ($data['expires_at'] ?? ''));
    if ($expiresAt !== '') {
        $timestamp = strtotime($expiresAt . ' 23:59:59');
        if ($timestamp === false) {
            throw new InvalidArgumentException('Choose a valid expiry date.');
        }
        $expiresAt = date('Y-m-d H:i:s', $timestamp);
    } else {
        $expiresAt = null;
    }
    do {
        $token = bin2hex(random_bytes(32));
        $exists = db()->prepare('SELECT COUNT(*) c FROM admin_onboarding_links WHERE token=?');
        $exists->execute([$token]);
    } while ((int) $exists->fetch()['c'] > 0);

    $stmt = db()->prepare('INSERT INTO admin_onboarding_links (token, admin_id, client_name, client_email, country, note, expires_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, "active")');
    $stmt->execute([
        $token,
        $adminId,
        trim((string) ($data['client_name'] ?? '')) ?: null,
        $clientEmail ?: null,
        trim((string) ($data['country'] ?? '')) ?: null,
        trim((string) ($data['note'] ?? '')) ?: null,
        $expiresAt,
    ]);
    return $token;
}

function verify_transaction_pin(array $user, string $pin): bool
{
    if (!preg_match('/^\d{4}$/', $pin)) {
        return false;
    }
    $hash = $user['transaction_pin_hash'] ?? null;
    if (!$hash) {
        return $pin === '1937';
    }
    return password_verify($pin, $hash);
}

function transaction_icon(array $transaction): string
{
    $type = strtolower((string) ($transaction['transaction_type'] ?? ''));
    $description = strtoupper((string) ($transaction['description'] ?? ''));

    if (str_contains($type, 'payroll') || str_contains($description, 'PAYROLL') || str_contains($description, 'DIRECT DEPOSIT')) {
        return 'fa-building-columns';
    }
    if (str_contains($description, 'STARBUCKS') || str_contains($description, 'CAFE')) {
        return 'fa-mug-hot';
    }
    if (str_contains($description, 'SHELL') || str_contains($description, 'CHEVRON') || str_contains($description, 'EXXON')) {
        return 'fa-gas-pump';
    }
    if (str_contains($description, 'TARGET') || str_contains($description, 'WALMART') || str_contains($description, 'COSTCO') || str_contains($description, 'AMAZON')) {
        return 'fa-store';
    }
    if (str_contains($description, 'ATM') || str_contains($description, 'CASH')) {
        return 'fa-money-bill-wave';
    }
    if (str_contains($type, 'transfer') || str_contains($description, 'ZELLE') || str_contains($description, 'TRANSFER')) {
        return 'fa-right-left';
    }
    if (str_contains($type, 'utility') || str_contains($description, 'COMCAST') || str_contains($description, 'VERIZON') || str_contains($description, 'ENERGY')) {
        return 'fa-bolt';
    }
    if (str_contains($type, 'subscription') || str_contains($description, 'NETFLIX') || str_contains($description, 'SPOTIFY')) {
        return 'fa-rotate';
    }
    if ((float) ($transaction['amount'] ?? 0) > 0) {
        return 'fa-arrow-down';
    }
    return 'fa-credit-card';
}

function transaction_category(array $transaction): string
{
    $type = strtolower((string) ($transaction['transaction_type'] ?? ''));
    $description = strtoupper((string) ($transaction['description'] ?? ''));

    if (str_contains($type, 'payroll') || str_contains($description, 'PAYROLL') || str_contains($description, 'DIRECT DEPOSIT')) {
        return 'Income';
    }
    if (str_contains($description, 'KROGER') || str_contains($description, 'TRADER JOE') || str_contains($description, 'WHOLE FOODS') || str_contains($description, 'COSTCO')) {
        return 'Groceries';
    }
    if (str_contains($description, 'SHELL') || str_contains($description, 'CHEVRON') || str_contains($description, 'EXXON')) {
        return 'Fuel';
    }
    if (str_contains($description, 'COMCAST') || str_contains($description, 'WIRELESS') || str_contains($description, 'ENERGY') || str_contains($description, 'WATER BILL')) {
        return 'Utilities';
    }
    if (str_contains($type, 'transfer') || str_contains($description, 'TRANSFER') || str_contains($description, 'ZELLE')) {
        return 'Transfer';
    }
    if (str_contains($description, 'ATM') || str_contains($description, 'CASH')) {
        return 'Cash';
    }
    if (str_contains($type, 'subscription') || str_contains($description, 'NETFLIX') || str_contains($description, 'SPOTIFY') || str_contains($description, 'APPLE.COM')) {
        return 'Subscription';
    }
    if ((float) ($transaction['amount'] ?? 0) > 0) {
        return 'Credit';
    }
    return 'Card';
}

function transaction_display_date(string $createdAt): string
{
    $date = new DateTimeImmutable($createdAt);
    return strtoupper($date->format('M j, Y')) . ' at ' . $date->format('g:i A');
}

function bank_seed_int(string $seed, int $min, int $max): int
{
    return $min + (crc32($seed) % (($max - $min) + 1));
}

function bank_pick(array $items, string $seed): mixed
{
    return $items[crc32($seed) % count($items)];
}

function bank_amount(string $seed, float $min, float $max): float
{
    $cents = bank_seed_int($seed, (int) round($min * 100), (int) round($max * 100));
    return round($cents / 100, 2);
}

function bank_descriptor(string $base, string $seed, bool $withId = true): string
{
    if (!$withId || bank_seed_int($seed . ':id', 1, 100) > 54) {
        return $base;
    }

    $formats = [
        $base . ' #' . bank_seed_int($seed . ':store', 1000, 9899),
        $base . ' ' . bank_seed_int($seed . ':mid', 100000, 999999),
        'POS PURCHASE ' . $base,
        $base . '*' . strtoupper(substr(dechex(crc32($seed)), 0, 5)),
    ];
    return bank_pick($formats, $seed . ':format');
}

function populate_nurse_parent_transactions(int $userId, int $weeks = 13): int
{
    $accountStmt = db()->prepare('SELECT id FROM accounts WHERE user_id=? LIMIT 1');
    $accountStmt->execute([$userId]);
    $account = $accountStmt->fetch();
    if (!$account) {
        return 0;
    }

    $start = new DateTimeImmutable('-' . ($weeks * 7) . ' days');
    $end = new DateTimeImmutable('today');
    db()->prepare('DELETE FROM transactions WHERE user_id=? AND created_at >= ?')->execute([$userId, $start->format('Y-m-d 00:00:00')]);

    $recurring = [
        [1, 'bill_pay', 'ONLINE BILL PAY MAPLE GROVE APTS', -1425.00],
        [3, 'utility', 'DUKE ENERGY WEB PAYMENT', -118.65],
        [5, 'ach_debit', 'ACH DEBIT METLIFE DENTAL PREM', -46.20],
        [8, 'ach_debit', 'ACH DEBIT BRIGHTPATH CHILDCARE', -185.00],
        [12, 'utility', 'AT&T WIRELESS PAYMENT', -92.38],
        [15, 'loan_payment', 'AUTO LOAN PAYMENT SHCU', -318.44],
        [16, 'ach_debit', 'ACH DEBIT AIDVANTAGE STUDENT LN', -286.75],
        [18, 'subscription', 'NETFLIX.COM 866-579-7172', -17.99],
        [19, 'subscription', 'SPOTIFY USA 877-778-1161', -11.99],
        [22, 'utility', 'WATER BILL PAYMENT CITY UTIL', -54.72],
        [25, 'ach_debit', 'ACH DEBIT GEICO AUTO INS', -141.36],
        [28, 'transfer', 'ONLINE TRANSFER TO SAVINGS', -150.00],
    ];
    $cardMerchants = [
        ['grocery', 'KROGER', 38, 126], ['grocery', 'TRADER JOE\'S', 22, 88], ['grocery', 'WHOLE FOODS MARKET', 31, 112],
        ['warehouse', 'COSTCO WHSE', 72, 210], ['retail', 'TARGET T-1821', 18, 96], ['retail', 'WALMART SUPERCENTER', 24, 138],
        ['retail', 'AMAZON MKTPLACE PMTS', 9, 84], ['pharmacy', 'CVS/PHARMACY', 8, 48], ['pharmacy', 'WALGREENS', 7, 55],
        ['fuel', 'SHELL SERVICE STATION', 32, 78], ['fuel', 'CHEVRON', 34, 82], ['fuel', 'EXXONMOBIL', 29, 76],
        ['coffee', 'STARBUCKS STORE', 4, 11], ['coffee', 'SQ *BLUE HARBOR CAFE', 6, 18], ['dining', 'CHIPOTLE ONLINE', 12, 34],
        ['dining', 'MCDONALD\'S', 7, 19], ['dining', 'PANERA BREAD', 10, 28], ['transport', 'UBER TRIP HELP.UBER.COM', 11, 39],
        ['transport', 'LYFT RIDE', 10, 36], ['home', 'HOME DEPOT', 21, 145], ['electronics', 'BEST BUY', 24, 180],
        ['payment', 'PAYPAL *MERCHANT', 13, 73],
    ];
    $weeklyPatterns = [
        1 => [['coffee', 72], ['grocery', 36]],
        2 => [['fuel', 48], ['coffee', 54]],
        3 => [['coffee', 66], ['retail', 28]],
        4 => [['dining', 42], ['pharmacy', 22]],
        5 => [['coffee', 70], ['dining', 58], ['transport', 30]],
        6 => [['warehouse', 50], ['retail', 44], ['home', 18]],
        7 => [['grocery', 62], ['dining', 34]],
    ];

    $insert = db()->prepare('INSERT INTO transactions (user_id, account_id, transaction_type, description, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $add = static function (string $type, string $description, float $amount, string $status, DateTimeImmutable $when) use ($insert, $userId, $account): void {
        $insert->execute([$userId, $account['id'], $type, strtoupper($description), $amount, $status, $when->format('Y-m-d H:i:s')]);
    };
    $count = 0;
    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
        $dayOfMonth = (int) $day->format('j');
        $dayOfWeek = (int) $day->format('N');
        if ($dayOfWeek <= 5 && in_array((int) $day->format('W') % 2, [0], true) && $dayOfWeek === 5) {
            $pay = 3200 + (((crc32($day->format('Y-m-d') . 'pay') % 9000) - 4500) / 100);
            $add('ach_credit_payroll', 'ACH CREDIT PAYROLL CITYCARE MED CTR', round($pay, 2), 'completed', $day->setTime(7, 18));
            $count++;
            if ((int) $day->format('j') <= 14) {
                $add('ach_debit', 'ACH DEBIT AIDVANTAGE STUDENT LN', -286.75, 'completed', $day->modify('+1 day')->setTime(8, 44));
                $count++;
            }
            if ((int) $day->format('W') % 4 === 0) {
                $add('direct_deposit', 'DIRECT DEPOSIT CITYCARE SHIFT DIFF', 216.40, 'completed', $day->setTime(7, 42));
                $count++;
            }
        }
        foreach ($recurring as $tx) {
            if ($tx[0] === $dayOfMonth) {
                $minute = bank_seed_int($day->format('Y-m-d') . $tx[2], 3, 54);
                $add($tx[1], $tx[2], (float) $tx[3], 'completed', $day->setTime(8, $minute));
                $count++;
            }
        }

        foreach ($weeklyPatterns[$dayOfWeek] as $patternIndex => [$category, $chance]) {
            $seed = $day->format('Y-m-d') . ':' . $category . ':' . $patternIndex;
            if (bank_seed_int($seed, 1, 100) > $chance) {
                continue;
            }
            $options = array_values(array_filter($cardMerchants, static fn (array $m): bool => $m[0] === $category));
            $merchant = bank_pick($options, $seed . ':merchant');
            $description = bank_descriptor($merchant[1], $seed, true);
            $amount = -bank_amount($seed . ':amount', (float) $merchant[2], (float) $merchant[3]);
            $status = $day >= $end->modify('-2 days') && bank_seed_int($seed . ':pending', 1, 100) <= 42 ? 'pending' : 'completed';
            $hour = bank_seed_int($seed . ':hour', 7, 21);
            $minute = bank_seed_int($seed . ':minute', 0, 58);
            $add('debit_card_purchase', $description, $amount, $status, $day->setTime($hour, $minute));
            $count++;
        }

        if ($dayOfWeek === 3 && bank_seed_int($day->format('Y-m-d') . ':zelle', 1, 100) <= 16) {
            $amount = bank_amount($day->format('Y-m-d') . ':zelle_amt', 18, 95);
            $signed = bank_seed_int($day->format('Y-m-d') . ':zelle_dir', 1, 100) <= 35 ? $amount : -$amount;
            $label = $signed > 0 ? 'ZELLE PAYMENT FROM JORDAN M' : 'ZELLE PAYMENT TO JORDAN M';
            $add('zelle_payment', $label, $signed, 'completed', $day->setTime(18, bank_seed_int($day->format('Y-m-d') . ':zelle_min', 4, 49)));
            $count++;
        }

        if ($dayOfWeek === 5 && bank_seed_int($day->format('Y-m-d') . ':atm', 1, 100) <= 18) {
            $amount = (float) bank_pick([-40, -60, -80, -100, -120], $day->format('Y-m-d') . ':atm_amt');
            $add('atm_withdrawal', 'ATM WITHDRAWAL SHCU BRANCH ' . bank_seed_int($day->format('Y-m-d') . ':atm_id', 1002, 8841), $amount, 'completed', $day->setTime(17, 12));
            $count++;
        }

        if ($dayOfMonth === 27) {
            $add('interest_credit', 'CREDIT INTEREST PAYMENT', bank_amount($day->format('Y-m-d') . ':interest', 1.15, 6.75), 'completed', $day->setTime(2, 5));
            $count++;
        }
    }
    $add('mobile_deposit', 'MOBILE CHECK DEPOSIT', bank_amount($end->format('Y-m-d') . ':mobile_deposit', 125, 460), 'pending', $end->setTime(15, 34));
    $count++;
    $add('fee_refund', 'ATM FEE REFUND', 3.50, 'completed', $end->modify('-3 days')->setTime(6, 12));
    $count++;

    return $count;
}

function log_admin(int $adminId, string $action, string $details, ?int $affectedUserId = null, array|string|null $before = null, array|string|null $after = null): void
{
    ensure_banking_schema();
    $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action, affected_user_id, details, before_values, after_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $adminId,
        $action,
        $affectedUserId,
        $details,
        is_array($before) ? json_encode($before) : $before,
        is_array($after) ? json_encode($after) : $after,
        $_SERVER['REMOTE_ADDR'] ?? 'local',
    ]);
}

function user_account(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM accounts WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function user_accounts(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM accounts WHERE user_id=? ORDER BY id ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function banking_create_user_account(int $userId, string $accountType, array $actor): int
{
    ensure_banking_schema();
    $allowed = ['Premium Checking', 'Premium Chequing', 'Everyday Checking', 'Current Account', 'Private Account', 'Savings Account', 'Money Market', 'Business Current Account'];
    $accountType = trim($accountType);
    if (!in_array($accountType, $allowed, true)) {
        $accountType = 'Savings Account';
    }

    $userStmt = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $primary = user_account($userId);
    $region = user_banking_region($user, $primary);
    $regionConfig = banking_region_config($region);
    $usesIban = in_array($region, ['de', 'ch'], true);
    $accountNumber = '';
    do {
        $accountNumber = (string) random_int(1000000000, 9999999999);
        $check = db()->prepare('SELECT id FROM accounts WHERE account_number=? LIMIT 1');
        $check->execute([$accountNumber]);
    } while ($check->fetch());

    $iban = null;
    $bic = null;
    $routing = $regionConfig['routing'];
    if ($usesIban) {
        $iban = $region === 'ch' ? 'CH9300762011623852957' : generated_german_iban();
        $bic = $regionConfig['routing'];
        if ($accountType === 'Premium Checking' || $accountType === 'Everyday Checking') {
            $accountType = $regionConfig['account_type'];
        }
        if ($accountType === 'Savings Account') {
            $accountType = 'Savings Account';
        }
    } elseif ($region === 'ca' && $accountType === 'Premium Checking') {
        $accountType = 'Premium Chequing';
    } elseif ($region === 'uk' && $accountType === 'Premium Checking') {
        $accountType = 'Current Account';
    }

    db()->prepare('INSERT INTO accounts (user_id, account_number, routing_number, iban, bic, account_type, available_balance, pending_balance, savings_balance) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$userId, $accountNumber, $routing, $iban, $bic, $accountType, 0, 0, 0]);
    $accountId = (int) db()->lastInsertId();
    banking_emit_event('account.created', [
        'account_id' => $accountId,
        'account_type' => $accountType,
        'system_detail' => 'Customer opened an additional account under the same login.',
    ], $actor, $userId, 'account', $accountId);
    return $accountId;
}

function banking_create_loan_application(int $userId, string $loanType, float $amount, int $termMonths, string $purpose, array $actor): int
{
    ensure_banking_schema();
    $amount = abs(banking_validate_amount($amount, false));
    if ($termMonths < 3 || $termMonths > 360) {
        throw new RuntimeException('Choose a loan term between 3 and 360 months.');
    }
    $account = user_account($userId);
    $userStmt = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $currency = user_account_currency($user ?: null, $account);
    $reference = 'LON' . random_int(100000, 999999);
    db()->prepare('INSERT INTO loan_applications (user_id, loan_type, amount, currency, term_months, purpose, reference_code) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId, trim($loanType) ?: 'Personal Loan', $amount, $currency, $termMonths, trim($purpose), $reference]);
    $loanId = (int) db()->lastInsertId();
    banking_emit_event('loan_application.created', [
        'loan_id' => $loanId,
        'reference' => $reference,
        'amount' => $amount,
        'currency' => $currency,
        'system_detail' => 'Customer submitted a loan request for review.',
    ], $actor, $userId, 'loan', $loanId);
    notify_customer_event($userId, 'transfer_pending', ['message' => 'Your loan request was submitted for review.']);
    return $loanId;
}

function seed_banking_experience(int $userId): void
{
    ensure_banking_schema();
    $account = user_account($userId);
    if (!$account) {
        return;
    }

    $recipientCount = db()->prepare('SELECT COUNT(*) c FROM payment_recipients WHERE user_id=?');
    $recipientCount->execute([$userId]);
    if ((int) $recipientCount->fetch()['c'] === 0) {
        $recipients = [
            ['Jordan Miller', 'jordan.miller@example.com', '312-555-0198', 'Jordan'],
            ['Maya Robinson', 'maya.robinson@example.com', '773-555-0146', 'Maya'],
            ['Bright Kids Care', 'billing@brightkids.example', '224-555-0172', 'Childcare'],
        ];
        $stmt = db()->prepare('INSERT INTO payment_recipients (user_id, name, email, phone, nickname, last_used_at) VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        foreach ($recipients as $i => $r) {
            $stmt->execute([$userId, $r[0], $r[1], $r[2], $r[3], 3 + ($i * 9)]);
        }
    }

    $billerCount = db()->prepare('SELECT COUNT(*) c FROM billers WHERE user_id=?');
    $billerCount->execute([$userId]);
    if ((int) $billerCount->fetch()['c'] === 0) {
        $billers = [
            ['DUKE ENERGY', 'Electricity', '****3821', 3, 1],
            ['COMCAST CABLE', 'Internet', '****1184', 11, 0],
            ['GEICO AUTO INSURANCE', 'Insurance', '****9940', 25, 1],
            ['AT&T WIRELESS', 'Phone', '****4472', 12, 0],
            ['CITY WATER SERVICES', 'Water', '****2088', 22, 0],
            ['MAPLE GROVE APTS', 'Rent', '****7401', 1, 1],
        ];
        $stmt = db()->prepare('INSERT INTO billers (user_id, name, category, account_mask, due_day, autopay, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
        foreach ($billers as $b) {
            $stmt->execute([$userId, $b[0], $b[1], $b[2], $b[3], $b[4]]);
        }
    }

    $linkedCount = db()->prepare('SELECT COUNT(*) c FROM linked_accounts WHERE user_id=?');
    $linkedCount->execute([$userId]);
    if ((int) $linkedCount->fetch()['c'] === 0) {
        db()->prepare('INSERT INTO linked_accounts (user_id, institution_name, account_type, account_mask, routing_number, verification_method, status, last_synced_at) VALUES (?, "JOINT ACCOUNT LINKED", "Joint Checking", "", NULL, "micro_deposit", "pending_verification", NULL)')
            ->execute([$userId]);
    }

    $docCount = db()->prepare('SELECT COUNT(*) c FROM documents WHERE user_id=?');
    $docCount->execute([$userId]);
    if ((int) $docCount->fetch()['c'] === 0) {
        $stmt = db()->prepare('INSERT INTO documents (user_id, document_type, title, period_label, file_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        for ($i = 0; $i < 6; $i++) {
            $period = (new DateTimeImmutable('first day of this month'))->modify('-' . $i . ' months');
            $label = $period->format('F Y');
            $stmt->execute([$userId, 'Statement', 'Checking Statement', $label, 'statement-' . $period->format('Y-m') . '.pdf', $i === 0 ? 'new' : 'available', $period->modify('last day of this month')->format('Y-m-d 09:00:00')]);
        }
        $stmt->execute([$userId, 'Tax Form', 'Year-End Interest Summary', '2025', 'tax-1099-int-2025.pdf', 'available', date('Y-m-d 09:00:00', strtotime('-2 months'))]);
        notify_customer_event($userId, 'statement_available');
    }

    $eventCount = db()->prepare('SELECT COUNT(*) c FROM security_events WHERE user_id=?');
    $eventCount->execute([$userId]);
    if ((int) $eventCount->fetch()['c'] === 0) {
        db()->prepare('INSERT INTO security_events (user_id, event_type, title, details, device, ip_address, severity, created_at) VALUES
            (?, "login", "Successful sign-in", "Recognized browser sign-in", "Windows Chrome", "192.168.1.24", "success", DATE_SUB(NOW(), INTERVAL 1 DAY)),
            (?, "device", "Trusted device added", "This device can receive security prompts.", "Windows Chrome", "192.168.1.24", "info", DATE_SUB(NOW(), INTERVAL 8 DAY)),
            (?, "alert", "Login alert enabled", "Email alerts are enabled for new sign-ins.", "Account settings", NULL, "info", DATE_SUB(NOW(), INTERVAL 15 DAY))')
            ->execute([$userId, $userId, $userId]);
    }
}

function secure_upload(array $file, string $targetDir): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime]) || ($file['size'] ?? 0) > 4 * 1024 * 1024) {
        return null;
    }
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $path = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    return move_uploaded_file($file['tmp_name'], $path) ? $name : null;
}
