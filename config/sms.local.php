<?php
return [
    'OTP_ENABLED' => '1',
    'SMS_OTP_ENABLED' => '1',
    'SMS_OTP_TTL_MINUTES' => '5',
    'SMS_OTP_MAX_PER_HOUR' => '10',
    'SMS_PROVIDER' => 'twilio',

    // Twilio SMS OTP. Copy this file to sms.local.php, then paste only these 3 values.
    'TWILIO_ACCOUNT_SID' => 'ACeb567d69e1c7f35ee5633d8508d0bf24',
    'TWILIO_AUTH_TOKEN' => '66e22e7fe6db1def41934e9b92ccde3a',
    'TWILIO_FROM_NUMBER' => '+19793158037',
];
