<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\SpamBotGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

/**
 * Direct middleware tests. The phpunit.xml master switch keeps this
 * middleware OFF for the rest of the suite, but we re-enable it here
 * so the actual logic is exercised.
 */
class SpamBotGuardTest extends TestCase
{
    private SpamBotGuard $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.spambot_guard.enabled' => true]);
        $this->middleware = new SpamBotGuard();
    }

    public function test_lets_get_requests_through_unchanged(): void
    {
        $req = Request::create('/login', 'GET');
        $reached = false;

        $resp = $this->middleware->handle($req, function () use (&$reached) {
            $reached = true;
            return new Response('ok');
        });

        $this->assertTrue($reached);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_skips_when_master_switch_off(): void
    {
        config(['security.spambot_guard.enabled' => false]);
        $req = $this->postWith([]);

        $reached = false;
        $this->middleware->handle($req, function () use (&$reached) {
            $reached = true;
            return new Response('ok');
        });

        $this->assertTrue($reached, 'Disabled middleware should pass everything through');
    }

    public function test_rejects_when_honeypot_filled(): void
    {
        $req = $this->postWith([
            SpamBotGuard::HONEYPOT_FIELD => 'evil@spam.test',
            SpamBotGuard::TIMESTAMP_FIELD => SpamBotGuard::freshTimestamp(),
        ]);

        $resp = $this->middleware->handle($req, fn () => new Response('passed'));

        $this->assertInstanceOf(RedirectResponse::class, $resp);
    }

    public function test_rejects_when_timestamp_missing(): void
    {
        $req = $this->postWith([SpamBotGuard::HONEYPOT_FIELD => '']);

        $resp = $this->middleware->handle($req, fn () => new Response('passed'));

        $this->assertInstanceOf(RedirectResponse::class, $resp);
    }

    public function test_rejects_when_timestamp_undecryptable(): void
    {
        $req = $this->postWith([
            SpamBotGuard::HONEYPOT_FIELD => '',
            SpamBotGuard::TIMESTAMP_FIELD => 'not-a-real-cipher',
        ]);

        $resp = $this->middleware->handle($req, fn () => new Response('passed'));

        $this->assertInstanceOf(RedirectResponse::class, $resp);
    }

    public function test_rejects_when_form_submitted_too_fast(): void
    {
        $token = Crypt::encryptString((string) (int) (microtime(true) * 1000));

        $req = $this->postWith([
            SpamBotGuard::HONEYPOT_FIELD => '',
            SpamBotGuard::TIMESTAMP_FIELD => $token,
        ]);

        $resp = $this->middleware->handle($req, fn () => new Response('passed'));

        $this->assertInstanceOf(RedirectResponse::class, $resp);
    }

    public function test_rejects_when_form_token_is_stale(): void
    {
        $thirtyMinAgoMs = (int) (microtime(true) * 1000) - (30 * 60 * 1000);
        $token = Crypt::encryptString((string) $thirtyMinAgoMs);

        $req = $this->postWith([
            SpamBotGuard::HONEYPOT_FIELD => '',
            SpamBotGuard::TIMESTAMP_FIELD => $token,
        ]);

        $resp = $this->middleware->handle($req, fn () => new Response('passed'));

        $this->assertInstanceOf(RedirectResponse::class, $resp);
    }

    public function test_passes_with_valid_honeypot_and_aged_token(): void
    {
        $agedMs = (int) (microtime(true) * 1000) - 1200;
        $token = Crypt::encryptString((string) $agedMs);

        $req = $this->postWith([
            SpamBotGuard::HONEYPOT_FIELD => '',
            SpamBotGuard::TIMESTAMP_FIELD => $token,
        ]);

        $reached = false;
        $resp = $this->middleware->handle($req, function () use (&$reached) {
            $reached = true;
            return new Response('passed');
        });

        $this->assertTrue($reached, 'Middleware should let valid request through');
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_strips_bot_fields_so_validators_dont_see_them(): void
    {
        $agedMs = (int) (microtime(true) * 1000) - 1200;
        $token = Crypt::encryptString((string) $agedMs);

        $req = $this->postWith([
            SpamBotGuard::HONEYPOT_FIELD => '',
            SpamBotGuard::TIMESTAMP_FIELD => $token,
            'email' => 'real@user.test',
        ]);

        $captured = null;
        $this->middleware->handle($req, function (Request $r) use (&$captured) {
            $captured = $r->all();
            return new Response('ok');
        });

        $this->assertArrayNotHasKey(SpamBotGuard::HONEYPOT_FIELD, $captured);
        $this->assertArrayNotHasKey(SpamBotGuard::TIMESTAMP_FIELD, $captured);
        $this->assertSame('real@user.test', $captured['email']);
    }

    private function postWith(array $data): Request
    {
        $req = Request::create('/login', 'POST', $data);
        $req->setLaravelSession(app('session.store'));
        return $req;
    }
}
