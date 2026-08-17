<?php

require 'vendor/autoload.php';

use Resend\Resend;

$apiKey = getenv('RESEND_API');
if (!$apiKey && file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), 'RESEND_API=') === 0) {
            $apiKey = trim(substr(trim($line), 11));
            break;
        }
    }
}

if ($apiKey) {
    $resend = Resend::client($apiKey);

    $resend->emails->send([
      'from' => 'onboarding@resend.dev',
      'to' => '21u3196@student.mau.edu.ng',
      'subject' => 'Hello World',
      'html' => '<p>Congrats on sending your <strong>first email</strong>!</p>'
    ]);
}
