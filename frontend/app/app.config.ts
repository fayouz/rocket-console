/**
 * Identity of the application and its own menu entries: the rest of the interface (layout, dashboard,
 * administration pages) is shared by every Rocket application.
 */
export default defineAppConfig({
  ui: {
    colors: {
      primary: 'violet',
      neutral: 'slate',
    },
  },
  rocket: {
    id: 'console',
    name: 'Rocket Console',
    icon: 'i-lucide-rocket',
    // Login page subtitle.
    tagline: 'La console d’exploitation de la suite Rocket : comptes, abonnements, catalogue et licences.',
    // Public pages (no account): the self-service sign-up with offer choice.
    publicPaths: ['/inscription'] as string[],
    // Main menu: the domain pages ("label" entries start a group).
    navigation: [
      { label: 'Exploitation', type: 'label' },
      { label: 'Comptes', icon: 'i-lucide-building-2', to: '/accounts' },
      { label: 'Revenus', icon: 'i-lucide-trending-up', to: '/revenus' },
      { label: 'Catalogue', icon: 'i-lucide-blocks', to: '/catalogue' },
      { label: 'Journal', icon: 'i-lucide-scroll-text', to: '/journal' },
    ] as { label: string, icon?: string, to?: string, type?: 'label', exact?: boolean, exactQuery?: boolean, admin?: boolean }[],
    // Extra entries of the Administration menu.
    adminNavigation: [
    ] as { label: string, icon: string, to: string, exactQuery?: boolean }[],
    // "Services & raccourcis" of the dashboard, besides the documentation, changelog and API.
    shortcuts: [] as { label: string, description: string, icon: string, to: string, admin?: boolean }[],
    // Hero banner of the dashboard: one quote per day.
    quotes: [
      ['Ce qui se mesure s’améliore.', 'Adage de gestion'],
      ['Un client bien servi en amène deux.', 'Adage de commerce'],
      ['Le meilleur produit est celui qu’on peut vendre à la carte.', 'Adage SaaS'],
    ] as [string, string][],
  },
})
