<?php
/**
 * MT Data Notification Services (PHP Native Implementation)
 * Replaces legacy notification_services.py
 */

require_once(__DIR__ . "/includes/NotificationService.php");

/**
 * Functional helper for sending actions/notifications
 */
function dispatch_action_notification_and_email($user_id, $title, $message, $recipient_email = null, $detail_dict = []) {
    return NotificationService::send_transaction_acknowledgement([
        'user_id'       => $user_id,
        'user_email'    => $recipient_email,
        'title'         => $title,
        'description'   => $message,
        'service_type'  => $detail_dict['service_type'] ?? 'System',
        'recipient'     => $detail_dict['recipient'] ?? '',
        'amount'        => $detail_dict['amount'] ?? 0,
        'new_balance'   => $detail_dict['new_balance'] ?? 0,
        'date'          => date('Y-m-d H:i:s')
    ]);
}
