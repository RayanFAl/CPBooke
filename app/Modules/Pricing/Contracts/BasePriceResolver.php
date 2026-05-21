<?php

namespace App\Modules\Pricing\Contracts;

use App\Models\User;
use App\Modules\Pricing\DTO\BasePriceQuote;

interface BasePriceResolver
{
    public function supports(string $serviceType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(User $user, array $payload): BasePriceQuote;
}