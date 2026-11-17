<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TrustProxies::flushState();
        Request::setTrustedProxies([], 0);
        config(['trustedproxy.proxies' => ['172.29.0.2']]);

        Route::get('/__proxy-check', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'login_url' => url('/login'),
            'client_ip' => $request->ip(),
        ]));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        Request::setTrustedProxies([], 0);

        parent::tearDown();
    }

    public function test_trusted_proxy_preserves_https_and_external_port(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.29.0.2'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://10.202.0.113:5443/__proxy-check')
            ->assertOk()
            ->assertJsonPath('secure', true)
            ->assertJsonPath('login_url', 'https://10.202.0.113:5443/login');
    }

    public function test_untrusted_peer_cannot_claim_a_secure_connection(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.202.0.50'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://10.202.0.113:5030/__proxy-check')
            ->assertOk()
            ->assertJsonPath('secure', false)
            ->assertJsonPath('login_url', 'http://10.202.0.113:5030/login');
    }

    public function test_empty_proxy_list_ignores_forwarded_protocol(): void
    {
        config(['trustedproxy.proxies' => []]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.29.0.2'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://10.202.0.113:5443/__proxy-check')
            ->assertOk()
            ->assertJsonPath('secure', false);
    }

    public function test_direct_http_access_is_not_globally_forced_to_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.202.0.50'])
            ->get('http://10.202.0.113:5030/__proxy-check')
            ->assertOk()
            ->assertJsonPath('secure', false);
    }

    public function test_only_protocol_header_is_trusted(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.29.0.2'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'attacker.example',
                'X-Forwarded-Port' => '9999',
                'X-Forwarded-For' => '203.0.113.45',
            ])
            ->get('http://10.202.0.113:5443/__proxy-check')
            ->assertOk()
            ->assertJsonPath('login_url', 'https://10.202.0.113:5443/login')
            ->assertJsonPath('client_ip', '172.29.0.2');
    }

    public function test_mobile_authentication_redirect_stays_on_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.29.0.2'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://10.202.0.113:5443/mobile')
            ->assertRedirect('https://10.202.0.113:5443/login');
    }
}
