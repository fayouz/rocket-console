<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** A person of a customer account (later synced to the Rocket Auth organisation). */
#[ORM\Entity]
#[ORM\Table(name: 'account_member')]
#[ORM\UniqueConstraint(columns: ['account_id', 'email'])]
#[ORM\Index(columns: ['verification_token_hash'])]
class Member
{
    public const ROLES = ['owner', 'admin', 'member'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 8)]
    private string $role = 'member';

    /** Password chosen at self-service sign-up (hashed), until the account moves to Rocket Auth. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    /** Null while a self-service sign-up e-mail is unverified; set for members added by the operator. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    /** SHA-256 of the e-mail verification token (the token itself is only sent). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $verificationTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verificationExpiresAt = null;

    public function __construct(Account $account, string $email, string $role = 'member')
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->email = mb_strtolower($email);
        $this->role = $role;
        $this->emailVerifiedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getAccount(): Account { return $this->account; }
    public function getEmail(): string { return $this->email; }
    public function setName(?string $v): static { $this->name = $v; return $this; }
    public function setRole(string $v): static { $this->role = $v; return $this; }
    public function getName(): ?string { return $this->name; }
    public function getRole(): string { return $this->role; }
    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function setPasswordHash(?string $v): static { $this->passwordHash = $v; return $this; }
    public function isVerified(): bool { return null !== $this->emailVerifiedAt; }

    /** Marks the e-mail unverified and returns a new verification token (only its hash is kept). */
    public function requestVerification(\DateTimeImmutable $expiresAt): string
    {
        $token = bin2hex(random_bytes(32));
        $this->emailVerifiedAt = null;
        $this->verificationTokenHash = hash('sha256', $token);
        $this->verificationExpiresAt = $expiresAt;

        return $token;
    }

    public function verify(string $token, \DateTimeImmutable $now): bool
    {
        if (null === $this->verificationTokenHash || null === $this->verificationExpiresAt || $this->verificationExpiresAt < $now
            || !hash_equals($this->verificationTokenHash, hash('sha256', $token))) {
            return false;
        }
        $this->emailVerifiedAt = $now;
        $this->verificationTokenHash = null;
        $this->verificationExpiresAt = null;

        return true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'email' => $this->email, 'name' => $this->name, 'role' => $this->role, 'verified' => $this->isVerified(), 'hasPassword' => null !== $this->passwordHash];
    }
}
