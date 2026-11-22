<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../layout/escrow_helpers.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$status = $_GET['status'] ?? 'failed';
$page = admin_page();
$result = admin_search_payouts_ctr($status, $page);
$status = in_array($status, ['', 'pending', 'processing', 'sent', 'failed'], true) ? $status : '';

$chips = ['failed' => 'Failed', 'processing' => 'Waiting on Paystack', 'pending' => 'Queued', 'sent' => 'Sent', '' => 'All'];
$labels = ['pending' => 'Queued', 'processing' => 'Sending', 'sent' => 'Sent', 'failed' => 'Failed'];
$classes = ['pending' => 'status wait', 'processing' => 'status wait', 'sent' => 'status good', 'failed' => 'status bad'];
$counts = $result['counts'] + ['' => array_sum($result['counts'])];

$page_title = 'Payouts | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'payouts';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Payouts</h1>
        <p class="muted">Money owed to sellers after a buyer confirms. The scheduled job sends queued payouts every few minutes.</p>
    </div>
</div>

<div class="admin-chips">
    <?php foreach ($chips as $value => $label): ?>
        <a href="?<?php echo e(http_build_query(['status' => $value])); ?>"<?php echo $status === $value ? ' aria-current="true"' : ''; ?>><?php echo e($label); ?> (<?php echo (int) ($counts[$value] ?? 0); ?>)</a>
    <?php endforeach; ?>
</div>

<?php if ($status === 'processing'): ?>
    <p class="notice-bar">Paystack has not confirmed these. Look each reference up in the Paystack dashboard before doing anything. If it was paid, mark it paid here. Do <strong>not</strong> send it again by hand.</p>
<?php elseif ($status === 'failed'): ?>
    <p class="notice-bar">Fix the cause first (often wrong seller details, or not enough money in your Paystack balance), then retry.</p>
<?php endif; ?>

<section class="panel">
    <?php if (!$result['rows']): ?>
        <p class="muted">No payouts here.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Payout</th><th>Seller</th><th>Paid to</th><th class="num">Amount</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($result['rows'] as $payout): $pid = (int) $payout['payout_id']; ?>
                    <tr>
                        <td>#<?php echo $pid; ?><br><a href="<?php echo url('views/admin/order.php?id=' . (int) $payout['escrow_id']); ?>">Order #<?php echo (int) $payout['escrow_id']; ?></a></td>
                        <td><a href="<?php echo url('views/admin/user.php?id=' . (int) $payout['seller_id']); ?>"><?php echo e($payout['seller_name'] ?? '#' . $payout['seller_id']); ?></a><br><small><?php echo e($payout['seller_email']); ?></small></td>
                        <td>
                            <?php if ($payout['method']): ?>
                                <?php echo $payout['method'] === 'bank' ? 'Bank' : 'MoMo'; ?> · <?php echo e($payout['provider']); ?><br>
                                <small>…<?php echo e(substr($payout['account_number'], -4)); ?><?php echo $payout['recipient_code'] ? '' : ' (not verified)'; ?></small>
                            <?php else: ?>
                                <span class="field-error">No details yet</span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?php echo e(money((int) $payout['amount_minor'])); ?></td>
                        <td>
                            <span class="<?php echo $classes[$payout['status']]; ?>"><?php echo $labels[$payout['status']]; ?></span>
                            <?php if ($payout['failure_reason']): ?><br><small class="field-error"><?php echo e($payout['failure_reason']); ?></small><?php endif; ?>
                            <?php if ($payout['transfer_reference']): ?><br><small><?php echo e($payout['transfer_reference']); ?></small><?php endif; ?>
                            <br><small><?php echo escrow_time($payout['sent_at'] ?: $payout['created_at']); ?></small>
                        </td>
                        <td>
                            <?php if ($payout['status'] === 'failed'): ?>
                                <form action="<?php echo url('actions/admin/retry_payout_action.php'); ?>" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="payout_id" value="<?php echo $pid; ?>">
                                    <button class="btn" type="submit">Retry</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($payout['status'], ['failed', 'processing'], true)): ?>
                                <details>
                                    <summary>Already paid?</summary>
                                    <form class="admin-inline-form" action="<?php echo url('actions/admin/mark_payout_paid_action.php'); ?>" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="payout_id" value="<?php echo $pid; ?>">
                                        <input type="hidden" name="back_status" value="<?php echo e($status); ?>">
                                        <input class="input" name="reference" required minlength="3" maxlength="100" placeholder="Paystack transfer ref"
                                               value="<?php echo (int) ($old['payout_id'] ?? 0) === $pid ? e($old['reference'] ?? '') : e($payout['transfer_reference'] ?? ''); ?>">
                                        <button class="btn" type="submit">Mark paid</button>
                                    </form>
                                    <?php if ((int) ($old['payout_id'] ?? 0) === $pid && isset($admin_errors['reference'])): ?><small class="field-error"><?php echo e($admin_errors['reference']); ?></small><?php endif; ?>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php echo admin_pager($page, $result['total'], ADMIN_PER_PAGE); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
