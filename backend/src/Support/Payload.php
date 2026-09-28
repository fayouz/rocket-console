<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Reading of JSON request bodies with French 422 errors. */
final class Payload
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    public function raw(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function string(string $key, int $max, bool $required = false): ?string
    {
        $v = $this->data[$key] ?? null;
        if (null !== $v && !\is_scalar($v)) {
            throw new HttpException(422, \sprintf('Champ « %s » invalide.', $key));
        }
        $v = null === $v ? '' : mb_substr(trim((string) $v), 0, $max);
        if ('' === $v) {
            return $required ? throw new HttpException(422, \sprintf('Champ « %s » requis.', $key)) : null;
        }

        return $v;
    }

    public function float(string $key, bool $required = false): ?float
    {
        $v = $this->data[$key] ?? null;
        if (null === $v || '' === $v) {
            return $required ? throw new HttpException(422, \sprintf('Champ « %s » requis.', $key)) : null;
        }
        if (!is_numeric($v)) {
            throw new HttpException(422, \sprintf('Champ « %s » : nombre attendu.', $key));
        }

        return (float) $v;
    }

    /** @param list<string> $choices */
    public function choice(string $key, array $choices, ?string $default = null): string
    {
        $v = $this->data[$key] ?? $default;
        if (!\is_string($v) || !\in_array($v, $choices, true)) {
            throw new HttpException(422, \sprintf('Champ « %s » : une valeur parmi %s.', $key, implode(', ', $choices)));
        }

        return $v;
    }

    public function date(string $key): ?\DateTimeImmutable
    {
        $v = $this->string($key, 40);
        if (null === $v) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);

        return false !== $d ? $d : throw new HttpException(422, \sprintf('Champ « %s » : date AAAA-MM-JJ attendue.', $key));
    }

    public function int(string $key, bool $required = false, int $min = 0): ?int
    {
        $v = $this->data[$key] ?? null;
        if (null === $v || '' === $v) {
            return $required ? throw new HttpException(422, \sprintf('Champ « %s » requis.', $key)) : null;
        }
        if (!is_numeric($v) || (int) $v != $v || (int) $v < $min) {
            throw new HttpException(422, \sprintf('Champ « %s » : entier ≥ %d attendu.', $key, $min));
        }

        return (int) $v;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->data[$key] ?? false, \FILTER_VALIDATE_BOOL);
    }

    /** @return list<string> a list of short codes */
    public function codes(string $key): array
    {
        $v = $this->data[$key] ?? [];
        if (!\is_array($v)) {
            throw new HttpException(422, \sprintf('Champ « %s » : liste attendue.', $key));
        }
        $out = [];
        foreach ($v as $code) {
            if (!\is_string($code) || !preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $code)) {
                throw new HttpException(422, \sprintf('Champ « %s » : codes en minuscules attendus.', $key));
            }
            $out[] = $code;
        }

        return array_values(array_unique($out));
    }

    public function code(string $key, bool $required = true): ?string
    {
        $v = $this->string($key, 40, $required);
        if (null !== $v && !preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $v)) {
            throw new HttpException(422, \sprintf('Champ « %s » : minuscules, chiffres et tirets.', $key));
        }

        return $v;
    }

    /** @return array<string, mixed> */
    public function array(string $key): array
    {
        $v = $this->data[$key] ?? [];

        return \is_array($v) ? $v : throw new HttpException(422, \sprintf('Champ « %s » : objet attendu.', $key));
    }

    /** A UUID given plain or as an IRI ("/api/…/<uuid>"). */
    public static function id(mixed $value): ?string
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }
        $id = basename($value);

        return \Symfony\Component\Uid\Uuid::isValid($id) ? strtolower($id) : null;
    }
}
