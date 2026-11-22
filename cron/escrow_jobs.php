<?php
// Scheduled escrow work. Run every 5 minutes from the server's crontab, e.g.:
//   */5 * * * * php /path/to/bidlab/cron/escrow_jobs.php >> /path/to/logs/escrow_jobs.log 2>&1
//
// 1. Cancels orders the buyer did not pay for in time.
// 2. Releases money for shipped orders the buyer never confirmed or disputed.
// 3. Sends queued payouts to sellers through Paystack.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../core/payment_gateway.php';

$stamp = gmdate('Y-m-d H:i:s') . ' UTC';

$steps = [
    'cancel unpaid' => function () {
        $cancelled = cancel_unpaid_escrows_ctr();
        return count($cancelled) . ' cancelled' . ($cancelled ? ' (orders ' . implode(', ', array_column($cancelled, 'escrow_id')) . ')' : '');
    },
    'auto-release' => function () {
        $released = release_due_escrows_ctr();
        return count($released) . ' released' . ($released ? ' (orders ' . implode(', ', array_column($released, 'escrow_id')) . ')' : '');
    },
    'payouts' => function () {
        $lines = [];
        foreach (get_pending_payouts_ctr() as $payout) {
            $lines[] = send_escrow_payout($payout);
        }
        $stuck = count(get_stuck_payouts_ctr());
        if ($stuck) {
            $lines[] = "$stuck payout(s) waiting on Paystack";
        }
        return $lines ? implode('; ', $lines) : 'nothing to send';
    },
];

foreach ($steps as $name => $step) {
    try {
        echo "[$stamp] $name: " . $step() . "\n";
    } catch (Throwable $error) {
        // One failing step should not stop the others.
        echo "[$stamp] $name: ERROR " . $error->getMessage() . "\n";
        error_log("Escrow job '$name' failed: " . $error->getMessage());
    }
}
