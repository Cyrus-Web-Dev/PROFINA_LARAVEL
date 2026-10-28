<?php

// Konfigurasi Gmail API ada di config/gmail.php.
return [

    'postmark' => ['token' => env('POSTMARK_TOKEN')],

    'resend' => ['key' => env('RESEND_KEY')],

];
