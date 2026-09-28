<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** One change made in the console (who, what, on which object, with the data sent). Append-only. */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['account_slug'])]
#[ORM\Index(columns: ['occurred_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actor;

    /** e.g. account.create, subscription.update, catalogue.brick.update, licence.issue. */
    #[ORM\Column(length: 60)]
    private string $action;

    #[ORM\Column(length: 40)]
    private string $subjectType;

    #[ORM\Column(length: 64)]
    private string $subjectId;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $accountSlug;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $data;

    /** @param array<string, mixed> $data */
    public function __construct(?string $actor, string $action, string $subjectType, string $subjectId, ?string $accountSlug, array $data)
    {
        $this->id = Uuid::v7();
        $this->occurredAt = new \DateTimeImmutable();
        $this->actor = $actor;
        $this->action = $action;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->accountSlug = $accountSlug;
        $this->data = $data;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'occurredAt' => $this->occurredAt->format(\DATE_ATOM), 'actor' => $this->actor, 'action' => $this->action, 'subjectType' => $this->subjectType, 'subjectId' => $this->subjectId, 'account' => $this->accountSlug, 'data' => $this->data];
    }
}
