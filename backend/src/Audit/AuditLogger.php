<?php

namespace App\Audit;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/** Records a change (persisted with the caller's next flush). */
final class AuditLogger
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Security $security)
    {
    }

    /** @param array<string, mixed> $data */
    public function log(string $action, string $subjectType, string $subjectId, ?string $accountSlug = null, array $data = []): void
    {
        $this->em->persist(new AuditLog($this->security->getUser()?->getUserIdentifier() ?? 'console', $action, $subjectType, $subjectId, $accountSlug, $data));
    }
}
