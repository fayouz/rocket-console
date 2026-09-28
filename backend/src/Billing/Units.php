<?php

namespace App\Billing;

final class Units
{
    public static function label(string $unit): string
    {
        return match ($unit) {
            'per_property' => 'logement',
            'per_place' => 'lieu',
            'per_screen' => 'écran',
            'per_mailbox' => 'boîte',
            default => 'forfait',
        };
    }
}
