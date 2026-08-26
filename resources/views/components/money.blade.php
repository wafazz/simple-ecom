@props(['minor', 'currency' => null])

{{-- Single rendering point for money, so no view ever divides by 100 itself
     and no float enters a template (Planning §12.1). --}}
<span class="money">{{ \App\Support\Money::display((int) $minor, $currency ?? config('shop.currency_symbol')) }}</span>
