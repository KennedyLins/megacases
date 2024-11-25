<?php

namespace Megacases\AbandonedCart\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCart extends Model
{

    protected $fillable = [
        'cart_id',
        'customer_id',
        'email',
        'abandoned_at',
        'is_mail_sent',
        'mail_sent_at',
    ];
}
