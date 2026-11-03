<?php

namespace App\Domain\Commission;

final class DocumentInput
{
    public function __construct(
        public readonly int $id,
        public readonly string $category, // action_plan | rep_contract | rep_no_contract
        public readonly string $net,
        public readonly string $gross,
        public readonly string $discount,
        public readonly string $cash,
        public readonly string $credit,
        public readonly int $month, // Jalali month 1..12
        public readonly ?string $note = null,
    ) {}
}
