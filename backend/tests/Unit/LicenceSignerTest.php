<?php

namespace App\Tests\Unit;

use App\Licence\LicenceSigner;
use PHPUnit\Framework\TestCase;

final class LicenceSignerTest extends TestCase
{
    public function testHs256RoundTrip(): void
    {
        $signer = new LicenceSigner(str_repeat('k', 40));
        $token = $signer->sign(['sub' => 'loussahousing', 'bricks' => ['host'], 'exp' => time() + 60]);
        self::assertSame('HS256', $signer->algorithm());
        self::assertSame(['host'], $signer->verify($token)['bricks']);
        self::assertNull($signer->publicKey());
    }

    public function testEdDsaRoundTripAndPublicKey(): void
    {
        $signer = new LicenceSigner('ed25519:'.base64_encode(str_repeat("\x01", 32)));
        $token = $signer->sign(['sub' => 'acme', 'exp' => time() + 60]);
        self::assertSame('EdDSA', $signer->algorithm());
        self::assertSame('acme', $signer->verify($token)['sub']);
        // a verifier holding only the public key
        [$h, $p, $s] = explode('.', $token);
        $public = base64_decode(strtr((string) $signer->publicKey(), '-_', '+/'));
        self::assertTrue(sodium_crypto_sign_verify_detached(base64_decode(strtr($s, '-_', '+/')), "$h.$p", $public));
    }

    public function testForgedExpiredAndWrongKeyAreRejected(): void
    {
        $signer = new LicenceSigner(str_repeat('k', 40));
        $token = $signer->sign(['sub' => 'acme', 'bricks' => ['host'], 'exp' => time() + 60]);
        [$h, , $s] = explode('.', $token);
        $forged = $h.'.'.rtrim(strtr(base64_encode((string) json_encode(['sub' => 'acme', 'bricks' => ['host', 'pms'], 'exp' => time() + 60])), '+/', '-_'), '=').'.'.$s;
        foreach ([
            [$signer, $forged, 'Signature invalide'],
            [$signer, $signer->sign(['exp' => time() - 1]), 'expirée'],
            [new LicenceSigner(str_repeat('z', 40)), $token, 'Signature invalide'],
            [new LicenceSigner('ed25519:'.base64_encode(str_repeat("\x02", 32))), $token, 'Algorithme'],
            [$signer, 'abc', 'mal formé'],
        ] as [$verifier, $t, $message]) {
            try {
                $verifier->verify($t);
                self::fail($message);
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testMissingOrShortKey(): void
    {
        $this->expectException(\LogicException::class);
        (new LicenceSigner('short'))->sign([]);
    }
}
