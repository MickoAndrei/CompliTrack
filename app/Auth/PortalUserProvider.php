<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use MongoDB\Laravel\Eloquent\Builder;

class PortalUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        $query = parent::newModelQuery($model);

        // Keep identity and authorization fresh without transferring photo bytes
        // on every page/API request. The photo endpoint still loads the image.
        if ($query instanceof Builder && ! request()->is('api/v1/auth/avatar')) {
            $query->project(['avatarData' => 0]);
        }

        return $query;
    }
}
