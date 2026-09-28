<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * What an account subscribes to: a plan (optional) plus bricks à la carte, options and the quantities that price
 * them (properties, places, screens, mailboxes), monthly or yearly, from startsAt to endsAt (null: open-ended).
 */
#[ORM\Entity]
#[ORM\Table(name: 'subscription')]
class Subscription
{
    public const PERIODS = ['monthly', 'yearly'];
    public const QUANTITIES = ['properties', 'places', 'screens', 'mailboxes'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $plan = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $bricks = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $options = [];

    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    private array $quantities = ['properties' => 0, 'places' => 0, 'screens' => 0, 'mailboxes' => 0];

    #[ORM\Column(length: 8)]
    private string $period = 'monthly';

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    use TrackedTrait;

    public function __construct(Account $account, ?\DateTimeImmutable $startsAt = null)
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->startsAt = $startsAt ?? new \DateTimeImmutable('today');
    }

    public function getId(): Uuid { return $this->id; }
    public function getAccount(): Account { return $this->account; }
    public function getPlan(): ?string { return $this->plan; }
    public function setPlan(?string $v): static { $this->plan = $v; return $this; }
    /** @return list<string> */
    public function getBricks(): array { return $this->bricks; }
    /** @param list<string> $v */
    public function setBricks(array $v): static { $this->bricks = array_values(array_unique($v)); return $this; }
    /** @return list<string> */
    public function getOptions(): array { return $this->options; }
    /** @param list<string> $v */
    public function setOptions(array $v): static { $this->options = array_values(array_unique($v)); return $this; }
    /** @return array<string, int> */
    public function getQuantities(): array { return $this->quantities + array_fill_keys(self::QUANTITIES, 0); }
    /** @param array<string, int> $v */
    public function setQuantities(array $v): static { $this->quantities = array_intersect_key($v, array_flip(self::QUANTITIES)) + $this->getQuantities(); return $this; }
    public function getPeriod(): string { return $this->period; }
    public function setPeriod(string $v): static { $this->period = $v; return $this; }
    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(\DateTimeImmutable $v): static { $this->startsAt = $v; return $this; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $v): static { $this->endsAt = $v; return $this; }

    public function isInForce(\DateTimeImmutable $at): bool
    {
        return $this->startsAt <= $at && (null === $this->endsAt || $this->endsAt > $at);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'account' => $this->account->getSlug(), 'plan' => $this->plan, 'bricks' => $this->bricks, 'options' => $this->options, 'quantities' => $this->getQuantities(), 'period' => $this->period, 'startsAt' => $this->startsAt->format('Y-m-d'), 'endsAt' => $this->endsAt?->format('Y-m-d')];
    }
}
