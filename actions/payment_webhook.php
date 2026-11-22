<?php
/*
 * Paystack calls this address directly (not through the buyer's browser).
 * Set it in the Paystack dashboard: Settings > API Keys & Webhooks > Webhook URL
 *   https://your-site/actions/payment_webhook.php
 *
 * Paystack keeps retrying until it gets a 200, so every step here is safe to run more than once.
 */
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../core/payment_gateway.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw_body = file_get_contents('php://input');
if (!paystack_signature_is_valid($raw_body, $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '')) {
    http_response_code(401);
    exit;
}

$event = json_decode($raw_body, true);
$type = $event['event'] ?? '';
$reference = (string) ($event['data']['reference'] ?? '');

try {
    switch ($type) {
        case 'charge.success':
            if (!preg_match('/^BLB-\d+-[a-f0-9]{12}$/', $reference)) {
                break; // Not an escrow payment.
            }
            $result = confirm_escrow_payment($reference);
            if (!$result['ok'] && !empty($result['retry'])) {
                http_response_code(503); // Paystack will try again later.
                exit;
            }
            break;

        case 'transfer.success':
        case 'transfer.failed':
        case 'transfer.reversed':
            $payout_id = payout_id_from_reference($reference);
            $payout = $payout_id ? get_payout_ctr($payout_id) : null;
            if (!$payout || $payout['transfer_reference'] !== $reference) {
                break; // Unknown, or an older attempt that has been replaced.
            }
            if ($type === 'transfer.success') {
                mark_payout_sent_ctr($payout_id);
            } elseif (!mark_payout_failed_ctr($payout_id, "Paystack: $type")) {
                // Reversed after it was marked sent. The seller did not get the money.
                add_escrow_note_ctr($payout['escrow_id'], "Payout $reference was reversed by Paystack after sending. Check and resend.");
                error_log("Escrow: payout $reference reversed after being marked sent");
            }
            break;
    }
} catch (Throwable $error) {
    error_log('Paystack webhook error: ' . $error->getMessage());
    http_response_code(500); // Paystack will try again later.
    exit;
}

http_response_code(200);
