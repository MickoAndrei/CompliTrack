<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use MongoDB\BSON\ObjectId as BSONObjectId;
use MongoDB\Laravel\Eloquent\Model;

class NullableObjectId implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof BSONObjectId ? (string) $value : (string) $value;
    }

    public function set($model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof BSONObjectId ? $value : new BSONObjectId((string) $value);
    }
}
