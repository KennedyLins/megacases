<?php

namespace Megacases\AbandonedCart\Observers;

use AbandonedCartRepository;
use Carbon\Carbon;
use Prettus\Validator\Exceptions\ValidatorException;
use Webkul\Checkout\Models\Cart;

class CartObserver
{
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository
    ) {}

    /**
     * Handle the Cart "updated" event.
     *
     * @param Cart $cart
     * @return void
     * @throws ValidatorException
     */
    public function updated(Cart $cart): void
    {
        // Only proceed if the cart has items and is not already ordered
        if ($cart->items->count() > 0 && ! $cart->is_ordered) {
            $this->abandonedCartRepository->updateOrCreate(
                ['cart_id' => $cart->id],
                [
                    'customer_id'  => $cart->customer_id,
                    'email'        => $cart->customer_email,
                    'abandoned_at' => Carbon::now(),
                    'is_mail_sent' => false,
                ]
            );
        }
    }

    /**
     * Handle the Cart "deleted" event.
     *
     * @param Cart $cart
     * @return void
     */
    public function deleted(Cart $cart): void
    {
        // Remove the cart from abandoned carts if it gets deleted
        $this->abandonedCartRepository->deleteWhere(['cart_id' => $cart->id]);
    }
}
