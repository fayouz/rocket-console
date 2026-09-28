<?php

namespace App\Controller;

use App\Entity\Account;
use App\Licence\Entitlements;
use App\Licence\LicenceSigner;
use App\Support\Payload;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Security\ApplicationUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Entitlements read by the bricks: an application token (Bearer rco_…, acting for itself) or an administrator.
 * The response carries the document and the same document signed ("token"), to cache and verify offline.
 */
final class EntitlementController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Entitlements $entitlements, private readonly LicenceSigner $signer)
    {
    }

    #[Route('/api/entitlements/{slug}', name: 'api_entitlements', methods: ['GET'], requirements: ['slug' => AccountController::SLUG])]
    public function show(string $slug): JsonResponse
    {
        $this->guard();
        $account = $this->em->getRepository(Account::class)->findOneBy(['slug' => $slug]) ?? throw new NotFoundHttpException('Compte inconnu.');
        $document = $this->entitlements->compute($account);
        $signed = $this->signer->isConfigured() ? $this->entitlements->licence($account)['token'] : null;

        return $this->json($document + ['token' => $signed, 'algorithm' => $this->signer->isConfigured() ? $this->signer->algorithm() : null]);
    }

    /** {"token"}: checks a licence key or signed document (signature, expiry) and returns its claims. */
    #[Route('/api/licences/verify', name: 'api_licence_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $this->guard();
        try {
            return $this->json(['valid' => true, 'claims' => $this->signer->verify((string) (new Payload($request->toArray()))->string('token', 20000, true))]);
        } catch (\InvalidArgumentException|\LogicException $e) {
            return $this->json(['valid' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Algorithm and, for EdDSA, the public key to give to self-hosted instances. */
    #[Route('/api/licences/public-key', name: 'api_licence_public_key', methods: ['GET'])]
    public function publicKey(): JsonResponse
    {
        $this->guard();

        return $this->json(['configured' => $this->signer->isConfigured(), 'algorithm' => $this->signer->algorithm(), 'publicKey' => $this->signer->isConfigured() ? $this->signer->publicKey() : null]);
    }

    private function guard(): void
    {
        $user = $this->getUser();
        if (!$user instanceof ApplicationUser && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Réservé aux applications et aux administrateurs.');
        }
    }
}
