<?php

namespace App\Tests\Functional;

use App\Billing\CatalogueSeeder;
use App\Licence\LicenceSigner;
use App\Tests\ApiTestTrait;
use Rocket\Core\Secrets\SecretVault;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Catalogue, accounts, subscriptions, quotes, entitlements, licence keys, overview and audit log. No network. */
final class ConsoleTest extends WebTestCase
{
    use ApiTestTrait;

    private string $admin;
    private string $alice;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get(CatalogueSeeder::class)->seed();
        static::getContainer()->get(SecretVault::class)->set('rocket.console.signing_key', 'test-signing-key-0123456789abcdef0123456789');
        $this->admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        $this->alice = 'Bearer '.$this->jwtFor($this->createUser('alice@example.org'));
    }

    public function testCatalogueIsSeededAndEditableByAdminsOnly(): void
    {
        $catalogue = $this->api('GET', '/api/catalogue', null, $this->alice);
        $this->assertStatus(200);
        self::assertCount(12, $catalogue['bricks']);
        self::assertSame(2900, $catalogue['pricing']['minimumMonthlyCents']);
        self::assertSame(['place'], array_values(array_filter($catalogue['bricks'], static fn ($b) => 'pms' === $b['code']))[0]['depends']);

        $this->api('PATCH', '/api/catalogue/bricks/host', ['monthlyPriceCents' => 1200], $this->alice);
        $this->assertStatus(403);
        self::assertSame(1200, $this->api('PATCH', '/api/catalogue/bricks/host', ['monthlyPriceCents' => 1200], $this->admin)['monthlyPriceCents']);
        $this->api('PATCH', '/api/catalogue/bricks/pms', ['depends' => ['ghost']], $this->admin);
        $this->assertStatus(422);
        $this->api('POST', '/api/catalogue/bricks', ['code' => 'host', 'name' => 'Double'], $this->admin);
        $this->assertStatus(409);
        $this->api('POST', '/api/catalogue/options', ['code' => 'pms-channel', 'name' => 'Canal', 'brick' => 'pms', 'unit' => 'flat', 'monthlyPriceCents' => 300], $this->admin);
        $this->assertStatus(201);
        $this->api('POST', '/api/catalogue/plans', ['code' => 'rocket-pro', 'name' => 'Pro', 'bricks' => ['pms', 'place'], 'unit' => 'per_place', 'unitPriceCents' => 900], $this->admin);
        $this->assertStatus(201);
        $pricing = $this->api('PUT', '/api/catalogue/pricing', ['minimumMonthlyCents' => 3900, 'volumeTiers' => [['min' => 20, 'percent' => 20], ['min' => 5, 'percent' => 5]]], $this->admin);
        self::assertSame([['min' => 5, 'percent' => 5], ['min' => 20, 'percent' => 20]], $pricing['volumeTiers']);
        // in use by a plan: cannot be deleted
        $this->api('DELETE', '/api/catalogue/bricks/place', null, $this->admin);
        $this->assertStatus(409);
        $this->api('DELETE', '/api/catalogue/options/pms-channel', null, $this->admin);
        $this->assertStatus(204);
    }

    public function testAccountLifecycleWithSubscriptionAndQuote(): void
    {
        $account = $this->api('POST', '/api/accounts', ['name' => 'Les Mas du Sud', 'ownerEmail' => 'Owner@Example.org', 'country' => 'fr'], $this->admin);
        $this->assertStatus(201);
        self::assertSame('les-mas-du-sud', $account['slug']);
        self::assertSame('trial', $account['status']);
        self::assertNotNull($account['trialEndsAt']);
        self::assertSame('owner@example.org', $account['members'][0]['email']);
        self::assertSame('FR', $account['country']);
        $this->api('POST', '/api/accounts', ['name' => 'x', 'slug' => 'les-mas-du-sud'], $this->admin);
        $this->assertStatus(409);
        $this->api('GET', '/api/accounts', null, $this->alice);
        $this->assertStatus(403);

        // dependency validation
        $this->api('POST', '/api/accounts/les-mas-du-sud/subscriptions', ['bricks' => ['pms'], 'quantities' => ['places' => 2]], $this->admin);
        $this->assertStatus(422);
        self::assertStringContainsString('demande la brique « place »', json_decode((string) $this->client->getResponse()->getContent(), true)['detail']);

        $sub = $this->api('POST', '/api/accounts/les-mas-du-sud/subscriptions', ['plan' => 'rocket-location', 'quantities' => ['properties' => 12], 'period' => 'yearly'], $this->admin);
        $this->assertStatus(201);
        self::assertSame(12 * 2200 * 85 / 100 * 10, $sub['quote']['periodTotalCents']);

        // a new subscription replaces the current one
        $this->api('POST', '/api/accounts/les-mas-du-sud/subscriptions', ['plan' => 'rocket-host', 'options' => ['host-clean'], 'quantities' => ['properties' => 3]], $this->admin);
        $this->assertStatus(201);
        $detail = $this->api('GET', '/api/accounts/les-mas-du-sud', null, $this->admin);
        self::assertCount(2, $detail['subscriptions']);
        self::assertSame('rocket-host', $detail['subscription']['plan']);
        self::assertSame(4500, $detail['quote']['monthlyCents']);
        self::assertSame(['clean', 'host'], $detail['entitlements']['bricks']);

        $patched = $this->api('PATCH', "/api/subscriptions/{$detail['subscription']['id']}", ['quantities' => ['properties' => 1]], $this->admin);
        self::assertSame(2900, $patched['quote']['monthlyCents']);

        $member = $this->api('POST', '/api/accounts/les-mas-du-sud/members', ['email' => 'bob@example.org', 'role' => 'admin'], $this->admin);
        $this->assertStatus(201);
        $this->api('DELETE', "/api/accounts/les-mas-du-sud/members/{$member['id']}", null, $this->admin);
        $this->assertStatus(204);

        $quote = $this->api('POST', '/api/quote', ['plan' => 'rocket-host', 'quantities' => ['properties' => 30]], $this->admin);
        self::assertSame(22500, $quote['monthlyCents']);

        $this->api('DELETE', '/api/accounts/les-mas-du-sud', null, $this->admin);
        $this->assertStatus(409);
        $this->api('PATCH', '/api/accounts/les-mas-du-sud', ['status' => 'closed'], $this->admin);
        $this->api('DELETE', '/api/accounts/les-mas-du-sud', null, $this->admin);
        $this->assertStatus(204);

        $log = $this->api('GET', '/api/audit-logs?account=les-mas-du-sud', null, $this->admin);
        self::assertContains('account.create', array_column($log, 'action'));
        self::assertContains('subscription.update', array_column($log, 'action'));
        self::assertSame('admin@example.org', $log[0]['actor']);
    }

    public function testEntitlementsForApplicationsAndSignedLicence(): void
    {
        $this->api('POST', '/api/accounts', ['name' => 'Acme', 'status' => 'active'], $this->admin);
        $this->api('POST', '/api/accounts/acme/subscriptions', ['plan' => 'rocket-location', 'quantities' => ['properties' => 2, 'mailboxes' => 1]], $this->admin);
        [, $token] = $this->createApplication(false, 'Rocket Host');

        $this->api('GET', '/api/entitlements/acme', null, $this->alice);
        $this->assertStatus(403);
        $doc = $this->api('GET', '/api/entitlements/acme', null, 'Bearer '.$token);
        $this->assertStatus(200);
        self::assertTrue($doc['active']);
        $this->api('GET', '/api/accounts', null, 'Bearer '.$token);
        $this->assertStatus(403);
        self::assertSame(['clean', 'host', 'mailer', 'place', 'stock'], $doc['bricks']);
        self::assertSame(2, $doc['quotas']['properties']);
        self::assertSame('HS256', $doc['algorithm']);
        $claims = static::getContainer()->get(LicenceSigner::class)->verify($doc['token']);
        self::assertSame('acme', $claims['sub']);

        $licence = $this->api('GET', '/api/accounts/acme/licence', null, $this->admin);
        $this->assertStatus(200);
        self::assertTrue($this->api('POST', '/api/licences/verify', ['token' => $licence['token']], 'Bearer '.$token)['valid']);
        self::assertFalse($this->api('POST', '/api/licences/verify', ['token' => $licence['token'].'x'], $this->admin)['valid']);

        // suspended: nothing enabled
        $this->api('PATCH', '/api/accounts/acme', ['status' => 'suspended'], $this->admin);
        $doc = $this->api('GET', '/api/entitlements/acme', null, 'Bearer '.$token);
        self::assertFalse($doc['active']);
        self::assertSame([], $doc['bricks']);

        // expired trial: nothing enabled either
        $this->api('PATCH', '/api/accounts/acme', ['status' => 'trial', 'trialEndsAt' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d')], $this->admin);
        self::assertSame([], $this->api('GET', '/api/entitlements/acme', null, $this->admin)['bricks']);
        $this->api('GET', '/api/entitlements/nobody', null, $this->admin);
        $this->assertStatus(404);
    }

    public function testOverviewAndDemoSeeder(): void
    {
        $this->runConsole('app:demo:seed');
        $overview = $this->api('GET', '/api/overview', null, $this->admin);
        $this->assertStatus(200);
        self::assertSame(1, $overview['accountsByStatus']['active']);
        self::assertSame(4400, $overview['mrrCents']);
        self::assertSame('gite-des-oliviers', $overview['trialsEnding'][0]['slug']);
        self::assertSame(2900, $overview['trialsEnding'][0]['monthlyCents']);
        $this->api('GET', '/api/overview', null, $this->alice);
        $this->assertStatus(403);

        $this->runConsole('console:licence:issue', ['account' => 'loussahousing']);
        self::assertMatchesRegularExpression('/^[\w-]+\.[\w-]+\.[\w-]+$/', trim($this->output));
    }

    private string $output = '';

    /** @param array<string, string> $args */
    private function runConsole(string $name, array $args = []): void
    {
        $app = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($app->find($name));
        $tester->execute($args);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->output = $tester->getDisplay();
    }
}
