<?php
/**
 * MT Data Automated Acknowledgement & Notification Engine
 * 
 * Features:
 * 1. Resend API Transactional Email Receipts
 * 2. Real-time In-App Notification Center
 * 3. Automated SMS Acknowledgements (simulated telecom dispatch with carrier formatting)
 * 4. QStack Notification Server Push Microservice
 */

require_once(__DIR__ . "/../config.php");

class NotificationService {

    const QSTACK_SERVER_URL = "https://notification.qstack.com.ng/api/v1/notifications/notify";

    /**
     * Get Resend API Key from env or .env file
     */
    public static function getResendApiKey(): string {
        $key = getenv('RESEND_API');
        if ($key) return trim($key);

        // Check if .env file exists and parse it
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), 'RESEND_API=') === 0) {
                    return trim(substr(trim($line), 11));
                }
            }
        }
        return '';
    }

    /**
     * Get QStack API Key from env or .env file
     */
    public static function getQstackApiKey(): string {
        $key = getenv('QSTACK_API_KEY');
        if ($key) return trim($key);

        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), 'QSTACK_API_KEY=') === 0) {
                    return trim(substr(trim($line), 15));
                }
            }
        }
        return '';
    }

    /**
     * Sends transactional email receipt via Resend API
     */
    public static function send_resend_email(array $to, string $subject, string $htmlContent, string $textContent = ''): array {
        $apiKey = self::getResendApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'No Resend API Key configured'];
        }

        $payload = [
            'from'    => 'MT Data <onboarding@resend.dev>',
            'to'      => $to,
            'subject' => $subject,
            'html'    => $htmlContent,
            'text'    => $textContent ?: strip_tags($htmlContent)
        ];

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => $err];
        }

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $json];
        }

        return ['success' => false, 'http_code' => $httpCode, 'response' => $json];
    }

    /**
     * Dispatches external push to QStack notification microservice
     */
    public static function send_external_push(string $title, string $body, array $payload = [], string $channel = 'default'): array {
        $data = [
            'channel' => $channel,
            'title'   => $title,
            'body'    => $body,
            'payload' => $payload
        ];

        $apiKey = self::getQstackApiKey();
        $headers = ['Content-Type: application/json'];
        if ($apiKey) {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        $ch = curl_init(self::QSTACK_SERVER_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $httpCode, 'response' => json_decode($res, true)];
    }

    /**
     * Dispatch an Automated Acknowledgement for any Transaction:
     * - In-app notification
     * - Simulated SMS receipt to recipient phone
     * - Branded HTML email confirmation to user email
     * - Push notification to external socket/QStack
     */
    public static function send_transaction_acknowledgement(array $params): array {
        global $conn;

        $userId        = (int)($params['user_id'] ?? 0);
        $userEmail     = trim($params['user_email'] ?? '');
        $userFullname  = trim($params['user_fullname'] ?? 'Valued Customer');
        $txId          = (int)($params['transaction_id'] ?? 0);
        $serviceType   = trim($params['service_type'] ?? 'Transaction');
        $title         = trim($params['title'] ?? 'Transaction Successful');
        $description   = trim($params['description'] ?? '');
        $recipient     = trim($params['recipient'] ?? '');
        $amount        = (float)($params['amount'] ?? 0);
        $newBalance    = (float)($params['new_balance'] ?? 0);
        $refCode       = '#TX-' . str_pad($txId, 5, '0', STR_PAD_LEFT);
        $dateStr       = $params['date'] ?? date('Y-m-d H:i:s');

        $result = [
            'in_app'      => false,
            'email_sent'  => false,
            'sms_sent'    => false,
            'sms_message' => '',
            'push_sent'   => false
        ];

        // 1. In-App Notification
        $inAppMsg = "Your {$serviceType} transaction of ₦" . number_format($amount, 2) . " ({$description}) was successful. Ref: {$refCode}.";
        $metaJson = json_encode([
            'transaction_id' => $txId,
            'ref'            => $refCode,
            'recipient'      => $recipient,
            'amount'         => $amount,
            'new_balance'    => $newBalance,
            'service_type'   => $serviceType,
            'date'           => $dateStr
        ]);

        $notifStmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, message, service_type, channels, metadata) VALUES (?, ?, ?, ?, 'in_app,email', ?)");
        if ($notifStmt) {
            mysqli_stmt_bind_param($notifStmt, "issss", $userId, $title, $inAppMsg, $serviceType, $metaJson);
            mysqli_stmt_execute($notifStmt);
            mysqli_stmt_close($notifStmt);
            $result['in_app'] = true;
        }

        // 2. Simulated SMS Acknowledgement (for recipient phone number)
        if (!empty($recipient)) {
            $smsText = "MT-DATA Alert: Recharge of ₦" . number_format($amount, 2) . " ({$description}) on {$recipient} was SUCCESSFUL. Ref: {$refCode}. Date: " . date('d-M-y H:i', strtotime($dateStr)) . ". Thank you for choosing MT Data.";
            
            $smsStmt = mysqli_prepare($conn, "INSERT INTO sms_logs (transaction_id, user_id, phone_number, sender_id, message, status) VALUES (?, ?, ?, 'MT-DATA', ?, 'Delivered (Simulated)')");
            if ($smsStmt) {
                mysqli_stmt_bind_param($smsStmt, "iiss", $txId, $userId, $recipient, $smsText);
                mysqli_stmt_execute($smsStmt);
                mysqli_stmt_close($smsStmt);
                $result['sms_sent'] = true;
                $result['sms_message'] = $smsText;
            }
        }

        // 3. Branded HTML Email Receipt (Resend)
        if (!empty($userEmail)) {
            $emailSubject = "Payment Receipt: {$title} [{$refCode}]";
            $htmlBody = self::buildEmailTemplate([
                'fullname'     => $userFullname,
                'title'        => $title,
                'refCode'      => $refCode,
                'serviceType'  => $serviceType,
                'description'  => $description,
                'recipient'    => $recipient,
                'amount'       => $amount,
                'newBalance'   => $newBalance,
                'dateStr'      => $dateStr,
                'smsMessage'   => $result['sms_message'] ?? ''
            ]);

            $emailRes = self::send_resend_email([$userEmail], $emailSubject, $htmlBody, $inAppMsg);
            $result['email_sent'] = $emailRes['success'] ?? false;
            $result['email_details'] = $emailRes;
        }

        // 4. External Push Notification (QStack)
        $pushRes = self::send_external_push(
            $title,
            $inAppMsg,
            [
                'transaction_id' => $txId,
                'ref'            => $refCode,
                'user_id'        => $userId,
                'recipient'      => $recipient,
                'amount'         => $amount
            ]
        );
        $result['push_sent'] = ($pushRes['status'] >= 200 && $pushRes['status'] < 300);

        return $result;
    }

    /**
     * Dispatch an Automated Acknowledgement for Wallet Funding:
     */
    public static function send_funding_acknowledgement(array $params): array {
        global $conn;

        $userId        = (int)($params['user_id'] ?? 0);
        $userEmail     = trim($params['user_email'] ?? '');
        $userFullname  = trim($params['user_fullname'] ?? 'Valued Customer');
        $fundingId     = (int)($params['funding_id'] ?? 0);
        $amount        = (float)($params['amount'] ?? 0);
        $paymentMethod = trim($params['payment_method'] ?? 'Card Payment');
        $oldBalance    = (float)($params['old_balance'] ?? 0);
        $newBalance    = (float)($params['new_balance'] ?? 0);
        $reference     = trim($params['reference'] ?? ('#FUND-' . str_pad($fundingId, 5, '0', STR_PAD_LEFT)));
        $dateStr       = $params['date'] ?? date('Y-m-d H:i:s');

        $result = [
            'in_app'      => false,
            'email_sent'  => false,
            'sms_sent'    => false,
            'sms_message' => '',
            'push_sent'   => false
        ];

        // 1. In-App Notification
        $title = "Wallet Credited: ₦" . number_format($amount, 2);
        $inAppMsg = "Your MT Data wallet has been credited with ₦" . number_format($amount, 2) . " via {$paymentMethod}. Ref: {$reference}. New Balance: ₦" . number_format($newBalance, 2);
        $metaJson = json_encode([
            'funding_id'     => $fundingId,
            'reference'      => $reference,
            'amount'         => $amount,
            'payment_method' => $paymentMethod,
            'old_balance'    => $oldBalance,
            'new_balance'    => $newBalance,
            'service_type'   => 'Wallet Funding',
            'date'           => $dateStr
        ]);

        $notifStmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, message, service_type, channels, metadata) VALUES (?, ?, ?, 'Wallet Funding', 'in_app,email', ?)");
        if ($notifStmt) {
            mysqli_stmt_bind_param($notifStmt, "isss", $userId, $title, $inAppMsg, $metaJson);
            mysqli_stmt_execute($notifStmt);
            mysqli_stmt_close($notifStmt);
            $result['in_app'] = true;
        }

        // 2. Simulated SMS Acknowledgement to customer phone
        $userObj = get_user($userId);
        $userPhone = $userObj['phone'] ?? '';
        if (!empty($userPhone)) {
            $smsText = "MT-DATA Alert: Credit of ₦" . number_format($amount, 2) . " via {$paymentMethod} was SUCCESSFUL. Ref: {$reference}. New Bal: ₦" . number_format($newBalance, 2) . ". Thank you!";
            
            $smsStmt = mysqli_prepare($conn, "INSERT INTO sms_logs (transaction_id, user_id, phone_number, sender_id, message, status) VALUES (NULL, ?, ?, 'MT-DATA', ?, 'Delivered (Simulated)')");
            if ($smsStmt) {
                mysqli_stmt_bind_param($smsStmt, "iss", $userId, $userPhone, $smsText);
                mysqli_stmt_execute($smsStmt);
                mysqli_stmt_close($smsStmt);
                $result['sms_sent'] = true;
                $result['sms_message'] = $smsText;
            }
        }

        // 3. Branded HTML Email Receipt (Resend)
        if (!empty($userEmail)) {
            $emailSubject = "Wallet Credit Receipt: ₦" . number_format($amount, 2) . " [{$reference}]";
            $htmlBody = self::buildEmailTemplate([
                'fullname'     => $userFullname,
                'title'        => 'Wallet Funding Successful',
                'refCode'      => $reference,
                'serviceType'  => 'Wallet Funding',
                'description'  => "Wallet top-up via {$paymentMethod}",
                'recipient'    => $userPhone ?: 'My Account',
                'amount'       => $amount,
                'newBalance'   => $newBalance,
                'dateStr'      => $dateStr,
                'smsMessage'   => $result['sms_message'] ?? ''
            ]);

            $emailRes = self::send_resend_email([$userEmail], $emailSubject, $htmlBody, $inAppMsg);
            $result['email_sent'] = $emailRes['success'] ?? false;
            $result['email_details'] = $emailRes;
        }

        // 4. External Push Notification (QStack)
        $pushRes = self::send_external_push(
            $title,
            $inAppMsg,
            [
                'funding_id'     => $fundingId,
                'reference'      => $reference,
                'user_id'        => $userId,
                'amount'         => $amount
            ]
        );
        $result['push_sent'] = ($pushRes['status'] >= 200 && $pushRes['status'] < 300);

        return $result;
    }

    /**
     * Dispatch an Automated Acknowledgement for New User Registration:
     * - In-app notification
     * - Welcome HTML email via Resend
     * - Simulated Welcome SMS
     */
    public static function send_welcome_acknowledgement(int $userId, string $userEmail, string $fullname, string $phone = ''): array {
        global $conn;

        $result = [
            'in_app'     => false,
            'email_sent' => false,
            'sms_sent'   => false,
            'push_sent'  => false
        ];

        $title = "Welcome to MT Data! 🚀";
        $inAppMsg = "Hello {$fullname}, your account has been successfully created. Fund your wallet to start instant data purchases and airtime top-ups with Face ID biometric authentication.";
        $metaJson = json_encode([
            'user_id'  => $userId,
            'fullname' => $fullname,
            'email'    => $userEmail,
            'phone'    => $phone,
            'event'    => 'user_registered',
            'date'     => date('Y-m-d H:i:s')
        ]);

        // 1. In-App Notification
        $notifStmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, message, service_type, channels, metadata) VALUES (?, ?, ?, 'Account', 'in_app,email,sms', ?)");
        if ($notifStmt) {
            mysqli_stmt_bind_param($notifStmt, "isss", $userId, $title, $inAppMsg, $metaJson);
            mysqli_stmt_execute($notifStmt);
            mysqli_stmt_close($notifStmt);
            $result['in_app'] = true;
        }

        // 2. Simulated SMS to user phone
        if (!empty($phone)) {
            $smsText = "Welcome to MT Data, {$fullname}! Your account is active. Top-up airtime and data with lightning speed and 1-click Face ID. Ref: #ACC-" . str_pad($userId, 5, '0', STR_PAD_LEFT);
            $smsStmt = mysqli_prepare($conn, "INSERT INTO sms_logs (transaction_id, user_id, phone_number, sender_id, message, status) VALUES (NULL, ?, ?, 'MT-DATA', ?, 'Delivered (Simulated)')");
            if ($smsStmt) {
                mysqli_stmt_bind_param($smsStmt, "iss", $userId, $phone, $smsText);
                mysqli_stmt_execute($smsStmt);
                mysqli_stmt_close($smsStmt);
                $result['sms_sent'] = true;
            }
        }

        // 3. Branded HTML Welcome Email via Resend
        if (!empty($userEmail)) {
            $emailSubject = "Welcome to MT Data – Fast Data & Airtime Top-Up";
            $htmlBody = self::buildWelcomeEmailTemplate($fullname, $userEmail, $userId);
            $emailRes = self::send_resend_email([$userEmail], $emailSubject, $htmlBody, $inAppMsg);
            $result['email_sent'] = $emailRes['success'] ?? false;
            $result['email_details'] = $emailRes;
        }

        // 4. External Push Notification
        $pushRes = self::send_external_push(
            $title,
            $inAppMsg,
            ['user_id' => $userId, 'email' => $userEmail, 'event' => 'registration']
        );
        $result['push_sent'] = ($pushRes['status'] >= 200 && $pushRes['status'] < 300);

        return $result;
    }

    /**
     * Builds professional HTML email template for welcome registration
     */
    public static function buildWelcomeEmailTemplate(string $fullname, string $email, int $userId): string {
        $accNum = "#ACC-" . str_pad($userId, 5, '0', STR_PAD_LEFT);
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Welcome to MT Data</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 16px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 580px; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                            
                            <!-- Header Bar -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 36px 30px; text-align: center;">
                                    <div style="display: inline-block; width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 16px; line-height: 56px; font-size: 28px; color: #ffffff; margin-bottom: 12px;">⚡</div>
                                    <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">Welcome to MT Data</h1>
                                    <p style="margin: 6px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">Your High-Speed Telecommunications & Data Portal</p>
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style="padding: 36px 30px;">
                                    <h2 style="margin: 0 0 12px 0; color: #0f172a; font-size: 20px; font-weight: 800;">
                                        Hi ' . htmlspecialchars($fullname) . ', 👋
                                    </h2>
                                    <p style="margin: 0 0 20px 0; color: #475569; font-size: 15px; line-height: 1.6;">
                                        Thank you for joining <strong>MT Data</strong>. Your account has been initialized and is ready for immediate transactions.
                                    </p>

                                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 24px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; padding-bottom: 6px;">Account ID:</td>
                                                <td style="color: #0f172a; font-weight: 700; text-align: right; font-size: 13px; padding-bottom: 6px;">' . $accNum . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; padding-bottom: 6px;">Email:</td>
                                                <td style="color: #0f172a; font-weight: 600; text-align: right; font-size: 13px; padding-bottom: 6px;">' . htmlspecialchars($email) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px;">Security Feature:</td>
                                                <td style="color: #10b981; font-weight: 700; text-align: right; font-size: 13px;">1-Click Face ID Enabled</td>
                                            </tr>
                                        </table>
                                    </div>

                                    <h3 style="margin: 0 0 12px 0; color: #0f172a; font-size: 15px; font-weight: 700;">What you can do with MT Data:</h3>
                                    <ul style="margin: 0 0 24px 0; padding-left: 20px; color: #475569; font-size: 14px; line-height: 1.8;">
                                        <li><strong>Cheap Data Bundles:</strong> MTN, Airtel, Glo, and 9mobile SME & Corporate Gifting.</li>
                                        <li><strong>Instant Airtime:</strong> VTU top-ups with instant bonus receipts.</li>
                                        <li><strong>Cable Subscriptions:</strong> Instant renewal for DSTV, GOTV, and Startimes.</li>
                                        <li><strong>Face ID Biometrics:</strong> Login seamlessly with your webcam without typing passwords.</li>
                                    </ul>

                                    <div style="text-align: center; margin: 30px 0 10px 0;">
                                        <a href="http://localhost:8080/user/dashboard.php" style="display: inline-block; padding: 14px 32px; background: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 14px; border-radius: 12px; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);">
                                            Go to Dashboard & Fund Wallet &rarr;
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                                    <p style="margin: 0; color: #94a3b8; font-size: 12px;">
                                        MT Data &bull; Automated Telecommunication & Data Vending System<br>
                                        ID Number: <strong style="color: #64748b;">CSC/21U/3196</strong>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }

    /**
     * Builds professional HTML email template for receipts
     */
    public static function buildEmailTemplate(array $d): string {
        $amountFormatted = "₦" . number_format($d['amount'], 2);
        $balFormatted = "₦" . number_format($d['newBalance'], 2);
        $dateFormatted = date('M d, Y h:i A', strtotime($d['dateStr']));

        $recipientRow = "";
        if (!empty($d['recipient'])) {
            $recipientRow = '
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 12px 0; color: #64748b; font-size: 14px;">Beneficiary / Recipient:</td>
                <td style="padding: 12px 0; color: #0f172a; font-weight: 700; text-align: right; font-size: 14px;">' . htmlspecialchars($d['recipient']) . '</td>
            </tr>';
        }

        $smsBox = "";
        if (!empty($d['smsMessage'])) {
            $smsBox = '
            <div style="margin-top: 24px; padding: 16px; background-color: #f8fafc; border-left: 4px solid #4f46e5; border-radius: 8px;">
                <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.5px;">
                    📱 Automated SMS Acknowledgement Sent
                </p>
                <p style="margin: 0; font-size: 13px; color: #334155; font-family: monospace;">' . htmlspecialchars($d['smsMessage']) . '</p>
            </div>';
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Transaction Receipt</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 16px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 580px; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                            
                            <!-- Header Bar -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 32px 30px; text-align: center;">
                                    <div style="display: inline-block; width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 14px; line-height: 50px; font-size: 24px; color: #ffffff; margin-bottom: 12px;">⚡</div>
                                    <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">MT Data</h1>
                                    <p style="margin: 4px 0 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">Instant Top-Up & Vending Acknowledgement</p>
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style="padding: 32px 30px;">
                                    <div style="text-align: center; margin-bottom: 24px;">
                                        <span style="display: inline-block; padding: 6px 14px; background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase;">
                                            ✓ ' . htmlspecialchars($d['title']) . '
                                        </span>
                                        <div style="margin-top: 14px; font-size: 34px; font-weight: 800; color: #0f172a;">' . $amountFormatted . '</div>
                                        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Ref: <strong style="color: #334155;">' . htmlspecialchars($d['refCode']) . '</strong></p>
                                    </div>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #f1f5f9; margin-top: 20px;">
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 12px 0; color: #64748b; font-size: 14px;">Customer:</td>
                                            <td style="padding: 12px 0; color: #0f172a; font-weight: 700; text-align: right; font-size: 14px;">' . htmlspecialchars($d['fullname']) . '</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 12px 0; color: #64748b; font-size: 14px;">Service Type:</td>
                                            <td style="padding: 12px 0; color: #0f172a; font-weight: 700; text-align: right; font-size: 14px;">' . htmlspecialchars($d['serviceType']) . '</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 12px 0; color: #64748b; font-size: 14px;">Description / Package:</td>
                                            <td style="padding: 12px 0; color: #0f172a; font-weight: 700; text-align: right; font-size: 14px;">' . htmlspecialchars($d['description']) . '</td>
                                        </tr>
                                        ' . $recipientRow . '
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 12px 0; color: #64748b; font-size: 14px;">Date & Time:</td>
                                            <td style="padding: 12px 0; color: #0f172a; font-weight: 600; text-align: right; font-size: 14px;">' . $dateFormatted . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 14px 0; color: #475569; font-weight: 700; font-size: 14px;">Updated Wallet Balance:</td>
                                            <td style="padding: 14px 0; color: #10b981; font-weight: 800; text-align: right; font-size: 16px;">' . $balFormatted . '</td>
                                        </tr>
                                    </table>

                                    ' . $smsBox . '

                                    <div style="margin-top: 30px; text-align: center;">
                                        <a href="http://localhost:8080/user/dashboard.php" style="display: inline-block; padding: 13px 28px; background: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 14px; border-radius: 12px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">
                                            Open Dashboard
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                                    <p style="margin: 0; color: #94a3b8; font-size: 12px;">
                                        MT Data &bull; Automated Telecommunication & Data Vending System<br>
                                        ID Number: <strong style="color: #64748b;">CSC/21U/3196</strong>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }

    /**
     * Get user notifications
     */
    public static function get_user_notifications(int $userId, int $limit = 15): array {
        global $conn;
        $stmt = mysqli_prepare($conn, "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?");
        mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $list[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $list;
    }

    /**
     * Get unread notifications count
     */
    public static function get_unread_count(int $userId): int {
        global $conn;
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        return (int)($row['c'] ?? 0);
    }

    /**
     * Mark single notification as read
     */
    public static function mark_as_read(int $notifId, int $userId): bool {
        global $conn;
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $notifId, $userId);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * Mark all as read
     */
    public static function mark_all_as_read(int $userId): bool {
        global $conn;
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * Get user SMS logs
     */
    public static function get_user_sms_logs(int $userId, int $limit = 15): array {
        global $conn;
        $stmt = mysqli_prepare($conn, "SELECT * FROM sms_logs WHERE user_id = ? ORDER BY id DESC LIMIT ?");
        mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $list[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $list;
    }
}
