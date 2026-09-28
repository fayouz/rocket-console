<?php

namespace App\Billing;

/** A subscription that breaks the catalogue rules (unknown codes, missing dependency…): one French message per problem. */
final class InvalidSubscription extends \DomainException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
