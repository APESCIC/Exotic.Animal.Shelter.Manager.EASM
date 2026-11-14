<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    private function guestRequestThroughProxy(): TestResponse
    {
        return $this->get('/animals', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'shelter.example.org',
            'X-Forwarded-Port' => '443',
        ]);
    }

    public function test_forwarded_headers_are_ignored_by_default(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));

        $this->guestRequestThroughProxy()->assertRedirect(url('/login'));
    }

    public function test_a_trusted_proxy_gives_https_links_and_redirects(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->guestRequestThroughProxy()->assertRedirect('https://shelter.example.org/login');
    }

    public function test_a_listed_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8, 127.0.0.1']);

        $this->guestRequestThroughProxy()->assertRedirect('https://shelter.example.org/login');
    }

    public function test_an_address_that_is_not_listed_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);

        $this->guestRequestThroughProxy()->assertRedirect(url('/login'));
    }
}
