<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** A bundle of bricks at one unit price (Rocket Host, Rocket Location…). */
#[ORM\Entity]
#[ORM\Table(name: 'catalogue_plan')]
class Plan
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 40, unique: true)]
    private string $code;

    #[ORM\Column(length: 80)]
    private string $name;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $bricks = [];

    #[ORM\Column(length: 16)]
    private string $unit = 'per_property';

    #[ORM\Column]
    private int $unitPriceCents = 0;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

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
    /** @return list<string> */
    public function getBricks(): array { return $this->bricks; }
    /** @param list<string> $v */
    public function setBricks(array $v): static { $this->bricks = array_values(array_unique($v)); return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $v): static { $this->unit = $v; return $this; }
    public function getUnitPriceCents(): int { return $this->unitPriceCents; }
    public function setUnitPriceCents(int $v): static { $this->unitPriceCents = $v; return $this; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'code' => $this->code, 'name' => $this->name, 'bricks' => $this->bricks, 'unit' => $this->unit, 'unitPriceCents' => $this->unitPriceCents, 'description' => $this->description, 'active' => $this->active];
    }
}
