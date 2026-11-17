<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\TemplateService;

AuthService::requireAuth();

$orderId = $_GET['id'] ?? $_GET['order_id'] ?? null;
if (!$orderId) {
    die("Error: Order ID required.");
}

$tpl = TemplateService::getTemplate('receipt');
$preset = $_GET['preset'] ?? $tpl['theme_style'] ?? 'thermal_standard';

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM orders WHERE (id = :id OR order_number = :id) AND website_id = :wid LIMIT 1");
$stmt->execute([':id' => $orderId, ':wid' => Context::getWebsiteId()]);
$order = $stmt->fetch();

if (!$order) {
    die("Error: Order not found.");
}

// Fetch items
$itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :oid");
$itemStmt->execute([':oid' => $order['id']]);
$items = $itemStmt->fetchAll();

if (empty($items)) {
    $items = [
        [
            'sku' => 'NUX-ABC-01',
            'title' => 'ABC+ Health Blend (500 g)',
            'quantity' => 2,
            'unit_price_amount' => 74900,
            'tax_amount' => 3745,
            'total_amount' => 149800
        ],
        [
            'sku' => 'NUX-MOR-02',
            'title' => 'Moringa Powder (200 g)',
            'quantity' => 1,
            'unit_price_amount' => 29900,
            'tax_amount' => 1495,
            'total_amount' => 29900
        ]
    ];
}

$receiptNumber = 'REC-' . date('Ymd') . '-' . str_pad((string)abs(crc32($order['order_number']) % 10000), 4, '0', STR_PAD_LEFT);
$receiptDate = date('d/m/Y H:i', strtotime($order['created_at']));
$hasGst = !empty($tpl['has_gst']) && !empty($tpl['tax_id']);

$subtotal = $order['subtotal_amount'] / 100;
$shipping = ($order['shipping_amount'] ?? 0) / 100;
$discount = ($order['discount_amount'] ?? 0) / 100;
$gstAmount = $hasGst ? round($subtotal * 0.05, 2) : 0.00;
$grandTotal = $hasGst ? ($subtotal + $shipping - $discount + $gstAmount) : ($subtotal + $shipping - $discount);

$sigUrl = !empty($tpl['signature_url']) ? $tpl['signature_url'] : null;
$sigSrc = $sigUrl ? (str_starts_with($sigUrl, 'http') || str_starts_with($sigUrl, '/') ? $sigUrl : '../' . $sigUrl) : null;

// =========================================================================
// REAL PDF GENERATION ENGINE FOR RECEIPT DOWNLOAD
// =========================================================================
function generateThermalReceiptPdf(array $order, array $items, string $receiptNo, array $tpl): string {
    $companyName = strtoupper($tpl['company_name'] ?? 'NUTRONYX BOTANICALS');
    $companyEmail = $tpl['company_email'] ?? 'care@nutronyx.com';
    $taxId = !empty($tpl['tax_id']) ? $tpl['tax_id'] : '';

    $lines = [];
    $lines[] = ['font' => '/F2 13.0', 'align' => 'center', 'text' => $companyName];
    $lines[] = ['font' => '/F1 8.5', 'align' => 'center', 'text' => 'Official POS Cash Receipt'];
    if (!empty($taxId)) {
        $lines[] = ['font' => '/F1 7.5', 'align' => 'center', 'text' => 'GSTIN: ' . $taxId];
    }
    $lines[] = ['type' => 'dash'];
    $lines[] = ['font' => '/F2 8.5', 'align' => 'left', 'text' => 'Receipt No: ' . $receiptNo];
    $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => 'Order No:   ' . ($order['order_number'] ?? 'NX0000')];
    $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => 'Date:       ' . date('d M Y h:i A', strtotime($order['created_at'] ?? 'now'))];
    $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => 'Cashier:    Nutronyx Admin'];
    $lines[] = ['type' => 'dash'];
    $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => 'Customer:   ' . substr($order['shipping_name'] ?? 'Valued Customer', 0, 22)];
    $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => 'Phone:      ' . ($order['shipping_phone'] ?? '+91 9392633211')];
    $lines[] = ['type' => 'dash'];
    $lines[] = ['font' => '/F2 8.5', 'align' => 'left', 'text' => sprintf('%-4s %-20s %8s', 'Qty', 'Item', 'Total')];

    foreach ($items as $it) {
        $name = substr($it['title'], 0, 20);
        $qty = ($it['quantity'] ?? 1) . 'x';
        $lineTot = ($it['total_amount'] ?? (($it['quantity'] ?? 1) * ($it['unit_price_amount'] ?? 0))) / 100;
        $totStr = 'Rs.' . number_format($lineTot, 2);
        $lines[] = ['font' => '/F1 8.0', 'align' => 'left', 'text' => sprintf('%-4s %-20s %8s', $qty, $name, $totStr)];
    }

    $lines[] = ['type' => 'dash'];
    $sub = 'Rs.' . number_format(($order['subtotal_amount'] ?? 0) / 100, 2);
    $ship = 'Rs.' . number_format(($order['shipping_amount'] ?? 0) / 100, 2);
    $disc = '-Rs.' . number_format(($order['discount_amount'] ?? 0) / 100, 2);
    $totPaid = 'Rs.' . number_format(($order['grand_total_amount'] ?? 0) / 100, 2);

    $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => sprintf('%-22s %11s', 'Subtotal:', $sub)];
    $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => sprintf('%-22s %11s', 'Shipping:', $ship)];
    if (($order['discount_amount'] ?? 0) > 0) {
        $lines[] = ['font' => '/F1 8.5', 'align' => 'left', 'text' => sprintf('%-22s %11s', 'Discount:', $disc)];
    }
    $lines[] = ['font' => '/F2 10.0', 'align' => 'left', 'text' => sprintf('%-20s %11s', 'TOTAL PAID:', $totPaid)];
    $lines[] = ['type' => 'dash'];

    $pm = ($order['payment_method'] ?? 'ONLINE') . ' (' . ($order['payment_status'] ?? 'PAID') . ')';
    $lines[] = ['font' => '/F1 8.0', 'align' => 'center', 'text' => 'Payment Mode: ' . $pm];
    $lines[] = ['font' => '/F2 11.0', 'align' => 'center', 'text' => '*' . ($order['order_number'] ?? 'NX0000') . '*'];
    $lines[] = ['font' => '/F2 8.5', 'align' => 'center', 'text' => 'THANK YOU FOR SHOPPING!'];
    $lines[] = ['font' => '/F1 7.5', 'align' => 'center', 'text' => $companyEmail];

    $pageWidth = 226.77; // 80mm
    $lineHeight = 13.5;
    $totalH = 40 + count($lines) * $lineHeight + 40;
    $y = $totalH - 30;

    $stream = "q
";
    foreach ($lines as $item) {
        if (($item['type'] ?? '') === 'dash') {
            $stream .= "0.5 w [2 2] 0 d 15 " . sprintf('%.2f', $y) . " m 211.77 " . sprintf('%.2f', $y) . " l S [] 0 d
";
            $y -= 10;
            continue;
        }

        $font = $item['font'];
        $fontSize = (float)explode(' ', $font)[1];
        $txt = $item['text'];
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $txt);
        $charW = $fontSize * 0.6;
        $strW = strlen($txt) * $charW;

        if ($item['align'] === 'center') {
            $x = max(15, ($pageWidth - $strW) / 2);
        } else {
            $x = 15;
        }

        $stream .= "BT
{$font} Tf
" . sprintf('%.2f %.2f', $x, $y) . " Td
({$escaped}) Tj
ET
";
        $y -= $lineHeight;
    }
    $stream .= "Q
";

    $streamLen = strlen($stream);

    $objs = [];
    $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 226.77 " . sprintf('%.2f', $totalH) . "] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
    $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
    $objs[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>";
    $objs[6] = "<< /Length {$streamLen} >>
stream
{$stream}endstream";

    $out = "%PDF-1.4
";
    $offsets = [];
    for ($i = 1; $i <= 6; $i++) {
        $offsets[$i] = strlen($out);
        $out .= "{$i} 0 obj
" . $objs[$i] . "
endobj
";
    }

    $xrefOffset = strlen($out);
    $out .= "xref
0 7
0000000000 65535 f 
";
    for ($i = 1; $i <= 6; $i++) {
        $out .= sprintf("%010d 00000 n 
", $offsets[$i]);
    }
    $out .= "trailer
<< /Size 7 /Root 1 0 R >>
startxref
{$xrefOffset}
%%EOF
";

    return $out;
}

// Serve Real PDF File Download if requested
if (isset($_GET['download']) && strtolower((string)$_GET['download']) === 'pdf') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $pdfData = generateThermalReceiptPdf($order, $items, $receiptNumber, $tpl);
    $pdfFilename = "Nutronyx-Receipt-" . ($order['order_number'] ?: $order['id']) . ".pdf";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    header('Content-Length: ' . strlen($pdfData));
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $pdfData;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt &mdash; <?= htmlspecialchars($receiptNumber) ?> (<?= htmlspecialchars(ucwords(str_replace('_', ' ', $preset))) ?>)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background: #ECEFF1;
            margin: 0;
            padding: 24px 10px;
            color: #111827;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .action-bar {
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 480px;
        }
        .btn-act {
            background: #144D29;
            color: #FFF;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-act.secondary {
            background: #FFF;
            color: #374151;
            border: 1px solid #D1D5DB;
        }

        /* -------------------------------------------------------------
           RECEIPT PRESET 1: STANDARD SLIP (A4 / Half Sheet)
        --------------------------------------------------------------*/
        .receipt-standard {
            width: 480px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 8px;
            padding: 28px 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
        }
        .receipt-standard .brand-header {
            text-align: center;
            border-bottom: 2px solid #144D29;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }

        /* -------------------------------------------------------------
           RECEIPT PRESET 2: COMPACT SLIP
        --------------------------------------------------------------*/
        .receipt-compact {
            width: 360px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 6px;
            padding: 20px 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
            font-size: 12px;
        }
        .receipt-compact .brand-header {
            text-align: center;
            border-bottom: 1px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        /* -------------------------------------------------------------
           RECEIPT PRESET 3: THERMAL 58MM (Narrow POS Roll)
        --------------------------------------------------------------*/
        .receipt-thermal-58 {
            width: 240px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 16px 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.35;
            border: 1px dashed #9CA3AF;
        }

        /* -------------------------------------------------------------
           RECEIPT PRESET 4: THERMAL 80MM (Standard POS Roll)
        --------------------------------------------------------------*/
        .receipt-thermal-80 {
            width: 320px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 22px 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            border: 1px solid #D1D5DB;
        }

        @media print {
            body { background: #FFF; padding: 0; }
            .receipt-standard, .receipt-compact, .receipt-thermal-58, .receipt-thermal-80 {
                box-shadow: none; border-radius: 0; border: none; margin: 0 auto; padding: 10px 0;
            }
            .action-bar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="action-bar" style="<?= ($preset === 'thermal_standard') ? 'max-width: 480px;' : (($preset === 'thermal_58mm') ? 'max-width: 240px;' : 'max-width: 340px;') ?>">
        <a href="templates.php?tab=receipt" class="btn-act secondary">&larr; Studio</a>
        <button onclick="window.print()" class="btn-act">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <span>Print Receipt</span>
        </button>
    </div>

    <?php if ($preset === 'thermal_58mm'): ?>
        <!-- ================= 58MM NARROW THERMAL POS ROLL ================= -->
        <div class="receipt-thermal-58">
            <div style="text-align: center; font-weight: 700; font-size: 14px;">NUTRONYX</div>
            <div style="text-align: center; font-size: 9.5px;"><?= htmlspecialchars($tpl['company_name']) ?></div>
            <div style="text-align: center; font-size: 9px; color: #4B5563;">Whitefield Store, BLR</div>
            <?php if ($hasGst): ?>
                <div style="text-align: center; font-size: 9px;"><?= htmlspecialchars($tpl['tax_id']) ?></div>
            <?php endif; ?>
            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

            <div style="display: flex; justify-content: space-between; font-size: 10px;">
                <span><?= htmlspecialchars($receiptNumber) ?></span>
                <span><?= date('d/m/y') ?></span>
            </div>
            <div style="font-size: 10px;">Cust: <?= htmlspecialchars(substr($order['shipping_name'] ?? 'Customer', 0, 16)) ?></div>
            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

            <!-- Items -->
            <?php foreach ($items as $item): 
                $rate = $item['unit_price_amount'] / 100;
                $amt = ($item['total_amount'] ?? ($rate * $item['quantity'] * 100)) / 100;
            ?>
                <div style="font-weight: 700;"><?= htmlspecialchars(substr($item['title'], 0, 22)) ?></div>
                <div style="display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 4px;">
                    <span><?= (int)$item['quantity'] ?> x <?= number_format($rate, 2) ?></span>
                    <span>₹<?= number_format($amt, 2) ?></span>
                </div>
            <?php endforeach; ?>

            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
            <div style="display: flex; justify-content: space-between; font-size: 10px;">
                <span>SUBTOTAL:</span>
                <span>₹<?= number_format($subtotal, 2) ?></span>
            </div>
            <?php if ($hasGst): ?>
            <div style="display: flex; justify-content: space-between; font-size: 10px;">
                <span>GST (5%):</span>
                <span>₹<?= number_format($gstAmount, 2) ?></span>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 800; border-top: 1px solid #000; padding-top: 4px; margin-top: 4px;">
                <span>TOTAL:</span>
                <span>₹<?= number_format($grandTotal, 2) ?></span>
            </div>
            <div style="text-align: right; font-size: 9.5px; margin-top: 2px;">PAID: UPI/STORE POS</div>

            <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
            <div style="text-align: center; font-size: 9px; line-height: 1.3;">
                Thank you for visiting!<br>
                Nutronyx Botanical Nutrition<br>
                support@nutronyx.com
            </div>
        </div>

    <?php elseif ($preset === 'thermal_80mm'): ?>
        <!-- ================= 80MM STANDARD POS THERMAL ROLL ================= -->
        <div class="receipt-thermal-80">
            <div style="text-align: center; font-weight: 800; font-size: 16px; letter-spacing: 1px;">* NUTRONYX *</div>
            <div style="text-align: center; font-size: 11px; margin-top: 2px;"><?= htmlspecialchars($tpl['company_name']) ?></div>
            <div style="text-align: center; font-size: 10px; color: #4B5563;"><?= htmlspecialchars($tpl['company_address']) ?></div>
            <?php if ($hasGst): ?>
                <div style="text-align: center; font-size: 10.5px; font-weight: 700; margin-top: 2px;">GSTIN: <?= htmlspecialchars($tpl['tax_id']) ?></div>
            <?php endif; ?>
            <div style="border-top: 1px dashed #444; margin: 8px 0;"></div>

            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span>Receipt: <strong><?= htmlspecialchars($receiptNumber) ?></strong></span>
                <span><?= $receiptDate ?></span>
            </div>
            <div style="font-size: 11px; margin-top: 2px;">
                Customer: <strong><?= htmlspecialchars($order['shipping_name'] ?? 'Counter Guest') ?></strong>
            </div>
            <div style="font-size: 10.5px; color: #4B5563;">
                Order Ref: <?= htmlspecialchars($order['order_number']) ?>
            </div>
            <div style="border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; margin: 8px 0; font-weight: 700; font-size: 11px; display: flex; justify-content: space-between;">
                <span>ITEM DESCRIPTION</span>
                <span>QTY &bull; AMOUNT</span>
            </div>

            <!-- Item Rows -->
            <?php foreach ($items as $item): 
                $rate = $item['unit_price_amount'] / 100;
                $amt = ($item['total_amount'] ?? ($rate * $item['quantity'] * 100)) / 100;
            ?>
                <div style="font-weight: 700;"><?= htmlspecialchars($item['title']) ?></div>
                <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 6px;">
                    <span style="color: #4B5563;"><?= (int)$item['quantity'] ?> pcs @ ₹<?= number_format($rate, 2) ?></span>
                    <strong>₹<?= number_format($amt, 2) ?></strong>
                </div>
            <?php endforeach; ?>

            <div style="border-top: 1px dashed #444; margin: 8px 0;"></div>
            <div style="display: flex; justify-content: space-between; font-size: 11.5px;">
                <span>Subtotal:</span>
                <span>₹<?= number_format($subtotal, 2) ?></span>
            </div>
            <?php if ($hasGst): ?>
            <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #4B5563;">
                <span>GST (5%):</span>
                <span>₹<?= number_format($gstAmount, 2) ?></span>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 800; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; margin: 8px 0;">
                <span>TOTAL AMOUNT:</span>
                <span>₹<?= number_format($grandTotal, 2) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 10.5px; color: #374151;">
                <span>Payment Mode:</span>
                <span>PREPAID (UPI / CARD)</span>
            </div>

            <div style="border-top: 1px dashed #444; margin: 10px 0;"></div>
            <div style="text-align: center; font-size: 10px; color: #4B5563; line-height: 1.4;">
                *** THANK YOU FOR VISITING NUTRONYX ***<br>
                Keep this slip for 7-day store warranty/exchange.<br>
                Helpline: <?= htmlspecialchars($tpl['contact_phone']) ?>
            </div>
        </div>

    <?php elseif ($preset === 'thermal_compact'): ?>
        <!-- ================= COMPACT SLIP ================= -->
        <div class="receipt-compact">
            <div class="brand-header">
                <div style="font-size: 16px; font-weight: 800; color: #144D29; letter-spacing: 0.5px;">NUTRONYX BOTANICALS</div>
                <div style="font-size: 11px; color: #6B7280;"><?= htmlspecialchars($tpl['company_address']) ?></div>
                <?php if ($hasGst): ?>
                    <div style="font-size: 11px; font-weight: 700; color: #166534; margin-top: 2px;"><?= htmlspecialchars($tpl['tax_id']) ?></div>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 11.5px;">
                <span>Slip: <strong><?= htmlspecialchars($receiptNumber) ?></strong></span>
                <span><?= date('d M Y') ?></span>
            </div>
            <div style="font-size: 12px; margin-bottom: 8px;">
                Customer: <strong><?= htmlspecialchars($order['shipping_name'] ?? 'Customer') ?></strong>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 11.5px; margin-bottom: 8px;">
                <tr style="border-bottom: 1px solid #111827; font-weight: 700; font-size: 10.5px;">
                    <th style="text-align: left; padding: 4px 0;">Item</th>
                    <th style="text-align: center; width: 40px;">Qty</th>
                    <th style="text-align: right; width: 70px;">Amt</th>
                </tr>
                <?php foreach ($items as $item): 
                    $rate = $item['unit_price_amount'] / 100;
                    $amt = ($item['total_amount'] ?? ($rate * $item['quantity'] * 100)) / 100;
                ?>
                <tr style="border-bottom: 1px solid #F3F4F6;">
                    <td style="padding: 6px 0;"><?= htmlspecialchars($item['title']) ?></td>
                    <td style="text-align: center;"><?= (int)$item['quantity'] ?></td>
                    <td style="text-align: right; font-weight: 600;">₹<?= number_format($amt, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <div style="border-top: 1px solid #111827; padding-top: 6px; font-size: 12px;">
                <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 13.5px; color: #144D29;">
                    <span>Grand Total:</span>
                    <span>₹<?= number_format($grandTotal, 2) ?></span>
                </div>
            </div>

            <div style="text-align: center; font-size: 10px; color: #6B7280; margin-top: 12px; border-top: 1px dashed #D1D5DB; padding-top: 8px;">
                Thank you! Valid for counter pickup & warranty.
            </div>
        </div>

    <?php else: ?>
        <!-- ================= DEFAULT: STANDARD CUSTOMER SLIP ================= -->
        <div class="receipt-standard">
            <div class="brand-header">
                <?php if (!empty($tpl['logo_url'])): ?>
                    <img src="../<?= htmlspecialchars($tpl['logo_url']) ?>" alt="Logo" style="max-height: 38px; margin-bottom: 6px;">
                <?php endif; ?>
                <div style="font-size: 18px; font-weight: 800; color: #144D29; letter-spacing: -0.5px;">
                    <?= htmlspecialchars(!empty($tpl['brand_name']) ? $tpl['brand_name'] : 'NUTRONYX') ?>
                </div>
                <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                    <?= htmlspecialchars($tpl['company_name']) ?> &bull; <?= htmlspecialchars($tpl['company_address']) ?>
                </div>
                <?php if ($hasGst): ?>
                    <div style="font-size: 12px; font-weight: 700; color: #166534; margin-top: 3px;"><?= htmlspecialchars($tpl['tax_id']) ?></div>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 12.5px; background: #F8FAF9; padding: 10px 14px; border-radius: 6px;">
                <div>
                    <div>Receipt: <strong><?= htmlspecialchars($receiptNumber) ?></strong></div>
                    <div>Date: <?= $receiptDate ?></div>
                </div>
                <div style="text-align: right;">
                    <div>Customer: <strong><?= htmlspecialchars($order['shipping_name'] ?? 'Valued Customer') ?></strong></div>
                    <div>Order Ref: <strong><?= htmlspecialchars($order['order_number']) ?></strong></div>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; margin-bottom: 14px;">
                <thead>
                    <tr style="background: #144D29; color: #FFF; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 6px 10px; text-align: left;">Product</th>
                        <th style="padding: 6px 10px; text-align: center; width: 45px;">Qty</th>
                        <th style="padding: 6px 10px; text-align: right; width: 85px;">Rate</th>
                        <th style="padding: 6px 10px; text-align: right; width: 95px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($items as $item): 
                        $rate = $item['unit_price_amount'] / 100;
                        $amt = ($item['total_amount'] ?? ($rate * $item['quantity'] * 100)) / 100;
                    ?>
                    <tr style="border-bottom: 1px solid #E5E7EB;">
                        <td style="padding: 8px 10px;">
                            <strong><?= htmlspecialchars($item['title']) ?></strong>
                            <div style="font-size: 10.5px; color: #6B7280;">SKU: <?= htmlspecialchars($item['sku'] ?? 'NUX-001') ?></div>
                        </td>
                        <td style="padding: 8px 10px; text-align: center; font-weight: 600;"><?= (int)$item['quantity'] ?></td>
                        <td style="padding: 8px 10px; text-align: right;">₹<?= number_format($rate, 2) ?></td>
                        <td style="padding: 8px 10px; text-align: right; font-weight: 700; color: #144D29;">₹<?= number_format($amt, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display: flex; justify-content: flex-end; margin-bottom: 14px;">
                <div style="width: 220px; font-size: 12.5px;">
                    <div style="display: flex; justify-content: space-between; padding: 3px 0; color: #4B5563;">
                        <span>Subtotal:</span>
                        <span>₹<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <?php if ($hasGst): ?>
                    <div style="display: flex; justify-content: space-between; padding: 3px 0; color: #4B5563;">
                        <span>GST (5%):</span>
                        <span>₹<?= number_format($gstAmount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; padding: 6px 0; border-top: 1.5px solid #144D29; font-weight: 800; font-size: 14px; color: #144D29;">
                        <span>Total Paid:</span>
                        <span>₹<?= number_format($grandTotal, 2) ?></span>
                    </div>
                </div>
            </div>

            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 6px; padding: 8px 12px; font-size: 11px; display: flex; justify-content: space-between; align-items: center;">
                <span>Payment: <strong>UPI / Online Reconciled</strong></span>
                <span style="background: #DCFCE7; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: 700;">PAID</span>
            </div>

            <div style="text-align: center; font-size: 11px; color: #6B7280; margin-top: 16px; border-top: 1px solid #E5E7EB; padding-top: 10px;">
                Thank you for shopping with Nutronyx!<br>
                For questions or support, contact care@nutronyx.com
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
