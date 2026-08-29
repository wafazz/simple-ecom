<?php

/**
 * Order relay — deployed to https://penangfc.kopaarena.com/send-email.php
 *
 * NOT part of the Laravel application and NOT running on the shop's server.
 * The shop's VPS blocks outbound SMTP, so it POSTs the order here and this
 * host — which can open an SMTP connection — sends the confirmation.
 *
 * TARGET: PHP 8.1, cPanel, no Composer. PHPMailer sits beside this file and is
 * required by path. Nothing here needs an autoloader or a framework, so the
 * whole thing can be uploaded through File Manager and work:
 *
 *   public_html/send-email.php          <- this file
 *   public_html/PHPMailer/src/PHPMailer.php
 *   public_html/PHPMailer/src/SMTP.php
 *   public_html/PHPMailer/src/Exception.php
 *
 * Kept in the shop's repository so the two halves stay in step. The sending
 * side is app/Services/OrderRelayService.php.
 *
 * ------------------------------------------------------------ the order POST
 *
 *   customer_name   string   "Ahmad Faiz"
 *   customer_email  string   where it goes
 *   order_no        string   "ORD-20260828-0001"
 *   purchase_date   string   already formatted, e.g. "28 Aug 2026, 14:30"
 *   currency        string   "RM"
 *   items           array    [{name, variation, nameset, qty, unit_price, total}]
 *   total           string   items total, before delivery — "195.00"
 *   delivery_cost   string   "8.00"
 *   grand_total     string   "203.00"
 *
 * Amounts arrive as decimal STRINGS, already rounded. Nothing here does
 * arithmetic on money — it prints what it was given.
 *
 * The original plain-message form still works: send `to`, `subject` and
 * `message` and it is wrapped in the house template as before. An order POST
 * is recognised by `order_no` being present.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

// ============================================================================
// CONFIGURATION — edit these four blocks and nothing else
// ============================================================================

/**
 * The shared secret. Must match ORDER_RELAY_TOKEN in the shop's .env.
 *
 * Left empty this script still works and stays open to anyone who finds the
 * URL — which is an open mail relay, and spammers do find them, usually within
 * days of the path turning up in a log. SET IT.
 *
 * Any long random string will do. If you have SSH on either box:
 *   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 */
const RELAY_TOKEN = '';

/**
 * The mailbox this cPanel account sends as.
 *
 * The mailbox is local to this account, so 'localhost' works too and keeps the
 * connection inside the machine — no DNS lookup and no external hop. Use
 * whichever cPanel shows under Email Accounts -> Connect Devices.
 */
const SMTP_HOST = 'mail.kopaarena.com';
const SMTP_USERNAME = 'hallo@kopaarena.com';
const SMTP_PASSWORD = '';
const SMTP_PORT = 465;

/** The name a buyer sees the message is from. */
const FROM_NAME = 'Kopa Arena';

/** A relay with no ceiling is a relay somebody else will use. */
const MAX_BODY_BYTES = 524288; // 512 KB

// ============================================================================
// HELPERS
// ============================================================================

/**
 * @param  array<string, mixed>  $extra
 * @return never
 */
function respond(int $status, bool $success, string $message, array $extra = [])
{
    http_response_code($status);

    echo json_encode(
        ['success' => $success, 'message' => $message] + $extra,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/** Shorthand for the endless isset() dance on an untrusted array. */
function field(array $data, string $key, string $default = ''): string
{
    return isset($data[$key]) && is_scalar($data[$key]) ? trim((string) $data[$key]) : $default;
}

/** Escape for HTML. Every value printed below goes through this. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Headers are single-line by definition, so a CR or LF in one is either a
 * mistake or an attempt to append a second header. Neither should be sent.
 */
function singleLine(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $value));
}

/**
 * The order confirmation.
 *
 * Table layout with inline styles throughout, because email clients strip
 * <style> blocks and Outlook does not do flexbox. Deliberately plain.
 *
 * @param  array<string, mixed>  $data
 */
function renderOrder(array $data): string
{
    $currency = field($data, 'currency', 'RM');
    $customer = field($data, 'customer_name', 'there');
    $orderNo = field($data, 'order_no');
    $date = field($data, 'purchase_date');

    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    $rows = '';

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        $label = e(field($item, 'name'));

        $variation = field($item, 'variation');
        if ($variation !== '') {
            $label .= ' <span style="color:#777;">('.e($variation).')</span>';
        }

        // Printed to order, so the buyer has to be able to check the spelling
        // while a mistake is still worth reporting.
        $nameset = field($item, 'nameset');
        if ($nameset !== '') {
            $label .= '<br><em style="color:#777;font-size:13px;">Nameset: '.e($nameset).'</em>';
        }

        $rows .= '
            <tr>
                <td style="padding:10px 8px;border-bottom:1px solid #eee;">'.$label.'</td>
                <td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:center;">'.e(field($item, 'qty')).'</td>
                <td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap;">'.e($currency).e(field($item, 'total')).'</td>
            </tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="3" style="padding:10px 8px;color:#777;">No items listed.</td></tr>';
    }

    $summary = '';

    foreach ([
        'Total' => field($data, 'total'),
        'Delivery' => field($data, 'delivery_cost'),
    ] as $label => $amount) {
        if ($amount === '') {
            continue;
        }

        $summary .= '
            <tr>
                <td colspan="2" style="padding:6px 8px;text-align:right;color:#555;">'.e($label).'</td>
                <td style="padding:6px 8px;text-align:right;white-space:nowrap;">'.e($currency).e($amount).'</td>
            </tr>';
    }

    $grand = field($data, 'grand_total');

    if ($grand !== '') {
        $summary .= '
            <tr>
                <td colspan="2" style="padding:10px 8px;text-align:right;border-top:2px solid #333;"><strong>Grand total</strong></td>
                <td style="padding:10px 8px;text-align:right;border-top:2px solid #333;white-space:nowrap;"><strong>'.e($currency).e($grand).'</strong></td>
            </tr>';
    }

    $meta = '';

    if ($orderNo !== '') {
        $meta .= '<p style="margin:4px 0;"><strong>Order number:</strong> '.e($orderNo).'</p>';
    }

    if ($date !== '') {
        $meta .= '<p style="margin:4px 0;"><strong>Date of purchase:</strong> '.e($date).'</p>';
    }

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:30px 12px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#222;">
    <div style="max-width:600px;margin:auto;background:#ffffff;padding:30px;border-radius:8px;">

        <h2 style="margin:0 0 20px;">'.e(FROM_NAME).'</h2>

        <p style="margin:0 0 6px;">Hello <strong>'.e($customer).'</strong>,</p>
        <p style="margin:0 0 20px;">Thank you — your order is confirmed and is being prepared.</p>

        '.$meta.'

        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;">
            <thead>
                <tr>
                    <th align="left" style="padding:8px;border-bottom:2px solid #333;">Item</th>
                    <th align="center" style="padding:8px;border-bottom:2px solid #333;">Qty</th>
                    <th align="right" style="padding:8px;border-bottom:2px solid #333;">Total</th>
                </tr>
            </thead>
            <tbody>'.$rows.$summary.'</tbody>
        </table>

        <p style="color:#777;font-size:13px;margin:20px 0 0;">
            Quote your order number if you need to write to us about this order.
        </p>

        <hr style="border:none;border-top:1px solid #eee;margin:24px 0 12px;">

        <p style="color:#777;font-size:13px;margin:0;">'.e(FROM_NAME).'</p>

    </div>
</body>
</html>';
}

/**
 * The plain-text alternative, built from the same values.
 *
 * Written out rather than stripped from the HTML above: strip_tags on a table
 * produces a column of unlabelled numbers, which is worse than nothing.
 *
 * @param  array<string, mixed>  $data
 */
function renderOrderText(array $data): string
{
    $currency = field($data, 'currency', 'RM');

    $lines = ['Hello '.field($data, 'customer_name', 'there').',', '', 'Your order is confirmed.', ''];

    if (field($data, 'order_no') !== '') {
        $lines[] = 'Order number: '.field($data, 'order_no');
    }

    if (field($data, 'purchase_date') !== '') {
        $lines[] = 'Date of purchase: '.field($data, 'purchase_date');
    }

    $lines[] = '';

    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        $line = '- '.field($item, 'name');

        if (field($item, 'variation') !== '') {
            $line .= ' ('.field($item, 'variation').')';
        }

        if (field($item, 'nameset') !== '') {
            $line .= ' [Nameset: '.field($item, 'nameset').']';
        }

        $lines[] = $line.' x'.field($item, 'qty').'  '.$currency.field($item, 'total');
    }

    $lines[] = '';

    foreach ([
        'Total' => field($data, 'total'),
        'Delivery' => field($data, 'delivery_cost'),
        'Grand total' => field($data, 'grand_total'),
    ] as $label => $amount) {
        if ($amount !== '') {
            $lines[] = $label.': '.$currency.$amount;
        }
    }

    $lines[] = '';
    $lines[] = FROM_NAME;

    return implode("\n", $lines);
}

/** The house template, for the original plain-message form. */
function wrapPlainText(string $name, string $message): string
{
    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:30px;">
    <div style="max-width:600px;margin:auto;background:#ffffff;padding:30px;border-radius:8px;">
        <h2>'.e(FROM_NAME).'</h2>
        <p>Hello <strong>'.e($name).'</strong>,</p>
        <div>'.nl2br(e($message)).'</div>
        <hr>
        <p style="color:#777;">'.e(FROM_NAME).'</p>
    </div>
</body>
</html>';
}

// ============================================================================
// REQUEST
// ============================================================================

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

// A GET is a health check and nothing else. The original script would send on
// one, which put the recipient and the whole message into the query string —
// and so into the cPanel access log and any proxy in between.
if ($method === 'GET') {
    respond(200, true, 'Relay is up. POST JSON to send.', ['ping' => true]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    respond(405, false, 'Method not allowed.');
}

$raw = file_get_contents('php://input');

if ($raw !== false && strlen($raw) > MAX_BODY_BYTES) {
    respond(413, false, 'Payload too large.');
}

if (! empty($_POST)) {
    $request = $_POST;
} else {
    $request = json_decode((string) $raw, true);

    if (! is_array($request)) {
        respond(400, false, 'Body must be JSON or form-encoded.');
    }
}

// ============================================================================
// AUTHENTICATION
// ============================================================================

if (RELAY_TOKEN !== '') {
    // Two accepted places, because this is shared hosting: LiteSpeed and some
    // cPanel PHP handlers drop unrecognised X- headers before PHP sees them,
    // and a relay that silently 401s everything gets blamed on the token. The
    // body is equally private — a POST body is not written to the access log.
    $supplied = '';

    if (isset($_SERVER['HTTP_X_RELAY_TOKEN']) && is_string($_SERVER['HTTP_X_RELAY_TOKEN'])) {
        $supplied = $_SERVER['HTTP_X_RELAY_TOKEN'];
    } elseif (isset($request['token']) && is_string($request['token'])) {
        $supplied = $request['token'];
    }

    // hash_equals, not ===, so the comparison does not leak the token one
    // character at a time through how long it takes to fail.
    if (! hash_equals(RELAY_TOKEN, $supplied)) {
        respond(401, false, 'Unauthorized.');
    }
}

// ============================================================================
// VALIDATION
// ============================================================================

// `to` is the original field name; an order POST carries customer_email.
$to = field($request, 'to');

if ($to === '') {
    $to = field($request, 'customer_email');
}

if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
    respond(400, false, 'A valid recipient email is required.');
}

$isOrder = field($request, 'order_no') !== '';

if ($isOrder) {
    $name = field($request, 'customer_name');
    $subject = field($request, 'subject');

    if ($subject === '') {
        $subject = 'Order '.field($request, 'order_no').' confirmed';
    }

    $html = renderOrder($request);
    $text = renderOrderText($request);
} else {
    // The original form: a plain message, wrapped in the house template.
    $name = field($request, 'name', 'Recipient');
    $subject = field($request, 'subject');
    $message = field($request, 'message');

    if ($message === '') {
        respond(400, false, 'A message is required.');
    }

    if ($subject === '') {
        $subject = 'Email from '.FROM_NAME;
    }

    $html = wrapPlainText($name, $message);
    $text = $message;
}

$subject = singleLine($subject);
$name = singleLine($name);

// ============================================================================
// SEND
// ============================================================================

require __DIR__.'/PHPMailer/src/Exception.php';
require __DIR__.'/PHPMailer/src/PHPMailer.php';
require __DIR__.'/PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->Port = SMTP_PORT;

    // 465 speaks TLS from the first byte; 587 and 2525 start plain and upgrade
    // with STARTTLS. Naming the wrong one hangs until the timeout.
    $mail->SMTPSecure = SMTP_PORT === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    // The shop is waiting on this response inside a payment callback, and
    // shared hosting is slow under load. Bounded, not generous.
    $mail->Timeout = 20;

    // Fixed to the authenticated mailbox: this account may only send as
    // itself, and a From it does not own is refused by the SMTP server and
    // fails the recipient's SPF check.
    $mail->setFrom(SMTP_USERNAME, FROM_NAME);
    $mail->addAddress($to, $name);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;

    $mail->send();

    respond(200, true, 'Email sent successfully.', [
        'data' => [
            'to' => $to,
            'subject' => $subject,
            'order_no' => field($request, 'order_no'),
        ],
    ]);
} catch (Exception $e) {
    // ErrorInfo carries the SMTP server's own words. Without them a failure is
    // unactionable — "535 authentication failed" and "connection timed out"
    // need completely different fixes, and the shop logs this string.
    respond(500, false, 'Email failed.', [
        'error' => $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(),
    ]);
}
