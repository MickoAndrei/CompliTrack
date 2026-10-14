<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use MongoDB\BSON\ObjectId;

class MongoId
{
    public static function parse(string $id, string $field = 'id'): ObjectId
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
            throw ValidationException::withMessages([
                $field => 'The identifier must be a 24-character lowercase hexadecimal ObjectId.',
            ]);
        }

        return new ObjectId($id);
    }

    public static function strings(array $ids): array
    {
        return array_map(
            static fn ($id) => $id instanceof ObjectId ? (string) $id : (string) $id,
            $ids,
        );
    }
}
