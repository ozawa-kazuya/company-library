<?php

namespace Tests\Unit;

use App\Services\ReturnLocationGuard;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReturnLocationGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'library.return_location.restricted' => true,
            'library.return_location.allow_localhost' => false,
            'library.return_location.allowed_networks' => [
                '162.120.184.214/32',
                '114.183.40.91/32',
            ],
        ]);
    }

    public function test_allowed_ipv4_is_accepted(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '162.120.184.214',
        ]);

        $this->assertTrue(ReturnLocationGuard::allows($request));
    }

    public function test_ipv4_mapped_ipv6_is_accepted(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '::ffff:162.120.184.214',
        ]);

        $this->assertTrue(ReturnLocationGuard::allows($request));
    }

    public function test_additional_office_ipv4_is_accepted(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '114.183.40.91',
        ]);

        $this->assertTrue(ReturnLocationGuard::allows($request));
    }

    public function test_other_ipv4_is_rejected(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        $this->assertFalse(ReturnLocationGuard::allows($request));
    }

    public function test_x_forwarded_for_behind_loopback_proxy_is_accepted(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '162.120.184.214',
        ]);

        $trusted = new \Illuminate\Http\Middleware\TrustProxies;
        $trusted->handle($request, fn ($proxied) => $proxied);

        $this->assertSame('162.120.184.214', $request->ip());
        $this->assertTrue(ReturnLocationGuard::allows($request));
    }
}
