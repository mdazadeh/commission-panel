<?php

namespace App\Domain\Commission;

/** Calculation output: every component is traceable (code, label, base, rate, amount, formula). */
final class Result
{
    /** @var array<string, array{label:string, value:string, base?:string, rate?:string, formula?:string, rule?:string}> */
    public array $components = [];

    /** @var list<array{code:string, message:string}> */
    public array $flags = [];

    /** @var list<array{document_id:int, category:string, net:string, cash:string, credit:string, rate:string, commission:string, note:?string}> */
    public array $lines = [];

    public string $salesTotal = '0';

    public string $targetTotal = '0';

    public string $achievement = '0';

    public string $tierRate = '0';

    public string $final = '0';

    public string $payable = '0';

    /** @var array{above:string, rate:string, sales_needed:string}|null */
    public ?array $nextTier = null;

    public function add(string $code, string $label, string $value, array $extra = []): void
    {
        $this->components[$code] = ['label' => $label, 'value' => $value] + $extra;
    }

    public function flag(string $code, string $message): void
    {
        $this->flags[] = ['code' => $code, 'message' => $message];
    }
}
