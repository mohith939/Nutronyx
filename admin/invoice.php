<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\TemplateService;

AuthService::requireAuth();

$orderId = $_GET['id'] ?? $_GET['order_id'] ?? null;
if (!$orderId && !empty($_GET['ids'])) {
    $ids = explode(',', (string)$_GET['ids']);
    $orderId = trim($ids[0]);
}
if (!$orderId) {
    die("Error: Order ID required.");
}

$tpl = TemplateService::getTemplate('invoice');
$preset = $_GET['preset'] ?? $tpl['theme_style'] ?? 'classic_executive';

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM orders WHERE (id = :id OR order_number = :id) AND website_id = :wid LIMIT 1");
$stmt->execute([':id' => $orderId, ':wid' => Context::getWebsiteId()]);
$order = $stmt->fetch();

if (!$order) {
    die("Error: Order not found.");
}

// Fetch order items
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

$invoiceNumber = $order['invoice_number'] ?? (($tpl['invoice_prefix'] ?? 'INV') . '-' . date('Y', strtotime($order['created_at'])) . '-' . str_pad((string)abs(crc32($order['order_number']) % 100000), 5, '0', STR_PAD_LEFT));
$invoiceDate = date('d M Y', strtotime($order['created_at']));
$activeWebsite = Context::getWebsite();
$hasGst = !empty($tpl['has_gst']) && !empty($tpl['tax_id']);

$subtotal = $order['subtotal_amount'] / 100;
$shipping = ($order['shipping_amount'] ?? 0) / 100;
$discount = ($order['discount_amount'] ?? 0) / 100;
$taxRate = 0.05;
$gstAmount = $hasGst ? round($subtotal * $taxRate, 2) : 0.00;
$grandTotal = $hasGst ? ($subtotal + $shipping - $discount + $gstAmount) : ($subtotal + $shipping - $discount);

$sigUrl = !empty($tpl['signature_url']) ? $tpl['signature_url'] : null;
$sigSrc = $sigUrl ? (str_starts_with($sigUrl, 'http') || str_starts_with($sigUrl, '/') ? $sigUrl : '../' . $sigUrl) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice &mdash; <?= htmlspecialchars($invoiceNumber) ?> (<?= htmlspecialchars(ucwords(str_replace('_', ' ', $preset))) ?>)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #EFEFEF;
            color: #14221A;
            margin: 0;
            padding: 30px 20px;
        }

        .action-toolbar {
            max-width: 860px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-toolbar {
            background: #144D29;
            color: #FFF;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-toolbar.secondary {
            background: #FFF;
            color: #2C3E33;
            border: 1px solid #D8DFDB;
        }

        /* Base Card Container */
        .invoice-card {
            max-width: 860px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 44px 48px;
            position: relative;
        }

        /* -------------------------------------------------------------
           PRESET 1: CLASSIC EXECUTIVE
        --------------------------------------------------------------*/
        .preset-classic_executive .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2.5px solid #144D29;
            padding-bottom: 22px;
            margin-bottom: 24px;
        }
        .preset-classic_executive .brand-title {
            font-size: 26px;
            font-weight: 800;
            color: #144D29;
            letter-spacing: -0.5px;
        }
        .preset-classic_executive table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .preset-classic_executive table.items-table th {
            background: #144D29;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 10px 14px;
            border: 1px solid #144D29;
        }
        .preset-classic_executive table.items-table td {
            padding: 12px 14px;
            border: 1px solid #E5E7EB;
            font-size: 13.5px;
        }

        /* -------------------------------------------------------------
           PRESET 2: BOTANICAL SIGNATURE (NO LEAF WATERMARK)
        --------------------------------------------------------------*/
        .preset-botanical_luxury {
            font-family: 'Plus Jakarta Sans', sans-serif;
            border-top: 6px solid #166534;
        }
        .preset-botanical_luxury .brand-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 32px;
            font-weight: 700;
            color: #166534;
            letter-spacing: 0.5px;
        }
        .preset-botanical_luxury .doc-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 24px;
            font-weight: 700;
            color: #166534;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .preset-botanical_luxury .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #BBF7D0;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .preset-botanical_luxury table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .preset-botanical_luxury table.items-table th {
            background: #F0FDF4;
            color: #166534;
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            border-top: 1.5px solid #166534;
            border-bottom: 1.5px solid #166534;
        }
        .preset-botanical_luxury table.items-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #E5E7EB;
            font-size: 13.5px;
        }

        /* -------------------------------------------------------------
           PRESET 3: MODERN MINIMALIST
        --------------------------------------------------------------*/
        .preset-modern_minimal {
            font-family: 'Plus Jakarta Sans', sans-serif;
            border: 1px solid #E2E8F0;
        }
        .preset-modern_minimal .brand-title {
            font-size: 24px;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: -0.5px;
        }
        .preset-modern_minimal .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #CBD5E1;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .preset-modern_minimal table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .preset-modern_minimal table.items-table th {
            background: transparent;
            color: #64748B;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 8px 12px;
            border-bottom: 2px solid #0F172A;
        }
        .preset-modern_minimal table.items-table td {
            padding: 12px 12px;
            border-bottom: 1px solid #F1F5F9;
            font-size: 13.5px;
            color: #334155;
        }

        /* -------------------------------------------------------------
           PRESET 4: B2B WHOLESALE HUB
        --------------------------------------------------------------*/
        .preset-b2b_wholesale {
            font-family: 'Plus Jakarta Sans', sans-serif;
            border: 1.5px solid #CBD5E1;
            padding: 32px 36px;
        }
        .preset-b2b_wholesale .b2b-tax-banner {
            background: #0F172A;
            color: #FFFFFF;
            padding: 8px 14px;
            text-align: center;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 1.5px;
            margin-bottom: 16px;
            border-radius: 4px;
        }
        .preset-b2b_wholesale table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 12.5px;
        }
        .preset-b2b_wholesale table.items-table th {
            background: #F1F5F9;
            color: #0F172A;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #94A3B8;
        }
        .preset-b2b_wholesale table.items-table td {
            padding: 8px 10px;
            border: 1px solid #CBD5E1;
        }

        @media print {
            body { background: #FFF; padding: 0; }
            .invoice-card { box-shadow: none; border-radius: 0; padding: 20px 0; border: none; }
            .action-toolbar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="action-toolbar">
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="orders.php" class="btn-toolbar secondary">&larr; Back to Orders</a>
            <a href="templates.php?doc=invoice" class="btn-toolbar secondary" style="color: #144D29; font-weight: 700;">⚙️ Global Template Studio</a>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="font-size: 12px; color: #64748B; font-weight: 600;">Active Layout:</span>
            <span style="background: #E0E7FF; color: #3730A3; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $preset))) ?>
            </span>
            <button onclick="window.print()" class="btn-toolbar" style="margin-left: 10px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                <span>Print Document</span>
            </button>
        </div>
    </div>

    <!-- MAIN INVOICE CARD WITH DISTINCT PRESET LAYOUTS -->
    <div class="invoice-card preset-<?= htmlspecialchars($preset) ?>">

        <?php if ($preset === 'b2b_wholesale'): ?>
            <!-- ================= B2B WHOLESALE TEMPLATE ================= -->
            <div class="b2b-tax-banner">
                <?= $hasGst ? 'COMMERCIAL TAX INVOICE (RULE 46 - CGST RULES)' : 'BILL OF SUPPLY / COMMERCIAL INVOICE' ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #94A3B8; margin-bottom: 16px;">
                <!-- Consignor / Seller Block -->
                <div style="padding: 14px; border-right: 1px solid #94A3B8; font-size: 12.5px; line-height: 1.45;">
                    <strong style="color: #0F172A; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">Consignor (Supplier / Dispatched From):</strong>
                    <div style="font-size: 14px; font-weight: 800; color: #0F172A;"><?= htmlspecialchars($tpl['company_name']) ?></div>
                    <div><?= nl2br(htmlspecialchars($tpl['company_address'])) ?></div>
                    <div>Phone: <?= htmlspecialchars($tpl['contact_phone']) ?> &bull; Email: <?= htmlspecialchars($tpl['contact_email']) ?></div>
                    <?php if ($hasGst): ?>
                        <div style="margin-top: 4px; font-weight: 700; color: #0F172A;">GSTIN: <?= htmlspecialchars($tpl['tax_id']) ?> | State: Telangana (36)</div>
                    <?php endif; ?>
                </div>

                <!-- Consignee / Buyer Block -->
                <div style="padding: 14px; font-size: 12.5px; line-height: 1.45;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <strong style="color: #0F172A; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Consignee (Billed & Shipped To):</strong>
                        <span style="font-weight: 700; color: #0F172A;">Inv #: <?= htmlspecialchars($invoiceNumber) ?></span>
                    </div>
                    <div style="font-size: 14px; font-weight: 800; color: #0F172A;"><?= htmlspecialchars($order['shipping_name'] ?? 'B2B Partner') ?></div>
                    <div><?= htmlspecialchars($order['shipping_address_line1'] ?? '') ?>, <?= htmlspecialchars($order['shipping_city'] ?? '') ?></div>
                    <div>State: <?= htmlspecialchars($order['shipping_state'] ?? 'Telangana') ?> &bull; Phone: <?= htmlspecialchars($order['shipping_phone'] ?? 'N/A') ?></div>
                    <div style="margin-top: 4px; color: #475569;">Date: <strong><?= $invoiceDate ?></strong> &bull; Order Ref: <strong><?= htmlspecialchars($order['order_number']) ?></strong></div>
                </div>
            </div>

            <!-- B2B Structured Items Grid with HSN and GST Breakdown -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 30px; text-align: center;">#</th>
                        <th>Description of Goods / Products</th>
                        <th style="width: 80px; text-align: center;">HSN Code</th>
                        <th style="width: 50px; text-align: center;">Qty</th>
                        <th style="width: 90px; text-align: right;">Unit Rate</th>
                        <th style="width: 100px; text-align: right;">Taxable Amt</th>
                        <?php if ($hasGst): ?>
                            <th style="width: 85px; text-align: right;">CGST (2.5%)</th>
                            <th style="width: 85px; text-align: right;">SGST (2.5%)</th>
                        <?php endif; ?>
                        <th style="width: 105px; text-align: right;">Total (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($items as $item): 
                        $rate = $item['unit_price_amount'] / 100;
                        $qty = (int)$item['quantity'];
                        $itemTaxable = $rate * $qty;
                        $cgst = $hasGst ? round($itemTaxable * 0.025, 2) : 0;
                        $sgst = $hasGst ? round($itemTaxable * 0.025, 2) : 0;
                        $rowTotal = $itemTaxable + $cgst + $sgst;
                    ?>
                    <tr>
                        <td style="text-align: center; color: #64748B;"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['title']) ?></strong>
                            <div style="font-size: 11px; color: #64748B;">SKU: <?= htmlspecialchars($item['sku'] ?? 'NUX-001') ?></div>
                        </td>
                        <td style="text-align: center; font-family: monospace;">2106 90 99</td>
                        <td style="text-align: center; font-weight: 700;"><?= $qty ?></td>
                        <td style="text-align: right;">₹<?= number_format($rate, 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format($itemTaxable, 2) ?></td>
                        <?php if ($hasGst): ?>
                            <td style="text-align: right;">₹<?= number_format($cgst, 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($sgst, 2) ?></td>
                        <?php endif; ?>
                        <td style="text-align: right; font-weight: 700;">₹<?= number_format($rowTotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- B2B Totals & Bank Matrix -->
            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; margin-top: 16px;">
                <div style="background: #F8FAFC; border: 1px solid #CBD5E1; padding: 12px 14px; font-size: 12px; border-radius: 4px;">
                    <strong style="color: #0F172A; text-transform: uppercase; font-size: 11px;">Electronic Payment / NEFT / RTGS Details:</strong>
                    <div style="margin-top: 6px; line-height: 1.5; color: #334155;">
                        Bank: <strong>HDFC Bank Limited</strong><br>
                        Beneficiary Name: <strong><?= htmlspecialchars($tpl['company_name']) ?></strong><br>
                        Account No: <strong>50200084920194</strong> (Current A/C)<br>
                        IFSC Code: <strong>HDFC0001234</strong> &bull; Branch: Bengaluru
                    </div>
                </div>

                <div style="border: 1px solid #CBD5E1; background: #FFFFFF; font-size: 12.5px;">
                    <div style="display: flex; justify-content: space-between; padding: 6px 12px; border-bottom: 1px solid #E2E8F0;">
                        <span>Total Taxable Value:</span>
                        <strong>₹<?= number_format($subtotal, 2) ?></strong>
                    </div>
                    <?php if ($hasGst): ?>
                    <div style="display: flex; justify-content: space-between; padding: 6px 12px; border-bottom: 1px solid #E2E8F0; color: #475569;">
                        <span>Central GST (2.5%):</span>
                        <span>₹<?= number_format($gstAmount / 2, 2) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 6px 12px; border-bottom: 1px solid #E2E8F0; color: #475569;">
                        <span>State GST (2.5%):</span>
                        <span>₹<?= number_format($gstAmount / 2, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #0F172A; color: #FFF; font-weight: 800; font-size: 14.5px;">
                        <span>Total Invoice Value:</span>
                        <span>₹<?= number_format($grandTotal, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- B2B Statutory Declaration & Signatory -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 24px; border-top: 1px solid #94A3B8; padding-top: 14px;">
                <div style="font-size: 11.5px; color: #64748B; max-width: 480px; line-height: 1.45;">
                    <strong>Statutory Declaration:</strong><br>
                    We declare that this commercial invoice shows the actual price of the goods described and that all particulars are true and correct.
                </div>
                <div style="text-align: right; width: 200px;">
                    <div style="font-size: 11px; color: #0F172A; font-weight: 700; margin-bottom: 4px;">For <?= htmlspecialchars($tpl['company_name']) ?>:</div>
                    <?php if ($sigSrc): ?>
                        <img src="<?= htmlspecialchars($sigSrc) ?>" alt="Signature" style="max-height: 48px; max-width: 150px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                    <?php endif; ?>
                    <div style="height: 30px; border-bottom: 1px solid #0F172A; margin-bottom: 4px;"></div>
                    <strong style="color: #0F172A; font-size: 12px;"><?= htmlspecialchars($tpl['signatory_name'] ?? 'Authorized Signatory') ?></strong>
                    <div style="font-size: 10.5px; color: #64748B;"><?= htmlspecialchars($tpl['signatory_title'] ?? 'Compliance Officer') ?></div>
                </div>
            </div>

        <?php elseif ($preset === 'modern_minimal'): ?>
            <!-- ================= MODERN MINIMALIST TEMPLATE ================= -->
            <div class="header-bar">
                <div>
                    <div class="brand-title"><?= htmlspecialchars($tpl['brand_name'] ?: 'NUTRONYX') ?></div>
                    <div style="font-size: 12.5px; color: #64748B; margin-top: 2px;"><?= htmlspecialchars($tpl['company_name']) ?></div>
                    <div style="font-size: 11.5px; color: #94A3B8; margin-top: 2px;">
                        <?= htmlspecialchars($tpl['contact_email']) ?> &bull; <?= htmlspecialchars($tpl['contact_phone']) ?>
                        <?php if ($hasGst): ?> &bull; <strong><?= htmlspecialchars($tpl['tax_id']) ?></strong><?php endif; ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 20px; font-weight: 800; color: #0F172A; letter-spacing: 0.5px;"><?= $hasGst ? 'TAX INVOICE' : 'INVOICE' ?></div>
                    <div style="font-size: 13px; color: #64748B; margin-top: 4px;"># <strong><?= htmlspecialchars($invoiceNumber) ?></strong></div>
                    <div style="font-size: 12px; color: #94A3B8;">Date: <?= $invoiceDate ?></div>
                </div>
            </div>

            <!-- Minimal Bill/Ship Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 24px; font-size: 13px; line-height: 1.5;">
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Billed To</div>
                    <strong style="color: #0F172A;"><?= htmlspecialchars($order['shipping_name'] ?? 'Valued Customer') ?></strong><br>
                    <span style="color: #475569;"><?= htmlspecialchars($order['shipping_email'] ?? '') ?> &bull; <?= htmlspecialchars($order['shipping_phone'] ?? '') ?></span>
                </div>
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Shipped To</div>
                    <span style="color: #475569;">
                        <?= htmlspecialchars($order['shipping_address_line1'] ?? '') ?>, <?= htmlspecialchars($order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['shipping_postal_code'] ?? '') ?>
                    </span>
                </div>
            </div>

            <!-- Minimal Line Items -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>Product</th>
                        <th style="text-align: center; width: 60px;">Qty</th>
                        <th style="text-align: right; width: 100px;">Price</th>
                        <th style="text-align: right; width: 110px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($items as $item): 
                        $rate = $item['unit_price_amount'] / 100;
                        $amt = ($item['total_amount'] ?? ($rate * $item['quantity'] * 100)) / 100;
                    ?>
                    <tr>
                        <td style="color: #94A3B8;"><?= $i++ ?></td>
                        <td>
                            <strong style="color: #0F172A;"><?= htmlspecialchars($item['title']) ?></strong>
                            <div style="font-size: 11px; color: #94A3B8;">SKU: <?= htmlspecialchars($item['sku'] ?? 'NUX-001') ?></div>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?= (int)$item['quantity'] ?></td>
                        <td style="text-align: right;">₹<?= number_format($rate, 2) ?></td>
                        <td style="text-align: right; font-weight: 600; color: #0F172A;">₹<?= number_format($amt, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Minimal Totals -->
            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <div style="width: 240px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #64748B;">
                        <span>Subtotal</span>
                        <span>₹<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <?php if ($hasGst): ?>
                    <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #64748B;">
                        <span>GST (5%)</span>
                        <span>₹<?= number_format($gstAmount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-top: 1.5px solid #0F172A; font-weight: 800; font-size: 15px; color: #0F172A; margin-top: 6px;">
                        <span>Total Due</span>
                        <span>₹<?= number_format($grandTotal, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Minimal Signatory -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 16px; border-top: 1px solid #E2E8F0;">
                <div style="font-size: 12px; color: #94A3B8;">
                    Payment: <strong>Prepaid (UPI / NetBanking)</strong> &bull; Order Ref: <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                </div>
                <div style="text-align: right;">
                    <?php if ($sigSrc): ?>
                        <img src="<?= htmlspecialchars($sigSrc) ?>" alt="Signature" style="max-height: 44px; max-width: 140px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                    <?php endif; ?>
                    <div style="width: 160px; border-bottom: 1px solid #CBD5E1; height: 24px; margin-left: auto;"></div>
                    <strong style="font-size: 12px; color: #0F172A;"><?= htmlspecialchars($tpl['signatory_name'] ?? 'Authorized Signatory') ?></strong>
                </div>
            </div>

        <?php elseif ($preset === 'botanical_luxury'): ?>
            <!-- ================= BOTANICAL SIGNATURE (NO LEAF WATERMARK) ================= -->
            <div class="header-bar">
                <div>
                    <?php if (!empty($tpl['logo_url'])): ?>
                        <img src="../<?= htmlspecialchars($tpl['logo_url']) ?>" alt="Logo" style="max-height: 40px; margin-bottom: 6px;">
                    <?php endif; ?>
                    <div class="brand-title"><?= htmlspecialchars($tpl['brand_name'] ?: 'Nutronyx Botanicals') ?></div>
                    <div style="font-size: 13px; color: #166534; font-family: 'Cormorant Garamond', Georgia, serif; font-style: italic;">
                        <?= htmlspecialchars($tpl['company_name']) ?>
                    </div>
                    <div style="font-size: 11.5px; color: #4B5563; margin-top: 2px;">
                        <?= htmlspecialchars($tpl['company_address']) ?>
                    </div>
                    <?php if ($hasGst): ?>
                        <div style="font-size: 11.5px; font-weight: 700; color: #166534; margin-top: 2px;"><?= htmlspecialchars($tpl['tax_id']) ?></div>
                    <?php endif; ?>
                </div>

                <div style="text-align: right;">
                    <div class="doc-title"><?= $hasGst ? 'TAX INVOICE' : 'INVOICE' ?></div>
                    <div style="font-size: 12.5px; color: #4B5563; margin-top: 4px;">Invoice No: <strong style="color: #166534;"><?= htmlspecialchars($invoiceNumber) ?></strong></div>
                    <div style="font-size: 12px; color: #6B7280;">Date: <?= $invoiceDate ?></div>
                    <div style="font-size: 12px; color: #6B7280;">Order: <?= htmlspecialchars($order['order_number']) ?></div>
                </div>
            </div>

            <!-- Luxury Customer Box -->
            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 14px 18px; margin-bottom: 22px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 12.5px;">
                <div>
                    <span style="font-family: 'Cormorant Garamond', serif; font-size: 14px; font-weight: 700; color: #166534; text-transform: uppercase;">Billed Recipient</span>
                    <div style="font-weight: 700; color: #14221A; margin-top: 2px;"><?= htmlspecialchars($order['shipping_name'] ?? 'Valued Customer') ?></div>
                    <div style="color: #4B5563;"><?= htmlspecialchars($order['shipping_phone'] ?? '') ?> &bull; <?= htmlspecialchars($order['shipping_email'] ?? '') ?></div>
                </div>
                <div>
                    <span style="font-family: 'Cormorant Garamond', serif; font-size: 14px; font-weight: 700; color: #166534; text-transform: uppercase;">Delivery Destination</span>
                    <div style="color: #4B5563; margin-top: 2px;">
                        <?= htmlspecialchars($order['shipping_address_line1'] ?? '') ?>, <?= htmlspecialchars($order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['shipping_postal_code'] ?? '') ?>
                    </div>
                </div>
            </div>

            <!-- Luxury Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th>Botanical Product Selection</th>
                        <th style="width: 60px; text-align: center;">Qty</th>
                        <th style="width: 100px; text-align: right;">Unit Rate</th>
                        <th style="width: 120px; text-align: right;">Amount (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($items as $item): 
                        $rate = $item['unit_price_amount'] / 100;
                        $amt = ($item['total_amount'] ?? ($rate * $item['quantity'] * 100)) / 100;
                    ?>
                    <tr>
                        <td style="text-align: center; color: #166534; font-family: 'Cormorant Garamond', serif; font-size: 15px;"><?= $i++ ?></td>
                        <td>
                            <strong style="color: #14221A;"><?= htmlspecialchars($item['title']) ?></strong>
                            <div style="font-size: 11px; color: #166534; font-style: italic;">Natural Extract &bull; <?= htmlspecialchars($item['sku'] ?? 'NUX-001') ?></div>
                        </td>
                        <td style="text-align: center; font-weight: 700;"><?= (int)$item['quantity'] ?></td>
                        <td style="text-align: right;">₹<?= number_format($rate, 2) ?></td>
                        <td style="text-align: right; font-weight: 700; color: #166534;">₹<?= number_format($amt, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Luxury Totals -->
            <div style="display: flex; justify-content: flex-end; margin: 20px 0;">
                <div style="width: 250px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #4B5563;">
                        <span>Subtotal</span>
                        <span>₹<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <?php if ($hasGst): ?>
                    <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #4B5563;">
                        <span>GST (5%)</span>
                        <span>₹<?= number_format($gstAmount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-top: 1.5px solid #166534; border-bottom: 1.5px solid #166534; font-weight: 800; font-size: 16px; color: #166534; margin-top: 6px;">
                        <span>Total Payable</span>
                        <span>₹<?= number_format($grandTotal, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Luxury Footer & Signatory (NO LEAF WATERMARK) -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px; border-top: 1px solid #BBF7D0; padding-top: 16px;">
                <div style="font-size: 12px; color: #166534; font-family: 'Cormorant Garamond', Georgia, serif; font-size: 15px; font-style: italic;">
                    Thank you for choosing Nutronyx &mdash; Crafted with Pure Natural Goodness.
                </div>
                <div style="text-align: right; width: 180px;">
                    <?php if ($sigSrc): ?>
                        <img src="<?= htmlspecialchars($sigSrc) ?>" alt="Signature" style="max-height: 48px; max-width: 150px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                    <?php endif; ?>
                    <div style="height: 30px; border-bottom: 1px dashed #166534; margin-bottom: 4px;"></div>
                    <strong style="color: #14221A; font-size: 12px;"><?= htmlspecialchars($tpl['signatory_name'] ?? 'Authorized Signatory') ?></strong>
                    <div style="font-size: 10.5px; color: #6B7280;"><?= htmlspecialchars($tpl['signatory_title'] ?? 'Compliance & Quality Officer') ?></div>
                </div>
            </div>

        <?php else: ?>
            <!-- ================= DEFAULT: CLASSIC EXECUTIVE ================= -->
            <div class="header-bar">
                <div>
                    <?php if (!empty($tpl['show_logo']) && !empty($tpl['logo_url'])): ?>
                        <div style="margin-bottom: 8px;">
                            <img src="../<?= htmlspecialchars($tpl['logo_url']) ?>" alt="Logo" style="max-height: <?= (int)($tpl['logo_height'] ?? 44) ?>px; object-fit: contain;">
                        </div>
                    <?php endif; ?>

                    <div class="brand-title"><?= htmlspecialchars(!empty($tpl['brand_name']) ? $tpl['brand_name'] : 'Nutronyx') ?></div>
                    <div style="font-size: 13px; color: #586B5F; margin-top: 4px; line-height: 1.45;">
                        <strong><?= htmlspecialchars($tpl['company_name']) ?></strong><br>
                        <?= nl2br(htmlspecialchars($tpl['company_address'])) ?><br>
                        Email: <?= htmlspecialchars($tpl['contact_email']) ?> &bull; Tel: <?= htmlspecialchars($tpl['contact_phone']) ?>
                        <?php if ($hasGst): ?><br><span style="font-weight: 700; color: #144D29;"><?= htmlspecialchars($tpl['tax_id']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div style="text-align: right;">
                    <div style="font-size: 20px; font-weight: 800; color: #144D29; text-transform: uppercase; letter-spacing: 1px;">
                        <?= $hasGst ? 'TAX INVOICE' : 'INVOICE / BILL OF SUPPLY' ?>
                    </div>
                    <div style="font-size: 13px; color: #586B5F; margin-top: 4px;">Invoice No: <strong><?= htmlspecialchars($invoiceNumber) ?></strong></div>
                    <div style="font-size: 13px; color: #586B5F;">Date: <strong><?= $invoiceDate ?></strong></div>
                    <div style="font-size: 13px; color: #586B5F;">Order Ref: <strong><?= htmlspecialchars($order['order_number']) ?></strong></div>
                </div>
            </div>

            <!-- Classic Address Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; padding: 14px 18px; background: #F8FAF9; border-radius: 6px; border: 1px solid #D8DFDB; font-size: 13px;">
                <div>
                    <h4 style="margin: 0 0 6px 0; font-size: 11px; text-transform: uppercase; color: #144D29; font-weight: 700;">Billed &amp; Shipped To:</h4>
                    <strong><?= htmlspecialchars($order['shipping_name'] ?? 'Valued Customer') ?></strong><br>
                    <?= htmlspecialchars($order['shipping_address_line1'] ?? '') ?>, <?= htmlspecialchars($order['shipping_city'] ?? '') ?><br>
                    <?= htmlspecialchars($order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['shipping_postal_code'] ?? '') ?><br>
                    Phone: <?= htmlspecialchars($order['shipping_phone'] ?? 'N/A') ?>
                </div>
                <div>
                    <h4 style="margin: 0 0 6px 0; font-size: 11px; text-transform: uppercase; color: #144D29; font-weight: 700;">Order &amp; Fulfillment:</h4>
                    <strong>Payment Mode:</strong> <?= htmlspecialchars($order['payment_status'] === 'PAID' ? 'Online Paid (UPI/Card)' : 'Cash on Delivery') ?><br>
                    <strong>Payment Status:</strong> <?= htmlspecialchars($order['payment_status']) ?><br>
                    <strong>Shipping Partner:</strong> Express Logistics Partner
                </div>
            </div>

            <!-- Classic Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th>Product Description / SKU</th>
                        <th style="width: 70px; text-align: center;">Qty</th>
                        <th style="width: 120px; text-align: right;">Unit Price</th>
                        <th style="width: 130px; text-align: right;">Total (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($items as $item): 
                        $rate = $item['unit_price_amount'] / 100;
                        $amt = ($item['total_amount'] ?? ($rate * $item['quantity'] * 100)) / 100;
                    ?>
                    <tr>
                        <td style="text-align: center; color: #586B5F;"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['title']) ?></strong>
                            <div style="font-size: 11px; color: #586B5F;">SKU: <?= htmlspecialchars($item['sku'] ?? 'NUX-GEN') ?></div>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?= (int)$item['quantity'] ?></td>
                        <td style="text-align: right;">₹<?= number_format($rate, 2) ?></td>
                        <td style="text-align: right; font-weight: 700;">₹<?= number_format($amt, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Classic Totals -->
            <div style="display: flex; justify-content: flex-end; margin-bottom: 24px;">
                <table style="width: 300px; border-collapse: collapse; font-size: 13.5px;">
                    <tr>
                        <td style="padding: 6px 8px; color: #586B5F;">Subtotal:</td>
                        <td style="padding: 6px 8px; text-align: right; font-weight: 600;">₹<?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <?php if ($hasGst): ?>
                    <tr>
                        <td style="padding: 6px 8px; color: #586B5F;">GST (5%):</td>
                        <td style="padding: 6px 8px; text-align: right; font-weight: 600;">₹<?= number_format($gstAmount, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="border-top: 2px solid #144D29; border-bottom: 2px solid #144D29; background: #F8FAF9; font-weight: 800; font-size: 15px; color: #144D29;">
                        <td style="padding: 8px 8px;">Grand Total:</td>
                        <td style="padding: 8px 8px; text-align: right;">₹<?= number_format($grandTotal, 2) ?></td>
                    </tr>
                </table>
            </div>

            <!-- Classic Signatory & Terms (NO LEAF WATERMARK) -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #D8DFDB; padding-top: 16px; font-size: 12px; color: #586B5F;">
                <div>
                    <strong>Terms &amp; Conditions:</strong><br>
                    1. Goods once sold are covered under Nutronyx 7-day Quality Guarantee.<br>
                    2. Computer generated document. Subject to jurisdiction.
                </div>
                <div style="text-align: right; width: 180px;">
                    <?php if ($sigSrc): ?>
                        <img src="<?= htmlspecialchars($sigSrc) ?>" alt="Signature" style="max-height: 48px; max-width: 150px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                    <?php endif; ?>
                    <div style="height: 32px; border-bottom: 1px dashed #144D29; margin-bottom: 4px;"></div>
                    <strong style="color: #14221A; font-size: 12px;"><?= htmlspecialchars($tpl['signatory_name'] ?? 'Authorized Signatory') ?></strong><br>
                    <span style="font-size: 11px;"><?= htmlspecialchars($tpl['signatory_title'] ?? 'Compliance & Accounts Officer') ?></span>
                </div>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
