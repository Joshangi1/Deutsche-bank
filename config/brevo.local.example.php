<?php
return [
    'OTP_ENABLED' => '0',
    'SMS_OTP_ENABLED' => '0',

    // Twilio SMS OTP. Prefer API Key credentials; account Auth Token also works.
    'SMS_PROVIDER' => 'twilio',
    'TWILIO_ACCOUNT_SID' => 'paste-account-sid-here',
    'TWILIO_API_KEY_SID' => 'paste-api-key-sid-here',
    'TWILIO_API_KEY_SECRET' => 'paste-api-key-secret-here',
    'TWILIO_AUTH_TOKEN' => '',
    'TWILIO_FROM_NUMBER' => '+15551234567',
    'TWILIO_MESSAGING_SERVICE_SID' => '',

    // Generic/Brevo-compatible SMS API fallback.
    'SMS_API_KEY' => 'paste-send-inc-or-sms-api-key-here',
    'SMS_API_SECRET' => 'paste-send-inc-or-sms-api-secret-here',
    'SMS_SENDER_ID' => 'Deutsche Bank',
    'SMS_BASE_URL' => 'https://your-sms-provider.example/send',
    // Optional aliases also supported: SENDINC_API_KEY, SENDINC_API_SECRET, SENDINC_BASE_URL.
    'BREVO_FROM_NAME' => 'Deutsche Bank',
];
