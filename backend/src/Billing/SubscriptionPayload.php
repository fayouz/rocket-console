<?php

namespace App\Billing;

use App\Entity\Subscription;
use App\Support\Payload;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Applies a subscription JSON body (plan, bricks, options, quantities, period, endsAt) — shared by the operator API and the public sign-up. */
final class SubscriptionPayload
{
    public static function apply(Subscription $sub, Payload $p): void
    {
        if ($p->has('plan')) {
            $sub->setPlan($p->code('plan', false));
        }
        if ($p->has('bricks')) {
            $sub->setBricks($p->codes('bricks'));
        }
        if ($p->has('options')) {
            $sub->setOptions($p->codes('options'));
        }
        if ($p->has('quantities')) {
            $q = new Payload($p->array('quantities'));
            $values = [];
            foreach (Subscription::QUANTITIES as $key) {
                if ($q->has($key)) {
                    $values[$key] = (int) $q->int($key, true);
                }
            }
            $sub->setQuantities($values);
        }
        if ($p->has('period')) {
            $sub->setPeriod($p->choice('period', Subscription::PERIODS));
        }
        if ($p->has('endsAt')) {
            $sub->setEndsAt($p->date('endsAt'));
        }
        if (null !== $sub->getEndsAt() && $sub->getEndsAt() <= $sub->getStartsAt()) {
            throw new HttpException(422, 'La fin doit suivre le début.');
        }
    }
}
