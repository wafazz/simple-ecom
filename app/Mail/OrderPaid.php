<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the buyer once payment has actually been verified. */
class OrderPaid extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        // The order number leads: it is what the customer quotes back when
        // they write in, and what they search their inbox for.
        return new Envelope(subject: 'Order '.$this->order->order_no.' confirmed');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-paid', with: [
            'order' => $this->order,
            'symbol' => config('shop.currency_symbol'),
            'trackUrl' => route('order-status.show'),
            // The admin-set shop name, not APP_NAME — that is a deployment
            // label, and the customer only recognises the shop.
            'storeName' => Setting::get('store_name') ?: config('app.name'),
        ]);
    }
}
