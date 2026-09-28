<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** A customer of the shared instance (later: a Rocket Auth organisation, same slug). */
#[ORM\Entity]
#[ORM\Table(name: 'account')]
class Account
{
    public const STATUSES = ['trial', 'active', 'suspended', 'closed'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 64, unique: true)]
    private string $slug;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 12)]
    private string $status = 'trial';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $trialEndsAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $billingName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $billingEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingAddress = null;

    /** ISO 3166-1 alpha-2. */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, Member> */
    #[ORM\OneToMany(targetEntity: Member::class, mappedBy: 'account', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['email' => 'ASC'])]
    private Collection $members;

    /** @var Collection<int, Subscription> */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'account', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startsAt' => 'DESC'])]
    private Collection $subscriptions;

    use TrackedTrait;

    public function __construct(string $slug, string $name)
    {
        $this->id = Uuid::v7();
        $this->slug = $slug;
        $this->name = $name;
        $this->members = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getSlug(): string { return $this->slug; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getTrialEndsAt(): ?\DateTimeImmutable { return $this->trialEndsAt; }
    public function setTrialEndsAt(?\DateTimeImmutable $v): static { $this->trialEndsAt = $v; return $this; }
    public function getBillingEmail(): ?string { return $this->billingEmail; }
    public function setBillingName(?string $v): static { $this->billingName = $v; return $this; }
    public function setBillingEmail(?string $v): static { $this->billingEmail = $v; return $this; }
    public function setBillingAddress(?string $v): static { $this->billingAddress = $v; return $this; }
    public function setCountry(?string $v): static { $this->country = null === $v ? null : strtoupper($v); return $this; }
    public function setVatNumber(?string $v): static { $this->vatNumber = $v; return $this; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    /** @return Collection<int, Member> */
    public function getMembers(): Collection { return $this->members; }
    public function addMember(Member $m): static { $this->members->add($m); return $this; }
    /** @return Collection<int, Subscription> */
    public function getSubscriptions(): Collection { return $this->subscriptions; }
    public function addSubscription(Subscription $s): static { $this->subscriptions->add($s); return $this; }

    /** The subscription in force at $at (the most recent started one not ended), or null. */
    public function currentSubscription(\DateTimeImmutable $at): ?Subscription
    {
        foreach ($this->subscriptions as $s) {
            if ($s->isInForce($at)) {
                return $s;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'slug' => $this->slug, 'name' => $this->name, 'status' => $this->status, 'trialEndsAt' => $this->trialEndsAt?->format(\DATE_ATOM), 'billingName' => $this->billingName, 'billingEmail' => $this->billingEmail, 'billingAddress' => $this->billingAddress, 'country' => $this->country, 'vatNumber' => $this->vatNumber, 'notes' => $this->notes, 'createdAt' => $this->getCreatedAt()?->format(\DATE_ATOM)];
    }
}
