<?php

namespace App\Billing;

/**
 * Catalogue rules of a subscription: known and active codes, options on an enabled brick, dependencies
 * (Brick::depends, e.g. PMS requires Place) and a quantity for the plan. A brick enabled only through an option
 * (Host + option Ménage) runs inside its parent brick, which covers its dependencies.
 */
final class SubscriptionRules
{
    /**
     * Bricks enabled by the draft (plan ∪ à la carte ∪ granted by options), sorted.
     *
     * @return list<string>
     *
     * @throws InvalidSubscription
     */
    public function enabledBricks(Catalogue $catalogue, SubscriptionDraft $draft): array
    {
        $errors = [];
        $enabled = [];
        $granted = [];
        if (null !== $draft->plan) {
            $plan = $catalogue->plans[$draft->plan] ?? null;
            if (null === $plan || !$plan->isActive()) {
                $errors[] = \sprintf('Offre inconnue : « %s ».', $draft->plan);
            } else {
                $enabled = $plan->getBricks();
                if (0 === $draft->quantityFor($plan->getUnit())) {
                    $errors[] = \sprintf('L’offre « %s » demande une quantité (%s).', $plan->getName(), Units::label($plan->getUnit()));
                }
            }
        }
        foreach ($draft->bricks as $code) {
            $brick = $catalogue->bricks[$code] ?? null;
            if (null === $brick || !$brick->isActive()) {
                $errors[] = \sprintf('Brique inconnue : « %s ».', $code);
                continue;
            }
            $enabled[] = $code;
        }
        foreach ($draft->options as $code) {
            $option = $catalogue->options[$code] ?? null;
            if (null === $option || !$option->isActive()) {
                $errors[] = \sprintf('Option inconnue : « %s ».', $code);
                continue;
            }
            if (!\in_array($option->getBrick(), $enabled, true)) {
                $errors[] = \sprintf('L’option « %s » demande la brique « %s ».', $option->getName(), $option->getBrick());
            }
            if (null !== $option->getGrants()) {
                $enabled[] = $option->getGrants();
                $granted[] = $option->getGrants();
            }
        }
        $enabled = array_values(array_unique($enabled));
        foreach ($enabled as $code) {
            if (\in_array($code, $granted, true) && !\in_array($code, $draft->bricks, true) && !\in_array($code, null !== $draft->plan ? ($catalogue->plans[$draft->plan] ?? null)?->getBricks() ?? [] : [], true)) {
                continue; // embedded in its parent brick by an option (Host + Ménage): the parent covers the dependencies
            }
            foreach (($catalogue->bricks[$code] ?? null)?->getDepends() ?? [] as $dependency) {
                if (!\in_array($dependency, $enabled, true)) {
                    $errors[] = \sprintf('La brique « %s » demande la brique « %s ».', $code, $dependency);
                }
            }
        }
        if ([] !== $errors) {
            throw new InvalidSubscription(array_values(array_unique($errors)));
        }
        sort($enabled);

        return $enabled;
    }
}
