<!DOCTYPE html>
<html>
<head>
    <title>{{ core()->getConfigData('abandonedcart.settings.email_subject') ?? 'You left items in your cart!' }}</title>
</head>
<body>
<h1>Hello {{ $cart->customer_first_name ?? 'Customer' }},</h1>
<p>{{ core()->getConfigData('abandonedcart.settings.email_content') ?? 'We noticed that you left some items in your cart. Click the link below to complete your purchase:' }}</p>
<ul>
    @foreach ($cart->items as $item)
        <li>{{ $item->name }} - {{ $item->quantity }} x {{ core()->currency($item->price) }}</li>
    @endforeach
</ul>
<p>Total: {{ core()->currency($cart->grand_total) }}</p>
<a href="{{ route('shop.checkout.cart.index') }}">View Cart</a>
<p>Thank you!</p>
</body>
</html>
