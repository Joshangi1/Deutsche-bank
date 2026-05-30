<?php
return [
    'OTP_ENABLED' => '1',
    'SMS_OTP_ENABLED' => '1',
    'SMS_OTP_TTL_MINUTES' => '5',

    // Twilio SMS OTP. Prefer API Key credentials; account Auth Token also works.
    'SMS_PROVIDER' => 'twilio',
    'TWILIO_ACCOUNT_SID' => 'ACeb567d69e1c7f35ee5633d8508d0bf24',
    'TWILIO_API_KEY_SID' => 'SKaad8b2a90214bac2e54b048d8e400016',
    'TWILIO_API_KEY_SECRET' => 'paste-api-key-secret-here',
    'TWILIO_AUTH_TOKEN' => '4ba0150cb44a214fe2869e94af7afc8f',
    'TWILIO_FROM_NUMBER' => '+19793158037',
    'TWILIO_MESSAGING_SERVICE_SID' => '',

    // Generic/Brevo-compatible SMS API fallback.
    'SMS_API_KEY' => 'paste-send-inc-or-sms-api-key-here',
    'SMS_API_SECRET' => 'paste-send-inc-or-sms-api-secret-here',
    'SMS_SENDER_ID' => 'Deutsche Bank',
    'SMS_BASE_URL' => 'https://your-sms-provider.example/send',
    // Optional aliases also supported: SENDINC_API_KEY, SENDINC_API_SECRET, SENDINC_BASE_URL.
    'SMS_FROM_NAME' => 'Deutsche Bank',
    'BREVO_FROM_NAME' => 'Deutsche Bank',
];
