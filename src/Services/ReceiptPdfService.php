<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Context;

/**
 * ReceiptPdfService
 * 
 * Generates standards-compliant PDF 1.4 documents for POS receipts
 * Zero external dependencies — pure PHP implementation with exact point metrics,
 * thermal receipt formatting (80mm standard), clean typography and barcodes.
 */
class ReceiptPdfService
{
    private array $offsets = [];
    private string $buffer = '';
    private int $objCount = 0;

    private function out(string $s): void
    {
        $this->buffer .= $s . "\n";
    }

    private function newObj(): int
    {
        $this->objCount++;
        $this->offsets[$this->objCount] = strlen($this->buffer);
        $this->out($this->objCount . " 0 obj");
        return $this->objCount;
    }

    /**
     * Escape special PDF string characters: \ ( )
     */
    private static function escapePdf(string $str): string
    {
        // Replace non-ASCII and special characters for standard PDF Type 1 font
        $str = str_replace(['₹', 'Rs.'], 'Rs.', $str);
        $str = str_replace(['•', '●'], '*', $str);
        $str = str_replace(['–', '—'], '-', $str);
        // Replace accented/unicode with safe ASCII
        $clean = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        if ($clean !== false) {
            $str = $clean;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $str);
    }

    /**
     * Generate PDF binary string from structured receipt lines
     */
    public function render(array $lines, float $widthMm = 80, ?float $dynamicHeightMm = null): string
    {
        $this->buffer = "%PDF-1.4\n";
        $wPt = $widthMm * 72 / 25.4; // 80mm = ~226.77 pt

        // Estimate height if not explicitly provided
        if ($dynamicHeightMm === null) {
            $totalPt = 60.0; // Top and bottom padding
            foreach ($lines as $item) {
                if (!empty($item['line'])) {
                    $totalPt += 12.0;
                } else {
                    $size = $item['size'] ?? 8.5;
                    $spacing = $item['spacing'] ?? 3.0;
                    $totalPt += ($size * 1.35 + $spacing);
                }
            }
            $hPt = max(380.0, $totalPt);
        } else {
            $hPt = $dynamicHeightMm * 72 / 25.4;
        }

        // Obj 1: Catalog
        $this->newObj();
        $this->out("<< /Type /Catalog /Pages 2 0 R >>");
        $this->out("endobj");

        // Obj 2: Pages Collection
        $this->newObj();
        $this->out("<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
        $this->out("endobj");

        // Build Content Stream
        $contentStream = "q\n";
        $y = $hPt - 24; // start from top
        $marginLeft = 14.0;
        $marginRight = 14.0;
        $usableW = $wPt - $marginLeft - $marginRight;

        foreach ($lines as $item) {
            if (!empty($item['line'])) {
                // Dashed dividing line
                $dashType = $item['dash'] ?? '[2 2] 0 d';
                $contentStream .= "0.5 w " . $dashType . " " . sprintf("%.2f", $marginLeft) . " " . sprintf("%.2f", $y) . " m " . sprintf("%.2f", $wPt - $marginRight) . " " . sprintf("%.2f", $y) . " l S [] 0 d\n";
                $y -= 10;
                continue;
            }

            $rawText = (string)($item['text'] ?? '');
            $text = self::escapePdf($rawText);
            $font = !empty($item['bold']) ? "/F2" : "/F1";
            $size = (float)($item['size'] ?? 8.5);
            $align = $item['align'] ?? 'left';
            $spacing = (float)($item['spacing'] ?? 3);

            // Courier is monospaced: 0.6 * size
            $charW = $size * 0.6;
            $textW = strlen($rawText) * $charW;

            if ($align === 'center') {
                $x = ($wPt - $textW) / 2;
                if ($x < $marginLeft) $x = $marginLeft;
            } elseif ($align === 'right') {
                $x = $wPt - $marginRight - $textW;
            } else {
                $x = $marginLeft;
            }

            $contentStream .= "BT\n$font " . sprintf("%.1f", $size) . " Tf\n" . sprintf("%.2f %.2f", $x, $y) . " Td\n($text) Tj\nET\n";
            $y -= ($size * 1.35 + $spacing);
        }
        $contentStream .= "Q\n";

        // Obj 3: Page Definition
        $this->newObj();
        $this->out("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . sprintf("%.2f %.2f", $wPt, $hPt) . "] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>");
        $this->out("endobj");

        // Obj 4: Regular Monospace Font (Courier)
        $this->newObj();
        $this->out("<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>");
        $this->out("endobj");

        // Obj 5: Bold Monospace Font (Courier-Bold)
        $this->newObj();
        $this->out("<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>");
        $this->out("endobj");

        // Obj 6: Content Stream
        $this->newObj();
        $this->out("<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream");
        $this->out("endobj");

        // Cross-Reference Table
        $xrefOffset = strlen($this->buffer);
        $this->out("xref");
        $this->out("0 " . ($this->objCount + 1));
        $this->out("0000000000 65535 f ");
        for ($i = 1; $i <= $this->objCount; $i++) {
            $this->out(sprintf("%010d 00000 n ", $this->offsets[$i]));
        }

        $this->out("trailer");
        $this->out("<< /Size " . ($this->objCount + 1) . " /Root 1 0 R >>");
        $this->out("startxref");
        $this->out((string)$xrefOffset);
        $this->out("%%EOF");

        return $this->buffer;
    }

    /**
     * Build standard POS lines array from Order and Template details
     */
    public static function buildLines(array $order, array $items, array $tpl = []): array
    {
        $activeWebsite = Context::getWebsite();
        $storeName = !empty($tpl['store_name']) ? $tpl['store_name'] : ($activeWebsite['name'] ?? 'NUTRONYX BOTANICALS');
        $receiptNumber = 'REC-' . strtoupper(substr(md5($order['order_number']), 0, 8));
        $orderDate = date('d M Y, h:i A', strtotime($order['created_at']));

        $lines = [];

        // Header Branding
        $lines[] = ['text' => strtoupper($storeName), 'bold' => true, 'size' => 12.5, 'align' => 'center'];
        if (($tpl['show_tagline'] ?? 1)) {
            $lines[] = ['text' => 'Pure Herbal Formulations & D2C Catalog', 'bold' => false, 'size' => 7.5, 'align' => 'center'];
        }
        if (($tpl['show_address'] ?? 1)) {
            $lines[] = ['text' => 'Main Rd, Rajahmundry, AP - 533101', 'bold' => false, 'size' => 7.5, 'align' => 'center'];
        }
        if (($tpl['show_phone'] ?? 1)) {
            $lines[] = ['text' => 'Tel: +91 98765 43210', 'bold' => false, 'size' => 7.5, 'align' => 'center'];
        }
        if (!empty($tpl['show_gstin']) && !empty($tpl['gstin_number'])) {
            $lines[] = ['text' => 'GSTIN: ' . $tpl['gstin_number'], 'bold' => false, 'size' => 7.5, 'align' => 'center'];
        }

        $lines[] = ['line' => true];

        // Metadata Block
        $lines[] = ['text' => sprintf("%-14s %s", "Receipt No:", $receiptNumber), 'bold' => true, 'size' => 8];
        $lines[] = ['text' => sprintf("%-14s %s", "Order ID:", $order['order_number']), 'bold' => false, 'size' => 8];
        $lines[] = ['text' => sprintf("%-14s %s", "Date/Time:", $orderDate), 'bold' => false, 'size' => 8];
        $lines[] = ['text' => sprintf("%-14s %s", "Cashier:", "Mohith (Staff Admin)"), 'bold' => false, 'size' => 8];

        if (($tpl['show_customer_info'] ?? 1)) {
            $lines[] = ['line' => true];
            $lines[] = ['text' => sprintf("%-14s %s", "Customer:", $order['shipping_name'] ?? 'Walk-in Customer'), 'bold' => false, 'size' => 8];
            if (!empty($order['shipping_phone'])) {
                $lines[] = ['text' => sprintf("%-14s %s", "Contact:", $order['shipping_phone']), 'bold' => false, 'size' => 8];
            }
            if (!empty($order['shipping_city'])) {
                $lines[] = ['text' => sprintf("%-14s %s, %s", "City/State:", $order['shipping_city'], $order['shipping_state'] ?? ''), 'bold' => false, 'size' => 8];
            }
        }

        $lines[] = ['line' => true];

        // Column Header
        $lines[] = ['text' => sprintf("%-4s %-18s %8s", "QTY", "ITEM", "TOTAL"), 'bold' => true, 'size' => 8.5];
        $lines[] = ['line' => true, 'dash' => '[1 1] 0 d'];

        // Line Items
        foreach ($items as $item) {
            $qty = (int)($item['quantity'] ?? 1);
            $rawTitle = $item['title'] ?? 'Product Item';
            $cleanTitle = substr($rawTitle, 0, 18);
            $itemTotal = number_format(($item['total_amount'] ?? 0) / 100);

            $lines[] = [
                'text' => sprintf("%-4s %-18s %8s", $qty . 'x', $cleanTitle, 'Rs.' . $itemTotal),
                'bold' => false,
                'size' => 8
            ];

            // If item has SKU or remaining title, add secondary sub-line
            $sku = $item['sku'] ?? '';
            if ($sku) {
                $lines[] = [
                    'text' => sprintf("     SKU: %s", substr($sku, 0, 24)),
                    'bold' => false,
                    'size' => 7,
                    'spacing' => 1
                ];
            }
        }

        $lines[] = ['line' => true];

        // Totals Calculation
        $subtotal = number_format(($order['subtotal_amount'] ?? 0) / 100);
        $shipping = ((int)($order['shipping_amount'] ?? 0) === 0) ? 'Free' : ('Rs.' . number_format(($order['shipping_amount'] ?? 0) / 100));
        $discount = number_format(($order['discount_amount'] ?? 0) / 100);
        $grandTotal = number_format(($order['grand_total_amount'] ?? 0) / 100);

        $lines[] = ['text' => sprintf("%-18s %13s", "Subtotal:", "Rs." . $subtotal), 'bold' => false, 'size' => 8, 'align' => 'right'];
        $lines[] = ['text' => sprintf("%-18s %13s", "Shipping:", $shipping), 'bold' => false, 'size' => 8, 'align' => 'right'];
        if ((int)($order['discount_amount'] ?? 0) > 0) {
            $lines[] = ['text' => sprintf("%-18s %13s", "Discount:", "-Rs." . $discount), 'bold' => false, 'size' => 8, 'align' => 'right'];
        }

        $lines[] = ['line' => true, 'dash' => '[] 0 d'];
        $lines[] = ['text' => sprintf("%-14s %14s", "TOTAL PAID:", "Rs." . $grandTotal), 'bold' => true, 'size' => 10, 'align' => 'right'];
        $lines[] = ['line' => true, 'dash' => '[] 0 d'];

        // Payment Info
        $payMethod = strtoupper($order['payment_method'] ?? 'ONLINE');
        $payStatus = strtoupper($order['payment_status'] ?? 'PAID');
        $lines[] = ['text' => "PAYMENT: $payMethod ($payStatus)", 'bold' => true, 'size' => 8, 'align' => 'center'];

        // Barcode Representation
        if (($tpl['show_barcode'] ?? 1)) {
            $lines[] = ['text' => "|||| ||| ||||| || |||||| | |||||", 'bold' => false, 'size' => 8, 'align' => 'center', 'spacing' => 1];
            $lines[] = ['text' => "*" . $order['order_number'] . "*", 'bold' => true, 'size' => 9, 'align' => 'center'];
        }

        // Footer Note
        $customNote = !empty($tpl['custom_footer_note']) ? $tpl['custom_footer_note'] : 'THANK YOU FOR SHOPPING WITH NUTRONYX';
        $lines[] = ['line' => true];
        $lines[] = ['text' => $customNote, 'bold' => true, 'size' => 8, 'align' => 'center'];
        $lines[] = ['text' => "Retain receipt for customer guarantee", 'bold' => false, 'size' => 7, 'align' => 'center'];
        $lines[] = ['text' => "support@nutronyx.com | www.nutronyx.com", 'bold' => false, 'size' => 7, 'align' => 'center'];

        return $lines;
    }

    /**
     * Stream PDF directly to client as an attachment download
     */
    public static function stream(array $order, array $items, array $tpl = []): void
    {
        $lines = self::buildLines($order, $items, $tpl);
        $service = new self();
        $pdfContent = $service->render($lines, 80);

        $safeNum = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($order['order_number'] ?? $order['id']));
        $filename = "Nutronyx-Receipt-{$safeNum}.pdf";

        // Clean any buffered output
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdfContent;
        exit;
    }
}
