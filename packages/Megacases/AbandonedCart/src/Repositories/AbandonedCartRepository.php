<?php

namespace Megacases\AbandonedCart\Repositories;

use Webkul\Core\Eloquent\Repository;

class AbandonedCartRepository extends Repository
{

    public function model(): string
    {
        return 'Megacases\AbandonedCart\Models\AbandonedCart';
    }
}
