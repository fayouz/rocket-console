<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An option of a brick (e.g. "Ménage" on Rocket Host, a shared inbox on Rocket Mailer), priced per unit and per month.
 * "grants": the brick the option enables (host-clean grants clean), or null (a quota only).
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalogue_option')]
class Option
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 40, unique: true)]
    private string $code;

    #[ORM\Column(length: 80)]
    private string $name;

    /** Code of the brick this option applies to (it must be enabled). */
    #[ORM\Column(length: 40)]
    private string $brick;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $grants = null;

    #[ORM\Column(length: 16)]
    private string $unit = 'per_property';

    #[ORM\Column]
    private int $monthlyPriceCents = 0;

    #[ORM\Column]
    private bool $active = true;

    use TrackedTrait;

    public function __construct(string $code, string $name, string $brick)
    {
        $this->id = Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->brick = $brick;
    }

    public function getId(): Uuid { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getBrick(): string { return $this->brick; }
    public function setBrick(string $v): static { $this->brick = $v; return $this; }
    public function getGrants(): ?string { return $this->grants; }
    public function setGrants(?string $v): static { $this->grants = $v; return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $v): static { $this->unit = $v; return $this; }
    public function getMonthlyPriceCents(): int { return $this->monthlyPriceCents; }
    public function setMonthlyPriceCents(int $v): static { $this->monthlyPriceCents = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'code' => $this->code, 'name' => $this->name, 'brick' => $this->brick, 'grants' => $this->grants, 'unit' => $this->unit, 'monthlyPriceCents' => $this->monthlyPriceCents, 'active' => $this->active];
    }
}
