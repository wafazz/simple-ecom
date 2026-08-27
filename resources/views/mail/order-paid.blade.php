{{-- Laravel's <x-mail::message> prints config('app.name') in the header and
     footer. The shop's own name is what the customer recognises, and it is
     admin-editable, so the layout is composed here with that instead. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $storeName }}
</x-mail::header>
</x-slot:header>

# Thank you — your order is confirmed

We have received your payment and your order is being prepared.

**Order number:** {{ $order->order_no }}
**Placed:** {{ $order->created_at->format('j M Y, H:i') }}

<x-mail::table>
| Item | Qty | Amount |
|:-----|:---:|-------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}{{ $item->variation_label !== '' ? ' ('.$item->variation_label.')' : '' }}@if ($item->hasNameset()) <br>*Nameset: {{ $item->namesetLabel() }}*@endif | {{ $item->qty }} | {{ $symbol }}{{ \App\Support\Money::format($item->line_total_minor) }} |
@endforeach
| **Subtotal** | | {{ $symbol }}{{ \App\Support\Money::format($order->subtotal_minor) }} |
| **Delivery** | | {{ $symbol }}{{ \App\Support\Money::format($order->shipping_fee_minor) }} |
| **Total paid** | | **{{ $symbol }}{{ \App\Support\Money::format($order->grand_total_minor) }}** |
</x-mail::table>

**Delivering to**
{{ $order->customer_name }}
{{ $order->address_line }}
{{ $order->postcode }} {{ $order->city }}
{{ $order->state }}

We will email you again if anything changes. You can check your order at any
time using the order number above and the email address this was sent to.

<x-mail::button :url="$trackUrl">
Track this order
</x-mail::button>

@if ($order->items->contains(fn ($item) => $item->hasNameset()))
Printed namesets are made to your order, so please check the spelling and number
above. Tell us straight away if anything is wrong.
@endif

Thanks,<br>
{{ $storeName }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $storeName }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
