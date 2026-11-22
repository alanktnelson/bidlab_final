<?php
/*
 * Talks to Paystack. Every function returns either
 *   ['ok' => true, ...data]   or   ['ok' => false, 'error' => 'message', 'network' => bool]
 * 'network' => true means Paystack could not be reached, so the result is UNKNOWN (it may have worked).
 *
 * Amounts are in pesewas (minor units), which is what Paystack expects for GHS.
 */
require_once __DIR__ . '/escrow_config.php';
require_once __DIR__ . '/../controllers/escrow_controller.php';

function paystack_request($method, $path, array $body = null)
{
    if (PAYSTACK_SECRET_KEY === '') {
        return ['ok' => false, 'error' => 'Payments are not set up yet (missing PAYSTACK_SECRET_KEY).', 'network' => false];
    }

    $curl = curl_init(PAYSTACK_API_BASE . $path);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => PAYSTACK_TIMEOUT_SECONDS,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($raw === false) {
        error_log("Paystack $method $path failed: $curl_error");
        return ['ok' => false, 'error' => 'Could not reach the payment provider. Please try again.', 'network' => true];
    }

    $response = json_decode($raw, true);
    if (!is_array($response)) {
        error_log("Paystack $method $path returned something that is not JSON: " . substr($raw, 0, 300));
        return ['ok' => false, 'error' => 'The payment provider sent an unexpected reply.', 'network' => true];
    }
    if (empty($response['status'])) {
        return ['ok' => false, 'error' => $response['message'] ?? 'The payment provider refused the request.', 'network' => false];
    }
    return ['ok' => true, 'data' => $response['data'] ?? []];
}

/* ---------- Taking payment ---------- */

/** Start a checkout. Returns authorization_url to send the buyer to. */
function paystack_initialize($email, $amount_minor, $reference, $callback_url, array $metadata = [])
{
    $result = paystack_request('POST', '/transaction/initialize', [
        'email' => $email,
        'amount' => (int) $amount_minor,
        'currency' => ESCROW_CURRENCY,
        'reference' => $reference,
        'callback_url' => $callback_url,
        'metadata' => $metadata,
    ]);
    if (!$result['ok']) {
        return $result;
    }
    return ['ok' => true, 'authorization_url' => $result['data']['authorization_url']];
}

/**
 * Ask Paystack whether a payment really succeeded. Never trust the browser or the webhook body alone.
 * Returns paid (bool), amount_minor and currency.
 */
function paystack_verify($reference)
{
    $result = paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
    if (!$result['ok']) {
        return $result;
    }
    $data = $result['data'];
    return [
        'ok' => true,
        'paid' => ($data['status'] ?? '') === 'success',
        'status' => $data['status'] ?? 'unknown',
        'amount_minor' => (int) ($data['amount'] ?? 0),
        'currency' => $data['currency'] ?? '',
        'reference' => $data['reference'] ?? $reference,
    ];
}

/** Refund a payment in full (or $amount_minor of it). Returns the refund ID as refund_reference. */
function paystack_refund($payment_reference, $amount_minor = null)
{
    $body = ['transaction' => $payment_reference];
    if ($amount_minor !== null) {
        $body['amount'] = (int) $amount_minor;
    }
    $result = paystack_request('POST', '/refund', $body);
    if (!$result['ok']) {
        return $result;
    }
    return ['ok' => true, 'refund_reference' => (string) ($result['data']['id'] ?? $payment_reference)];
}

/* ---------- Paying sellers ---------- */

/** Banks Paystack can pay in Ghana, for the payout settings page. Returns banks => [code => name]. */
function paystack_list_banks()
{
    $result = paystack_request('GET', '/bank?currency=GHS&type=ghipss');
    if (!$result['ok']) {
        return $result;
    }
    $banks = [];
    foreach ($result['data'] as $bank) {
        if (!empty($bank['active'])) {
            $banks[$bank['code']] = $bank['name'];
        }
    }
    return ['ok' => true, 'banks' => $banks];
}

/**
 * Register a seller's account with Paystack. For mobile money, $account['provider'] is MTN, Telecel or AirtelTigo.
 * For a bank, $account['provider'] must be the bank code from paystack_list_banks().
 */
function paystack_create_recipient(array $account)
{
    if ($account['method'] === 'mobile_money') {
        $type = 'mobile_money';
        $bank_code = PAYSTACK_MOMO_CODES[$account['provider']] ?? null;
    } else {
        $type = 'ghipss';
        $bank_code = $account['provider'];
    }
    if (!$bank_code) {
        return ['ok' => false, 'error' => 'Unknown mobile money network.', 'network' => false];
    }

    $result = paystack_request('POST', '/transferrecipient', [
        'type' => $type,
        'name' => $account['account_name'],
        'account_number' => $account['account_number'],
        'bank_code' => $bank_code,
        'currency' => ESCROW_CURRENCY,
    ]);
    if (!$result['ok']) {
        return $result;
    }
    return ['ok' => true, 'recipient_code' => $result['data']['recipient_code']];
}

/**
 * Send money from your Paystack balance. 'state' is Paystack's transfer status:
 * success = done, pending/received = wait for the webhook, otp = transfer OTP is switched on (turn it off).
 */
function paystack_transfer($amount_minor, $recipient_code, $reference, $reason)
{
    $result = paystack_request('POST', '/transfer', [
        'source' => 'balance',
        'amount' => (int) $amount_minor,
        'recipient' => $recipient_code,
        'reference' => $reference,
        'reason' => $reason,
        'currency' => ESCROW_CURRENCY,
    ]);
    if (!$result['ok']) {
        return $result;
    }
    return ['ok' => true, 'state' => $result['data']['status'] ?? 'pending'];
}

/* ---------- Webhooks ---------- */

function paystack_signature_is_valid($raw_body, $signature)
{
    if (PAYSTACK_SECRET_KEY === '' || !is_string($signature) || $signature === '') {
        return false;
    }
    return hash_equals(hash_hmac('sha512', $raw_body, PAYSTACK_SECRET_KEY), $signature);
}

/* =====================================================================
 * Escrow steps that need Paystack. Used by the actions and the scheduled job.
 * ===================================================================== */

/**
 * Check a payment with Paystack and record it on the escrow.
 * Used by both payment_callback.php and payment_webhook.php; safe to run twice.
 * If money arrived for an order that can't take it (late or wrong amount), refunds it.
 *
 * Returns ['ok' => true, 'escrow_id' => ...] or ['ok' => false, 'error' => ..., 'escrow_id' => ...|null].
 */
function confirm_escrow_payment($reference)
{
    $check = paystack_verify($reference);
    if (!$check['ok']) {
        return ['ok' => false, 'error' => $check['error'], 'escrow_id' => null, 'retry' => $check['network']];
    }
    if (!$check['paid']) {
        return ['ok' => false, 'error' => 'The payment was not completed.', 'escrow_id' => null, 'retry' => false];
    }

    $result = record_payment_ctr($reference, $check['amount_minor'], $check['currency']);
    if (!empty($result['ok'])) {
        return ['ok' => true, 'escrow_id' => $result['escrow_id']];
    }

    $escrow_id = $result['escrow_id'] ?? null;
    if (!empty($result['refund_needed'])) {
        $refund = paystack_refund($reference);
        $note = $refund['ok']
            ? "Payment $reference refunded automatically (refund {$refund['refund_reference']})"
            : "Payment $reference NEEDS A MANUAL REFUND: " . $refund['error'];
        if ($escrow_id) {
            add_escrow_note_ctr($escrow_id, $note);
        }
        error_log("Escrow: $note");
        $message = $result['errors']['general'] . ($refund['ok'] ? ' Your money is being refunded.' : ' We will refund you.');
        return ['ok' => false, 'error' => $message, 'escrow_id' => $escrow_id, 'retry' => false];
    }

    return ['ok' => false, 'error' => $result['errors']['general'] ?? 'The payment could not be recorded.', 'escrow_id' => $escrow_id, 'retry' => false];
}

/**
 * Send one queued payout to the seller. Claims it first so it can never be sent twice.
 * Returns a short description of what happened, for the job log.
 */
function send_escrow_payout(array $payout)
{
    $payout_id = (int) $payout['payout_id'];
    $reference = claim_payout_ctr($payout_id);
    if ($reference === null) {
        return "payout $payout_id: already being handled";
    }

    $recipient_code = $payout['recipient_code'];
    if (!$recipient_code) {
        $recipient = paystack_create_recipient($payout);
        if (!$recipient['ok']) {
            mark_payout_failed_ctr($payout_id, 'Seller account rejected: ' . $recipient['error']);
            return "payout $payout_id: failed, seller account rejected ({$recipient['error']})";
        }
        $recipient_code = $recipient['recipient_code'];
        save_recipient_code_ctr($payout['seller_id'], $recipient_code);
    }

    $transfer = paystack_transfer($payout['amount_minor'], $recipient_code, $reference, 'Bidlab sale, order #' . $payout['escrow_id']);

    if (!$transfer['ok'] && $transfer['network']) {
        // Unknown result. Leave it 'processing'; the webhook will settle it, or an admin checks it.
        return "payout $payout_id: no answer from Paystack, left as processing ($reference)";
    }
    if (!$transfer['ok']) {
        mark_payout_failed_ctr($payout_id, $transfer['error']);
        return "payout $payout_id: failed ({$transfer['error']})";
    }
    if ($transfer['state'] === 'success') {
        mark_payout_sent_ctr($payout_id);
        return "payout $payout_id: sent ($reference)";
    }
    if ($transfer['state'] === 'otp') {
        mark_payout_failed_ctr($payout_id, 'Paystack wants an OTP. Turn off transfer OTP in the Paystack dashboard.');
        return "payout $payout_id: failed, transfer OTP is switched on";
    }
    return "payout $payout_id: accepted by Paystack, waiting for confirmation ($reference)";
}
