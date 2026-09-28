/**
 * Identity of the application and its own menu entries: the rest of the interface (layout, dashboard,
 * administration pages) is shared by every Rocket application.
 */
export default defineAppConfig({
  ui: {
    colors: {
      primary: 'amber',
      neutral: 'slate',
    },
  },
  rocket: {
    id: 'stock',
    name: 'Rocket Console',
    icon: 'i-lucide-package',
    // Login page subtitle.
    tagline: 'Le stock de tes lieux : consommables, linge, équipements, courses et bilan.',
    // Public pages (no account): none.
    publicPaths: [] as string[],
    // Main menu: the domain pages ("label" entries start a group).
    navigation: [
      { label: 'Ménage', type: 'label' },
      { label: 'Ménages du jour', icon: 'i-lucide-package', to: '/menage' },
      { label: 'Lieux', icon: 'i-lucide-map-pin', to: '/places' },
    ] as { label: string, icon?: string, to?: string, type?: 'label', exact?: boolean, exactQuery?: boolean, admin?: boolean }[],
    // Extra entries of the Administration menu.
    adminNavigation: [
    ] as { label: string, icon: string, to: string, exactQuery?: boolean }[],
    // "Services & raccourcis" of the dashboard, besides the documentation, changelog and API.
    shortcuts: [] as { label: string, description: string, icon: string, to: string, admin?: boolean }[],
    // Hero banner of the dashboard: one quote per day.
    quotes: [
      ['Ce qui se compte se pilote.', 'Adage d’intendance'],
      ['Le rouleau qui manque est toujours le dernier.', 'Adage de logement'],
      ['Ce qui n’est pas noté n’est pas fait.', 'Adage de gestion'],
    ] as [string, string][],
  },
})
