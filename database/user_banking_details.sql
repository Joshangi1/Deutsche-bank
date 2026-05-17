CREATE TABLE IF NOT EXISTS user_banking_details (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
