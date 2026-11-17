<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class PaymentService
{
    public static function getTransactions(int $limit = 50): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT pt.*, o.order_number, o.shipping_name
            FROM payment_transactions pt
            JOIN orders o ON pt.order_id = o.id
            WHERE pt.website_id = :wid
            ORDER BY pt.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':wid', Context::getWebsiteId());
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function processRefund(string $orderId, int $refundAmountPaise, string $reason): bool
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND website_id = :wid");
        $stmt->execute([':id' => $orderId, ':wid' => $websiteId]);
        $order = $stmt->fetch();
        if (!$order) return false;

        $refundId = 'tx_ref_' . bin2hex(random_bytes(6));
        $rStmt = $db->prepare("
            INSERT INTO payment_transactions (id, website_id, order_id, gateway, gateway_transaction_id, transaction_type, amount, status, idempotency_key)
            VALUES (:id, :wid, :oid, 'razorpay', :gtx, 'REFUND', :amt, 'SUCCESS', :idemp)
        ");
        $rStmt->execute([
            ':id' => $refundId,
            ':wid' => $websiteId,
            ':oid' => $orderId,
            ':gtx' => 'rfnd_' . bin2hex(random_bytes(6)),
            ':amt' => $refundAmountPaise,
            ':idemp' => 'idemp_' . bin2hex(random_bytes(8))
        ]);

        $isFull = ($refundAmountPaise >= $order['grand_total_amount']);
        $newPaymentStatus = $isFull ? 'REFUNDED' : 'PARTIALLY_REFUNDED';

        $uStmt = $db->prepare("UPDATE orders SET payment_status = :pstatus, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND website_id = :wid");
        $uStmt->execute([':pstatus' => $newPaymentStatus, ':id' => $orderId, ':wid' => $websiteId]);

        OrderService::addTimelineEvent(
            $orderId, 
            'REFUND_PROCESSED', 
            "Processed refund of " . Context::formatMoney($refundAmountPaise) . " ({$newPaymentStatus}). Reason: {$reason}"
        );

        AuditService::log('payment.refund', 'order', $orderId, "Processed refund of {$refundAmountPaise} paise. Reason: {$reason}");

        return true;
    }
}
