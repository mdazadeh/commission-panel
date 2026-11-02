<?php

namespace App\Services\Imports;

use App\Models\ImportBatch;
use RuntimeException;

class DuplicateImportException extends RuntimeException
{
    public function __construct(public readonly ImportBatch $existing)
    {
        parent::__construct("این فایل قبلاً در تاریخ {$existing->created_at} بارگذاری شده است (Batch #{$existing->id}).");
    }
}
