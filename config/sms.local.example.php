<?php
return [
    'OTP_ENABLED' => '1',
    'SMS_OTP_ENABLED' => '1',
    'SMS_OTP_TTL_MINUTES' => '5',
    'SMS_PROVIDER' => 'twilio',

    // Twilio SMS OTP. Copy this file to sms.local.php, then paste only these 3 values.
    'TWILIO_ACCOUNT_SID' => 'paste-account-sid-here',
    'TWILIO_AUTH_TOKEN' => 'paste-auth-token-here',
    'TWILIO_FROM_NUMBER' => '+15551234567',
];
