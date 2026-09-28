<?php

namespace App\Dashboard;

use App\Controller\OverviewController;
use Rocket\Core\Dashboard\DashboardSectionInterface;
use Rocket\Core\Entity\User;

/** The operator figures on the shared dashboard: MRR, active accounts, trials and the trials ending soon. */
final class ConsoleSection implements DashboardSectionInterface
{
    public function __construct(private readonly OverviewController $overview)
    {
    }

    public function build(User $user, bool $admin, \DateTimeImmutable $from, \DateTimeImmutable $previousFrom): array
    {
        if (!$admin) {
            return ['kpis' => [], 'series' => [], 'daily' => [], 'recent' => null, 'quickActions' => []];
        }
        $f = $this->overview->figures();

        return [
            'kpis' => [
                ['id' => 'console_mrr', 'label' => 'MRR estimé (€ HT)', 'value' => round($f['mrrCents'] / 100, 2), 'format' => 'number', 'icon' => 'i-lucide-trending-up', 'tone' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'],
                ['id' => 'console_active', 'label' => 'Comptes actifs', 'value' => $f['accountsByStatus']['active'], 'format' => 'number', 'icon' => 'i-lucide-building-2', 'tone' => 'bg-primary/10 text-primary'],
                ['id' => 'console_trials', 'label' => 'Essais en cours', 'value' => $f['accountsByStatus']['trial'], 'format' => 'number', 'icon' => 'i-lucide-hourglass', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
            ],
            'series' => [],
            'daily' => [],
            'recent' => [
                'title' => 'Essais qui se terminent',
                'link' => '/accounts?status=trial',
                'empty' => 'Aucun essai en cours.',
                'items' => array_map(static fn (array $t) => [
                    'id' => $t['slug'],
                    'title' => $t['name'],
                    'subtitle' => \sprintf('%s € / mois', number_format($t['monthlyCents'] / 100, 2, ',', ' ')),
                    'at' => $t['trialEndsAt'],
                    'badge' => 'Essai',
                    'badgeColor' => 'warning',
                    'link' => '/accounts/'.$t['slug'],
                ], $f['trialsEnding']),
            ],
            'quickActions' => [
                ['label' => 'Comptes', 'icon' => 'i-lucide-building-2', 'to' => '/accounts', 'tone' => 'bg-primary/10 text-primary'],
                ['label' => 'Catalogue', 'icon' => 'i-lucide-blocks', 'to' => '/catalogue', 'tone' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400'],
                ['label' => 'Journal', 'icon' => 'i-lucide-scroll-text', 'to' => '/journal', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
            ],
        ];
    }
}
