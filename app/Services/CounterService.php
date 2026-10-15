<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\Operation\FindOneAndUpdate;

class CounterService
{
    public function next(string $prefix, bool $yearly = false): string
    {
        $year = now()->utc()->format('Y');
        $counterId = $yearly ? "{$prefix}-{$year}" : $prefix;

        $counter = DB::connection('mongodb')
            ->getMongoDB()
            ->selectCollection('counters')
            ->findOneAndUpdate(
                ['_id' => $counterId],
                ['$inc' => ['seq' => 1]],
                [
                    'upsert' => true,
                    'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
                ],
            );

        $codePrefix = $yearly ? "{$prefix}-{$year}" : $prefix;

        return sprintf('%s-%04d', $codePrefix, $counter->seq);
    }
}
