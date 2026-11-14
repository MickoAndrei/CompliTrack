<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UseTabSession
{
    public function handle(Request $request, Closure $next)
    {
        // This is a public context identifier, never a session ID or credential.
        $tab = $request->header('X-CompliTrack-Tab') ?? $request->query('_tab');
        if ($tab === null) {
            return $next($request);
        }
        abort_unless(is_string($tab) && preg_match('/\A[a-f0-9]{32}\z/D', $tab), 400, 'Invalid tab context.');

        $request->attributes->set('complitrack.tab', $tab);
        $cookie = config('session.cookie');
        config(['session.cookie' => $cookie.'_tab_'.$tab]);
        app('session')->driver()->setName(config('session.cookie'));

        try {
            $response = $next($request);
            if ($response->isRedirection() && $location = $response->headers->get('Location')) {
                $host = parse_url($location, PHP_URL_HOST);
                if ($host === null || $host === $request->getHost()) {
                    $response->headers->set('Location', $this->withTab($location, $tab));
                }
            }
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'same-origin');

            return $response;
        } finally {
            config(['session.cookie' => $cookie]);
            app('session')->driver()->setName($cookie);
        }
    }

    private function withTab(string $url, string $tab): string
    {
        $fragment = '';
        if (($position = strpos($url, '#')) !== false) {
            $fragment = substr($url, $position);
            $url = substr($url, 0, $position);
        }
        $parts = explode('?', $url, 2);
        parse_str($parts[1] ?? '', $query);
        $query['_tab'] = $tab;

        return $parts[0].'?'.http_build_query($query).$fragment;
    }
}
