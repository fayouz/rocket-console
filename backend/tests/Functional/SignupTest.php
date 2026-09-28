<?php

namespace App\Tests\Functional;

use App\Billing\CatalogueSeeder;
use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Self-service sign-up: public catalogue and quote, sign-up, anti-bot, idempotency, verification, operator badge. No network. */
final class SignupTest extends WebTestCase
{
    use ApiTestTrait;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get('cache.app')->clear();
        static::getContainer()->get(CatalogueSeeder::class)->seed();
    }

    /** @return array<string, mixed> */
    private function signupBody(string $email = 'Owner@Example.org', array $overrides = []): array
    {
        return array_replace_recursive([
            'offer' => ['plan' => 'rocket-host', 'quantities' => ['properties' => 4], 'period' => 'monthly'],
            'account' => ['name' => 'Villa Azur', 'country' => 'fr', 'vatNumber' => 'FR123'],
            'owner' => ['name' => 'Inès Martin', 'email' => $email, 'password' => 'un-mot-de-passe-solide'],
            'acceptTerms' => true,
            'formStartedAt' => time() - 30,
            'website' => '',
        ], $overrides);
    }

    public function testPublicCatalogueAndQuoteNeedNoAuthentication(): void
    {
        $catalogue = $this->api('GET', '/api/public/catalogue');
        $this->assertStatus(200);
        self::assertNotEmpty($catalogue['plans']);
        self::assertArrayNotHasKey('id', $catalogue['bricks'][0]);
        self::assertArrayNotHasKey('active', $catalogue['plans'][0]);
        self::assertSame(30, $catalogue['pricing']['trialDays']);

        $quote = $this->api('POST', '/api/public/quote', ['plan' => 'rocket-location', 'quantities' => ['properties' => 12], 'period' => 'yearly']);
        $this->assertStatus(200);
        self::assertSame(12 * 2200 * 85 / 100 * 10, $quote['periodTotalCents']);

        $this->api('POST', '/api/public/quote', ['bricks' => ['pms'], 'quantities' => ['places' => 2]]);
        $this->assertStatus(422);

        // operator endpoints stay closed
        $this->api('GET', '/api/accounts');
        $this->assertStatus(401);
    }

    public function testSignupCreatesTrialAccountSubscriptionAndPendingOwner(): void
    {
        $result = $this->api('POST', '/api/public/signup', $this->signupBody());
        $this->assertStatus(201);
        self::assertTrue($result['created']);
        self::assertSame('villa-azur', $result['account']['slug']);
        self::assertSame('trial', $result['account']['status']);
        self::assertSame((new \DateTimeImmutable('today +30 days'))->format('Y-m-d'), substr($result['account']['trialEndsAt'], 0, 10));
        self::assertSame('rocket-host', $result['subscription']['plan']);
        self::assertSame('owner@example.org', $result['owner']['email']);
        self::assertFalse($result['owner']['verified']);
        self::assertFalse($result['verification']['sent']);
        self::assertStringContainsString('/inscription/verifier?token=', $result['verification']['devUrl']);
        self::assertContains('channel-manager', array_column($result['nextSteps'], 'key'));
        self::assertGreaterThan(0, $result['quote']['monthlyEquivalentCents']);
        self::assertStringNotContainsString('mot-de-passe', (string) $this->client->getResponse()->getContent());

        // idempotent by e-mail while unverified: same account, new link
        $again = $this->api('POST', '/api/public/signup', $this->signupBody('owner@example.org', ['account' => ['name' => 'Autre nom']]));
        $this->assertStatus(200);
        self::assertFalse($again['created']);
        self::assertSame('villa-azur', $again['account']['slug']);
        self::assertNotSame($result['verification']['devUrl'], $again['verification']['devUrl']);

        // the first link is no longer valid, the new one is
        parse_str((string) parse_url($result['verification']['devUrl'], \PHP_URL_QUERY), $old);
        $this->api('POST', '/api/public/verify-email', ['token' => $old['token']]);
        $this->assertStatus(422);
        parse_str((string) parse_url($again['verification']['devUrl'], \PHP_URL_QUERY), $new);
        $verified = $this->api('POST', '/api/public/verify-email', ['token' => $new['token']]);
        $this->assertStatus(200);
        self::assertSame('villa-azur', $verified['account']['slug']);
        $this->api('POST', '/api/public/verify-email', ['token' => $new['token']]);
        $this->assertStatus(422);

        // verified: a new sign-up with the same e-mail is refused
        $this->api('POST', '/api/public/signup', $this->signupBody());
        $this->assertStatus(409);

        // same company name, other owner: suffixed slug
        $other = $this->api('POST', '/api/public/signup', $this->signupBody('autre@example.org'));
        $this->assertStatus(201);
        self::assertSame('villa-azur-2', $other['account']['slug']);

        // the operator sees the sign-ups with the « Nouveau » badge, until reviewed
        $admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        $new = $this->api('GET', '/api/accounts?new=1', null, $admin);
        self::assertSame(['villa-azur', 'villa-azur-2'], array_column($new, 'slug'));
        self::assertTrue($new[0]['isNew']);
        self::assertSame('signup', $new[0]['source']);
        $detail = $this->api('GET', '/api/accounts/villa-azur', null, $admin);
        self::assertTrue($detail['members'][0]['verified']);
        self::assertTrue($detail['members'][0]['hasPassword']);
        self::assertSame('FR', $detail['country']);
        self::assertFalse($this->api('POST', '/api/accounts/villa-azur/signup-reviewed', null, $admin)['isNew']);
        self::assertSame(['villa-azur-2'], array_column($this->api('GET', '/api/accounts?new=1', null, $admin), 'slug'));
        $logs = $this->api('GET', '/api/audit-logs?account=villa-azur', null, $admin);
        self::assertStringNotContainsString('mot-de-passe', json_encode($logs, \JSON_THROW_ON_ERROR));
    }

    public function testSignupValidationAndAntiBot(): void
    {
        // honeypot or too fast: neutral answer, nothing created
        $this->api('POST', '/api/public/signup', $this->signupBody('bot@example.org', ['website' => 'http://spam']));
        $this->assertStatus(202);
        $this->api('POST', '/api/public/signup', $this->signupBody('bot@example.org', ['formStartedAt' => time()]));
        $this->assertStatus(202);

        $this->api('POST', '/api/public/signup', $this->signupBody('a@example.org', ['acceptTerms' => false]));
        $this->assertStatus(422);
        $this->api('POST', '/api/public/signup', $this->signupBody('pas-une-adresse'));
        $this->assertStatus(422);
        static::getContainer()->get('cache.app')->clear(); // fresh rate-limit window
        $this->api('POST', '/api/public/signup', $this->signupBody('a@example.org', ['owner' => ['password' => 'court']]));
        $this->assertStatus(422);
        $this->api('POST', '/api/public/signup', $this->signupBody('a@example.org', ['offer' => ['plan' => null]]));
        $this->assertStatus(422);
    }

    public function testRocketAuthPlaceholderAndBricksAlone(): void
    {
        $body = $this->signupBody('solo@example.org', [
            'offer' => ['plan' => null, 'bricks' => ['place', 'pms'], 'quantities' => ['places' => 2], 'period' => 'yearly'],
            'account' => ['name' => 'Gîtes Solo', 'slug' => 'gites-solo'],
            'owner' => ['password' => null, 'authProvider' => 'rocket-auth'],
        ]);
        $result = $this->api('POST', '/api/public/signup', $body);
        $this->assertStatus(201);
        self::assertSame('gites-solo', $result['account']['slug']);
        self::assertSame(['place', 'pms'], $result['subscription']['bricks']);
        self::assertSame('yearly', $result['subscription']['period']);

        $this->api('POST', '/api/public/signup', $this->signupBody('x@example.org', ['account' => ['slug' => 'gites-solo']]));
        $this->assertStatus(409);
    }

    public function testSignupIsRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->api('POST', '/api/public/signup', $this->signupBody('bot@example.org', ['website' => 'spam']));
            $this->assertStatus(202);
        }
        $this->api('POST', '/api/public/signup', $this->signupBody('bot@example.org', ['website' => 'spam']));
        $this->assertStatus(429);
    }
}
