<?php

namespace App\Licence;

use App\Secrets\IntegrationSecrets;

/**
 * Compact JWS of the entitlements documents and licence keys, keyed by the vault secret rocket.console.signing_key
 * (legacy ROCKET_CONSOLE_SIGNING_KEY as the transition fallback, see App\Secrets\IntegrationSecrets):
 * - "ed25519:<base64 of a 32-byte seed>": EdDSA (Ed25519) — self-hosted instances only need the public key;
 * - any other value (≥ 32 characters): HS256 — shared secret (the instances of the suite).
 * Verifying (for rocket-core later): split on ".", base64url-decode the header, check "alg", verify the signature of
 * "<header>.<payload>" (hash_hmac sha256 or sodium_crypto_sign_verify_detached), then "exp" ≥ now.
 */
final class LicenceSigner
{
    public const ISSUER = 'rocket-console';

    public function __construct(private readonly IntegrationSecrets $secrets)
    {
    }

    private function key(): string
    {
        return $this->secrets->get('rocket.console.signing_key');
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->key());
    }

    public function algorithm(): string
    {
        return str_starts_with($this->key(), 'ed25519:') ? 'EdDSA' : 'HS256';
    }

    /** Base64url Ed25519 public key (EdDSA only), to give to self-hosted instances. */
    public function publicKey(): ?string
    {
        return 'EdDSA' === $this->algorithm() ? self::b64(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($this->seed()))) : null;
    }

    /** @param array<string, mixed> $claims */
    public function sign(array $claims): string
    {
        $header = self::b64((string) json_encode(['alg' => $this->algorithm(), 'typ' => 'JWT', 'kid' => $this->keyId()], \JSON_THROW_ON_ERROR));
        $payload = self::b64((string) json_encode($claims, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

        return "$header.$payload.".self::b64($this->signature("$header.$payload"));
    }

    /**
     * @return array<string, mixed> the claims
     *
     * @throws \InvalidArgumentException invalid, forged or expired
     */
    public function verify(string $token, ?\DateTimeImmutable $now = null): array
    {
        $parts = explode('.', trim($token));
        if (3 !== \count($parts)) {
            throw new \InvalidArgumentException('Jeton mal formé.');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::unb64($h), true);
        if (!\is_array($header) || ($header['alg'] ?? null) !== $this->algorithm()) {
            throw new \InvalidArgumentException('Algorithme inattendu.');
        }
        $signature = self::unb64($s);
        $valid = 'EdDSA' === $this->algorithm()
            ? \SODIUM_CRYPTO_SIGN_BYTES === \strlen($signature) && sodium_crypto_sign_verify_detached($signature, "$h.$p", sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($this->seed())))
            : hash_equals($this->signature("$h.$p"), $signature);
        if (!$valid) {
            throw new \InvalidArgumentException('Signature invalide.');
        }
        $claims = json_decode(self::unb64($p), true);
        if (!\is_array($claims)) {
            throw new \InvalidArgumentException('Contenu invalide.');
        }
        if (isset($claims['exp']) && (int) $claims['exp'] < ($now ?? new \DateTimeImmutable())->getTimestamp()) {
            throw new \InvalidArgumentException('Licence expirée.');
        }

        return $claims;
    }

    private function signature(string $input): string
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('Clé de signature absente : secret rocket.console.signing_key (Administration → Secrets).');
        }
        if ('EdDSA' === $this->algorithm()) {
            return sodium_crypto_sign_detached($input, sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($this->seed())));
        }
        if (\strlen($this->key()) < 32) {
            throw new \LogicException('Clé de signature (rocket.console.signing_key) : 32 caractères au moins.');
        }

        return hash_hmac('sha256', $input, $this->key(), true);
    }

    private function seed(): string
    {
        $seed = base64_decode(substr($this->key(), 8), true);
        if (false === $seed || \SODIUM_CRYPTO_SIGN_SEEDBYTES !== \strlen($seed)) {
            throw new \LogicException('Clé de signature (rocket.console.signing_key) : « ed25519: » suivi de 32 octets en base64.');
        }

        return $seed;
    }

    private function keyId(): string
    {
        return substr(hash('sha256', $this->publicKey() ?? $this->key()), 0, 12);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}
