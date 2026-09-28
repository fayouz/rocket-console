<?php

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Billing\CatalogueProvider;
use App\Entity\Brick;
use App\Entity\Option;
use App\Entity\Plan;
use App\Support\Payload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The catalogue: bricks, options, plans and pricing rules. Read: any signed-in user or application; write: ROLE_ADMIN.
 * Objects are addressed by code (immutable once created). Prices in cents per unit and per month.
 */
final class CatalogueController extends AbstractController
{
    public function __construct(private readonly CatalogueProvider $provider, private readonly EntityManagerInterface $em, private readonly AuditLogger $audit)
    {
    }

    #[Route('/api/catalogue', name: 'api_catalogue', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $c = $this->provider->catalogue();

        return $this->json([
            'bricks' => array_values(array_map(static fn (Brick $b) => $b->toArray(), $c->bricks)),
            'options' => array_values(array_map(static fn (Option $o) => $o->toArray(), $c->options)),
            'plans' => array_values(array_map(static fn (Plan $p) => $p->toArray(), $c->plans)),
            'pricing' => $c->rules->toArray(),
        ]);
    }

    #[Route('/api/catalogue/bricks', name: 'api_brick_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createBrick(Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $code = (string) $p->code('code');
        $this->unique(Brick::class, $code);
        $brick = new Brick($code, (string) $p->string('name', 80, true));
        $this->applyBrick($brick, $p);
        $this->em->persist($brick);
        $this->audit->log('catalogue.brick.create', 'brick', $code, null, $request->toArray());
        $this->em->flush();

        return $this->json($brick->toArray(), 201);
    }

    #[Route('/api/catalogue/bricks/{code}', name: 'api_brick_update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateBrick(string $code, Request $request): JsonResponse
    {
        $brick = $this->find(Brick::class, $code);
        $p = new Payload($request->toArray());
        if ($p->has('name')) {
            $brick->setName((string) $p->string('name', 80, true));
        }
        $this->applyBrick($brick, $p);
        $this->audit->log('catalogue.brick.update', 'brick', $code, null, $request->toArray());
        $this->em->flush();

        return $this->json($brick->toArray());
    }

    #[Route('/api/catalogue/options', name: 'api_option_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createOption(Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $code = (string) $p->code('code');
        $this->unique(Option::class, $code);
        $option = new Option($code, (string) $p->string('name', 80, true), $this->brickCode((string) $p->code('brick')));
        $this->applyOption($option, $p);
        $this->em->persist($option);
        $this->audit->log('catalogue.option.create', 'option', $code, null, $request->toArray());
        $this->em->flush();

        return $this->json($option->toArray(), 201);
    }

    #[Route('/api/catalogue/options/{code}', name: 'api_option_update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateOption(string $code, Request $request): JsonResponse
    {
        $option = $this->find(Option::class, $code);
        $p = new Payload($request->toArray());
        if ($p->has('name')) {
            $option->setName((string) $p->string('name', 80, true));
        }
        if ($p->has('brick')) {
            $option->setBrick($this->brickCode((string) $p->code('brick')));
        }
        $this->applyOption($option, $p);
        $this->audit->log('catalogue.option.update', 'option', $code, null, $request->toArray());
        $this->em->flush();

        return $this->json($option->toArray());
    }

    #[Route('/api/catalogue/plans', name: 'api_plan_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createPlan(Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $code = (string) $p->code('code');
        $this->unique(Plan::class, $code);
        $plan = new Plan($code, (string) $p->string('name', 80, true));
        $this->applyPlan($plan, $p);
        $this->em->persist($plan);
        $this->audit->log('catalogue.plan.create', 'plan', $code, null, $request->toArray());
        $this->em->flush();

        return $this->json($plan->toArray(), 201);
    }

    #[Route('/api/catalogue/plans/{code}', name: 'api_plan_update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updatePlan(string $code, Request $request): JsonResponse
    {
        $plan = $this->find(Plan::class, $code);
        $p = new Payload($request->toArray());
        if ($p->has('name')) {
            $plan->setName((string) $p->string('name', 80, true));
        }
        $this->applyPlan($plan, $p);
        $this->audit->log('catalogue.plan.update', 'plan', $code, null, $request->toArray());
        $this->em->flush();

        return $this->json($plan->toArray());
    }

    /** Deleting is only possible for a code no subscription uses (otherwise deactivate: "active": false). */
    #[Route('/api/catalogue/{type}/{code}', name: 'api_catalogue_delete', methods: ['DELETE'], requirements: ['type' => 'bricks|options|plans'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $type, string $code): Response
    {
        $class = ['bricks' => Brick::class, 'options' => Option::class, 'plans' => Plan::class][$type];
        $entity = $this->find($class, $code);
        $column = ['bricks' => 'bricks', 'options' => 'options', 'plans' => 'plan'][$type];
        $sql = 'plan' === $column ? 'SELECT COUNT(*) FROM subscription WHERE plan = :c' : "SELECT COUNT(*) FROM subscription WHERE jsonb_exists($column::jsonb, :c)";
        $used = (int) $this->em->getConnection()->fetchOne($sql, ['c' => $code]);
        if ('bricks' === $type) {
            $used += (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM catalogue_plan WHERE jsonb_exists(bricks::jsonb, :c)', ['c' => $code]);
        }
        if ($used > 0) {
            throw new HttpException(409, 'Code utilisé par des abonnements ou des offres : désactive-le plutôt.');
        }
        $this->em->remove($entity);
        $this->audit->log('catalogue.'.rtrim($type, 's').'.delete', rtrim($type, 's'), $code);
        $this->em->flush();

        return new Response(null, 204);
    }

    #[Route('/api/catalogue/pricing', name: 'api_pricing_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updatePricing(Request $request): JsonResponse
    {
        $rules = $this->provider->rules();
        $p = new Payload($request->toArray());
        foreach (['minimumMonthlyCents' => 'setMinimumMonthlyCents', 'yearlyFreeMonths' => 'setYearlyFreeMonths', 'trialDays' => 'setTrialDays'] as $key => $setter) {
            if ($p->has($key)) {
                $rules->{$setter}((int) $p->int($key, true));
            }
        }
        if ($rules->getYearlyFreeMonths() > 11) {
            throw new HttpException(422, 'Champ « yearlyFreeMonths » : 11 au plus.');
        }
        if ($p->has('volumeTiers')) {
            $tiers = [];
            foreach ($p->array('volumeTiers') as $tier) {
                $t = new Payload(\is_array($tier) ? $tier : []);
                $percent = (int) $t->int('percent', true);
                if ($percent > 90) {
                    throw new HttpException(422, 'Remise : 90 % au plus.');
                }
                $tiers[] = ['min' => (int) $t->int('min', true, 1), 'percent' => $percent];
            }
            $rules->setVolumeTiers($tiers);
        }
        $this->audit->log('catalogue.pricing.update', 'pricing', '1', null, $request->toArray());
        $this->em->flush();

        return $this->json($rules->toArray());
    }

    private function applyBrick(Brick $b, Payload $p): void
    {
        if ($p->has('family')) {
            $b->setFamily($p->choice('family', Brick::FAMILIES));
        }
        if ($p->has('unit')) {
            $b->setUnit($p->choice('unit', Brick::UNITS));
        }
        if ($p->has('monthlyPriceCents')) {
            $b->setMonthlyPriceCents((int) $p->int('monthlyPriceCents', true));
        }
        if ($p->has('depends')) {
            $depends = $p->codes('depends');
            if (\in_array($b->getCode(), $depends, true)) {
                throw new HttpException(422, 'Une brique ne peut pas dépendre d’elle-même.');
            }
            array_map($this->brickCode(...), $depends);
            $b->setDepends($depends);
        }
        if ($p->has('description')) {
            $b->setDescription($p->string('description', 500));
        }
        if ($p->has('position')) {
            $b->setPosition((int) $p->int('position', true));
        }
        if ($p->has('active')) {
            $b->setActive($p->bool('active'));
        }
    }

    private function applyOption(Option $o, Payload $p): void
    {
        if ($p->has('unit')) {
            $o->setUnit($p->choice('unit', Brick::UNITS));
        }
        if ($p->has('monthlyPriceCents')) {
            $o->setMonthlyPriceCents((int) $p->int('monthlyPriceCents', true));
        }
        if ($p->has('grants')) {
            $grants = $p->code('grants', false);
            $o->setGrants(null === $grants ? null : $this->brickCode($grants));
        }
        if ($p->has('active')) {
            $o->setActive($p->bool('active'));
        }
    }

    private function applyPlan(Plan $plan, Payload $p): void
    {
        if ($p->has('bricks')) {
            $plan->setBricks(array_map($this->brickCode(...), $p->codes('bricks')));
        }
        if ($p->has('unit')) {
            $plan->setUnit($p->choice('unit', Brick::UNITS));
        }
        if ($p->has('unitPriceCents')) {
            $plan->setUnitPriceCents((int) $p->int('unitPriceCents', true));
        }
        if ($p->has('description')) {
            $plan->setDescription($p->string('description', 500));
        }
        if ($p->has('active')) {
            $plan->setActive($p->bool('active'));
        }
    }

    private function brickCode(string $code): string
    {
        return null !== $this->em->getRepository(Brick::class)->findOneBy(['code' => $code]) ? $code : throw new HttpException(422, \sprintf('Brique inconnue : « %s ».', $code));
    }

    /** @param class-string $class */
    private function unique(string $class, string $code): void
    {
        if (null !== $this->em->getRepository($class)->findOneBy(['code' => $code])) {
            throw new HttpException(409, \sprintf('Le code « %s » existe déjà.', $code));
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function find(string $class, string $code): object
    {
        return $this->em->getRepository($class)->findOneBy(['code' => $code]) ?? throw new NotFoundHttpException('Code inconnu.');
    }
}
