<?php

namespace App\Domain\Commission;

final class PersonInput
{
    /**
     * @param  list<DocumentInput>  $documents
     * @param  list<int>  $periodMonths  the three Jalali months of the quarter
     * @param  string|null  $teamSales  for heads: total net sales of the team (D-09)
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
        public readonly string $quarterTarget,
        public readonly string $carriedOver,
        public readonly string $lostSales,
        public readonly string $priorReceivables,
        public readonly array $documents,
        public readonly ActivityInput $activity,
        public readonly array $periodMonths,
        public readonly ?string $teamSales = null,
    ) {}
}
