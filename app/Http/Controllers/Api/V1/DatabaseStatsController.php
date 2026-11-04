<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DatabaseStatsController extends Controller
{
    public function __invoke()
    {
        $connection = DB::connection('mongodb');
        $database = $connection->getMongoDB();
        $databaseStats = $database->command(['dbStats' => 1])->toArray()[0];
        $usersStats = $database->command(['collStats' => (new User)->getTable()])->toArray()[0];

        return response()->json([
            'data' => [
                'database' => $connection->getDatabaseName(),
                'databaseStats' => [
                    'collections' => (int) ($databaseStats->collections ?? 0),
                    'objects' => (int) ($databaseStats->objects ?? 0),
                    'dataSizeBytes' => (int) ($databaseStats->dataSize ?? 0),
                    'storageSizeBytes' => (int) ($databaseStats->storageSize ?? 0),
                    'indexSizeBytes' => (int) ($databaseStats->indexSize ?? 0),
                ],
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('isActive', true)->count(),
                    'dataSizeBytes' => (int) ($usersStats->size ?? 0),
                    'storageSizeBytes' => (int) ($usersStats->storageSize ?? 0),
                    'indexSizeBytes' => (int) ($usersStats->totalIndexSize ?? $usersStats->indexSize ?? 0),
                ],
            ],
        ]);
    }
}
