<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** A person of a customer account (later synced to the Rocket Auth organisation). */
#[ORM\Entity]
#[ORM\Table(name: 'account_member')]
#[ORM\UniqueConstraint(columns: ['account_id', 'email'])]
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

    public function __construct(Account $account, string $email, string $role = 'member')
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->email = mb_strtolower($email);
        $this->role = $role;
    }

    public function getId(): Uuid { return $this->id; }
    public function getAccount(): Account { return $this->account; }
    public function getEmail(): string { return $this->email; }
    public function setName(?string $v): static { $this->name = $v; return $this; }
    public function setRole(string $v): static { $this->role = $v; return $this; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'email' => $this->email, 'name' => $this->name, 'role' => $this->role];
    }
}
