/**
 * Admin sidebar navigation.
 *
 * - Top-level links: single page (e.g. Dashboard)
 * - Groups: label + children (collapsible parent with nested links)
 */
export const navigationItems = [
    {
        label: 'Dashboard',
        route: 'admin.dashboard',
        startsWith: '/admin/dashboard',
        icon: 'dashboard',
    },
    {
        label: 'Operations',
        icon: 'orders',
        children: [
            {
                label: 'Global Search',
                route: 'admin.search.index',
                startsWith: '/admin/search',
                permission: 'search.view',
                icon: 'orders',
            },
            {
                label: 'Orders',
                route: 'admin.orders.index',
                startsWith: '/admin/orders',
                permission: 'orders.view',
                icon: 'orders',
            },
            {
                label: 'Support',
                route: 'admin.support.index',
                startsWith: '/admin/support',
                permission: 'support.view',
                icon: 'support',
            },
            {
                label: 'Airports',
                route: 'admin.airports.index',
                startsWith: '/admin/airports',
                permission: 'settings.manage',
                icon: 'airports',
            },
        ],
    },
    {
        label: 'Suppliers',
        icon: 'suppliers',
        children: [
            {
                label: 'Supplier profiles',
                route: 'admin.suppliers.index',
                startsWith: '/admin/suppliers',
                permission: 'suppliers.view',
                icon: 'suppliers',
            },
            {
                label: 'Provider Wallets',
                route: 'admin.provider-wallets.index',
                startsWith: '/admin/provider-wallets',
                permission: 'provider-wallets.view',
                icon: 'finance',
            },
            {
                label: 'Provider Health',
                route: 'admin.provider-health.index',
                startsWith: '/admin/provider-health',
                permission: 'provider-health.view',
                icon: 'governance',
            },
            {
                label: 'Settlements',
                route: 'admin.settlements.index',
                startsWith: '/admin/settlements',
                permission: 'settlements.view',
                icon: 'finance',
            },
        ],
    },
    {
        label: 'Finance',
        icon: 'finance',
        children: [
            {
                label: 'Finance overview',
                route: 'admin.finance.index',
                startsWith: '/admin/finance',
                permission: 'finance.view',
                icon: 'finance',
            },
            {
                label: 'Approvals',
                route: 'admin.approvals.index',
                startsWith: '/admin/approvals',
                permission: 'approvals.view',
                icon: 'governance',
            },
            {
                label: 'Governance',
                route: 'admin.governance.dashboard',
                startsWith: '/admin/governance',
                permission: 'governance.view',
                icon: 'governance',
            },
        ],
    },
    {
        label: 'Platform',
        icon: 'users',
        children: [
            {
                label: 'Users',
                route: 'admin.users.index',
                startsWith: '/admin/users',
                permission: 'users.view',
                icon: 'users',
            },
            {
                label: 'Loyalty',
                route: 'admin.loyalty.index',
                startsWith: '/admin/loyalty',
                permission: 'loyalty.view',
                icon: 'loyalty',
            },
            {
                label: 'Notifications',
                route: 'admin.notifications.index',
                startsWith: '/admin/notifications',
                permission: 'notifications.view',
                icon: 'notifications',
            },
            {
                label: 'Monitoring',
                route: 'admin.monitoring.index',
                startsWith: '/admin/monitoring',
                permission: 'monitoring.view',
                icon: 'governance',
            },
            {
                label: 'Audit Center',
                route: 'admin.audit.index',
                startsWith: '/admin/audit',
                permission: 'audit.view',
                icon: 'governance',
            },
        ],
    },
];
