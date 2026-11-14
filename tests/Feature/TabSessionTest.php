<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class EnforceTabCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}

class TabSessionTest extends TestCase
{
    private array $jar = [];

    protected function setUp(): void
    {
        parent::setUp();
        $users = [
            'alice' => new GenericUser(['id' => 'alice', 'password' => 'hash-a', 'remember_token' => 'token-a']),
            'bob' => new GenericUser(['id' => 'bob', 'password' => 'hash-b', 'remember_token' => 'token-b']),
        ];
        $provider = Mockery::mock(UserProvider::class);
        $provider->shouldReceive('retrieveById')->andReturnUsing(fn ($id) => $users[$id] ?? null);
        $provider->shouldReceive('retrieveByToken')->andReturnUsing(fn ($id, $token) => isset($users[$id]) && $users[$id]->getRememberToken() === $token ? $users[$id] : null);
        $provider->shouldReceive('updateRememberToken')->andReturnNull();
        Auth::provider('tab-test', fn () => $provider);
        config(['auth.providers.users.driver' => 'tab-test']);

        Route::middleware('web')->prefix('_tab-test')->group(function () use ($users) {
            Route::post('/login/{id}', function (Request $request, string $id) use ($users) {
                Auth::guard('web')->login($users[$id], true);
                $request->session()->regenerate();

                return redirect('/_tab-test/me?view=details#account');
            });
            Route::get('/me', fn (Request $request) => response()->json([
                'id' => Auth::guard('web')->id(), 'csrf' => $request->session()->token(),
            ]));
            Route::post('/logout', function (Request $request) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login');
            });
            Route::get('/external', fn () => redirect('https://example.org/landing'));
            Route::post('/protected', fn () => response('saved'))->middleware(EnforceTabCsrf::class);
        });
        Route::middleware('api')->get('/api/_tab-test/me', fn () => response()->json(['id' => Auth::guard('web')->id()]));
    }

    private function visit(string $method, string $url, array $headers = [], array $data = [])
    {
        // Simulate a new PHP request while retaining one browser cookie jar.
        Auth::forgetGuards();
        app('session')->driver()->flush();
        app('cookie')->flushQueuedCookies();
        $this->unencryptedCookies = $this->jar;
        $response = $this->call($method, $url, $data, $this->prepareCookiesForRequest(), [], $this->transformHeadersToServerVars($headers));
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < time()) {
                unset($this->jar[$cookie->getName()]);
            } else {
                $this->jar[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    public function test_logins_csrf_and_logout_are_isolated_in_one_browser_cookie_jar(): void
    {
        $a = str_repeat('a', 32);
        $b = str_repeat('b', 32);
        $login = $this->visit('POST', "/_tab-test/login/alice?_tab=$a");
        $login->assertRedirect("/_tab-test/me?view=details&_tab=$a#account");
        $alice = $this->visit('GET', "/_tab-test/me?_tab=$a")->assertJsonPath('id', 'alice')->json('csrf');
        $this->visit('POST', "/_tab-test/login/bob?_tab=$b")->assertRedirect();
        $bob = $this->visit('GET', "/_tab-test/me?_tab=$b")->assertJsonPath('id', 'bob')->json('csrf');
        $this->assertNotSame($alice, $bob);
        $this->visit('POST', "/_tab-test/protected?_tab=$a", ['X-CSRF-TOKEN' => $alice])->assertOk();
        $this->visit('POST', "/_tab-test/protected?_tab=$a", ['X-CSRF-TOKEN' => $bob])->assertStatus(419);
        $this->visit('GET', "/_tab-test/me?_tab=$a")->assertJsonPath('id', 'alice');
        $this->visit('POST', "/_tab-test/logout?_tab=$a")->assertRedirect("/login?_tab=$a");
        $this->visit('GET', "/_tab-test/me?_tab=$a")->assertJsonPath('id', null);
        $this->visit('GET', "/_tab-test/me?_tab=$b")->assertJsonPath('id', 'bob');
        $this->visit('GET', '/_tab-test/me')->assertJsonPath('id', null);
    }

    public function test_remember_me_and_stateful_api_use_only_the_selected_context(): void
    {
        $a = str_repeat('a', 32);
        $b = str_repeat('b', 32);
        $this->visit('POST', "/_tab-test/login/alice?_tab=$a");
        $this->visit('POST', "/_tab-test/login/bob?_tab=$b");
        // Drop session cookies to exercise both distinct remember-me cookies.
        $this->jar = array_filter($this->jar, fn ($name) => str_starts_with($name, 'remember_'), ARRAY_FILTER_USE_KEY);
        $this->assertCount(2, $this->jar);
        $this->visit('GET', "/_tab-test/me?_tab=$a")->assertJsonPath('id', 'alice');
        $this->visit('GET', "/_tab-test/me?_tab=$b")->assertJsonPath('id', 'bob');
        $this->visit('GET', '/api/_tab-test/me', ['Origin' => 'http://localhost', 'X-CompliTrack-Tab' => $b])->assertJsonPath('id', 'bob');
        $this->visit('GET', '/_tab-test/me?_tab='.str_repeat('c', 32))->assertJsonPath('id', null);
    }

    public function test_invalid_contexts_are_rejected_and_external_redirects_are_untouched(): void
    {
        $this->visit('GET', '/_tab-test/me?_tab[]=bad')->assertStatus(400);
        $this->visit('GET', '/_tab-test/me?_tab=bad')->assertStatus(400);
        $this->visit('GET', '/_tab-test/external?_tab='.str_repeat('a', 32))->assertRedirect('https://example.org/landing');
    }
}
