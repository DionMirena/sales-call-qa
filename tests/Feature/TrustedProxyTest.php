<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    /** Behind ngrok the browser uses https; links must not fall back to http://localhost. */
    public function test_links_use_the_https_address_forwarded_by_a_proxy(): void
    {
        $this->get('/login', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host'  => 'example.ngrok-free.dev',
            'X-Forwarded-Port'  => '443',
        ])
            ->assertOk()
            ->assertSee('https://example.ngrok-free.dev/login', false)
            ->assertDontSee('http://localhost/login', false);
    }
}
