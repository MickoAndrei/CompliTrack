<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use MongoDB\BSON\ObjectId;

class ObjectIdArray implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): array
    {
        return array_map(
            static fn ($id) => $id instanceof ObjectId ? (string) $id : (string) $id,
            $value ?? [],
        );
    }

    public function set($model, string $key, mixed $value, array $attributes): array
    {
        return array_map(
            static fn ($id) => $id instanceof ObjectId ? $id : new ObjectId((string) $id),
            $value ?? [],
        );
    }
}
