<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MailSettingRequest;
use App\Mail\OrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Support\IntegrationConfig;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Outgoing mail, over SMTP.
 *
 * Provider-agnostic on purpose: a transactional service or the shop's own
 * mailbox both configure the same way. The credentials go into secure_settings
 * encrypted, like every other credential here; the host, port and sender are
 * not secrets and live in settings. The password is never rendered back — the
 * form shows whether one is stored, not what it is.
 */
class MailController extends Controller
{
    public function edit(): View
    {
        return view('admin.mail', [
            'host' => MailSettings::host(),
            'port' => (string) MailSettings::port(),
            'ports' => MailSettings::PORTS,
            'username' => MailSettings::username(),
            'fromAddress' => MailSettings::fromAddress(),
            'fromName' => MailSettings::fromName(),
            'passwordSet' => filled(IntegrationConfig::get('mail.smtp_password')),
            'configured' => MailSettings::isConfigured(),
            'passwordSource' => IntegrationConfig::source('mail.smtp_password'),
        ]);
    }

    public function update(MailSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Setting::put('mail_smtp_host', $data['mail_smtp_host']);
        Setting::put('mail_smtp_port', $data['mail_smtp_port']);
        Setting::put('mail_from_address', $data['mail_from_address']);
        Setting::put('mail_from_name', $data['mail_from_name']);

        if (filled($data['smtp_username'] ?? null)) {
            IntegrationConfig::put('mail.smtp_username', $data['smtp_username']);
        }

        // Blank leaves the stored password alone: the form never shows it, so
        // an empty box means "unchanged", not "delete".
        if (filled($data['smtp_password'] ?? null)) {
            IntegrationConfig::put('mail.smtp_password', $data['smtp_password']);
        }

        Log::info('Mail settings updated', [
            'host' => $data['mail_smtp_host'],
            'port' => $data['mail_smtp_port'],
            'password_changed' => filled($data['smtp_password'] ?? null),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('admin.mail.edit')->with('status', 'Mail settings saved.');
    }

    /**
     * Send a real email and report what happened.
     *
     * A real send, not a handshake: a server will accept a connection with a
     * good password and still refuse the message — a sender it does not own,
     * an unverified domain, a suspended account. Only sending finds those.
     */
    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_to' => ['required', 'email', 'max:255'],
        ]);

        if (! MailSettings::isConfigured()) {
            return back()->with('test_result', [
                'provider' => 'smtp',
                'ok' => false,
                'summary' => 'Not configured yet — a server, a username, a password and a sender address are all needed before anything can be sent.',
            ]);
        }

        try {
            // The real confirmation, so the test proves the transport AND shows
            // the admin exactly what a customer receives.
            Mail::to($data['test_to'])->send(
                new OrderPaid($this->sampleOrder(), sample: true),
            );

            $ok = true;
            $summary = 'Sent to '.$data['test_to'].'. If it does not arrive within a minute or two, check the spam folder and then the Mailgun logs.';
            $raw = null;
        } catch (Throwable $e) {
            $ok = false;
            $summary = 'The message could not be sent.';
            // The transport's own words. Without them a failure is
            // unactionable — "530 authentication required" and "connection
            // timed out" need completely different fixes.
            $raw = mb_substr($e->getMessage(), 0, 400);
        }

        Log::info('Mail test', [
            'ok' => $ok,
            'to' => $data['test_to'],
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('test_result', array_filter([
            'provider' => 'smtp',
            'ok' => $ok,
            'summary' => $summary,
            'raw' => $raw,
            'note' => $ok ? null : $this->hintFor($raw),
        ], fn ($v): bool => $v !== null));
    }

    /**
     * A believable order that is never saved.
     *
     * Built in memory rather than read from the orders table: a real order
     * would put a real customer's name and address into a message sent to
     * whatever address was typed into the box, and a shop with no orders yet
     * could not test at all.
     *
     * Nothing here touches the database, so no row, no order number and no
     * stock movement follows from pressing the button.
     */
    private function sampleOrder(): Order
    {
        $order = new Order([
            'order_no' => 'ORD-'.now()->format('Ymd').'-0001',
            'customer_name' => 'Ahmad Faiz',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '0123456789',
            'address_line' => '12 Jalan Contoh',
            'city' => 'George Town',
            'state' => 'MY-07',
            'postcode' => '10450',
            'country' => 'MY',
        ]);

        // Totals are guarded out of $fillable on the real model, so they are
        // set the same way the checkout sets them.
        $order->forceFill([
            'subtotal_minor' => 19500,
            'shipping_fee_minor' => 800,
            'grand_total_minor' => 20300,
            'created_at' => now(),
        ]);

        // Two lines on purpose: one plain, one printed, so the admin sees how
        // a nameset appears before a customer ever does.
        $items = collect([
            new OrderItem([
                'product_name' => 'Home Kit 2026/27',
                'variation_label' => 'M',
                'sku' => 'SAMPLE-M',
                'nameset_name' => 'AZLAN',
                'nameset_number' => '10',
                'nameset_price_minor' => 1500,
                'unit_price_minor' => 9000,
                'qty' => 1,
                'line_total_minor' => 10500,
            ]),
            new OrderItem([
                'product_name' => 'Training Tee',
                'variation_label' => 'L',
                'sku' => 'SAMPLE-L',
                'unit_price_minor' => 4500,
                'qty' => 2,
                'line_total_minor' => 9000,
            ]),
        ]);

        // Set rather than loaded: an unsaved order has nothing to load.
        $order->setRelation('items', $items);

        return $order;
    }

    /** Turn the common transport failures into something to actually do. */
    private function hintFor(?string $error): ?string
    {
        $error = mb_strtolower((string) $error);

        return match (true) {
            str_contains($error, 'authentication') || str_contains($error, '535') => 'The username or password was rejected. The username is usually the full email address. If your provider issues a separate SMTP password, use that rather than the account password.',
            str_contains($error, 'timed out') || str_contains($error, 'connection refused') => 'Nothing answered on that port. Many hosts block 587 outbound — try 2525, or 465 if your provider lists it. Check the server name is right as well.',
            str_contains($error, 'certificate') || str_contains($error, 'ssl') || str_contains($error, 'tls') => 'The secure connection failed. Port 465 expects TLS immediately while 587 and 2525 upgrade to it — using the wrong one for your server fails here. Check which the provider asks for.',
            str_contains($error, 'not allowed') || str_contains($error, 'forbidden')
                || str_contains($error, 'relay') || str_contains($error, '403') || str_contains($error, '550') => 'The login worked but the message was refused. Most servers only let you send FROM the address you logged in as — set the sender to match the username, or to an address that account is allowed to send for.',
            default => null,
        };
    }
}
