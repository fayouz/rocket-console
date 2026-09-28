<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A brick of the suite sold on its own: a middleware (Auth, Cloud, Mailer…) or a business app (Host, PMS, Place…).
 * "depends" lists the codes of the bricks it needs (e.g. PMS requires Place). Price in cents per unit and per month.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalogue_brick')]
class Brick
{
    public const FAMILIES = ['middleware', 'business'];
    public const UNITS = ['per_property', 'per_place', 'per_screen', 'per_mailbox', 'flat'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 40, unique: true)]
    private string $code;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 12)]
    private string $family = 'business';

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $depends = [];

    #[ORM\Column(length: 16)]
    private string $unit = 'per_place';

    #[ORM\Column]
    private int $monthlyPriceCents = 0;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $active = true;

    use TrackedTrait;

    public function __construct(string $code, string $name)
    {
        $this->id = Uuid::v7();
        $this->code = $code;
        $this->name = $name;
    }

    public function getId(): Uuid { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getFamily(): string { return $this->family; }
    public function setFamily(string $v): static { $this->family = $v; return $this; }
    /** @return list<string> */
    public function getDepends(): array { return $this->depends; }
    /** @param list<string> $v */
    public function setDepends(array $v): static { $this->depends = array_values(array_unique($v)); return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $v): static { $this->unit = $v; return $this; }
    public function getMonthlyPriceCents(): int { return $this->monthlyPriceCents; }
    public function setMonthlyPriceCents(int $v): static { $this->monthlyPriceCents = $v; return $this; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function setPosition(int $v): static { $this->position = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'code' => $this->code, 'name' => $this->name, 'family' => $this->family, 'depends' => $this->depends, 'unit' => $this->unit, 'monthlyPriceCents' => $this->monthlyPriceCents, 'description' => $this->description, 'position' => $this->position, 'active' => $this->active];
    }
}
