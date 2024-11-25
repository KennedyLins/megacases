<?php

namespace Megacases\AbandonedCart\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartEmailsCommand extends Command
{
    protected $signature = 'send:abandoned-cart-emails';

    protected $description = 'Send abandoned cart emails to customers';

    public function handle(): void
    {
        $enabled = core()->getConfigData('abandonedcart.settings.enable') ?? false;

        if (! $enabled) {
            return;
        }

        $abandonedTime = core()->getConfigData('abandonedcart.settings.abandoned_time') ?? 1;

        $carts = $this->abandonedCartRepository->scopeQuery(function ($query) use ($abandonedTime) {
            return $query->where('is_mail_sent', false)
                ->where('abandoned_at', '<=', Carbon::now()->subHours($abandonedTime));
        })->get();

        foreach ($carts as $abandonedCart) {
            // Check if the cart has been ordered
            $cart = \Webkul\Checkout\Models\Cart::find($abandonedCart->cart_id);

            if ($cart && !$cart->is_ordered) {
                // Send email
                Mail::send('abandonedcart::emails.reminder', ['cart' => $cart], function ($message) use ($cart) {
                    $message->to($cart->customer_email);
                    $message->subject(core()->getConfigData('abandonedcart.settings.email_subject') ?? 'You left items in your cart!');
                });

                // Update the record
                $this->abandonedCartRepository->update([
                    'is_mail_sent' => true,
                ], $abandonedCart->id);
            } else {
                // Remove the abandoned cart record if the cart is ordered or doesn't exist
                $this->abandonedCartRepository->delete($abandonedCart->id);
            }
        }
    }
}
