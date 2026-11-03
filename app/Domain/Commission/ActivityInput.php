<?php

namespace App\Domain\Commission;

final class ActivityInput
{
    /** @param array<int, array<string, array{target:int, actual:int, status:string}>> $months month => indicator => entry */
    public function __construct(public readonly array $months) {}
}
