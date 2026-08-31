/**
 * Admin sidebar navigation.
 *
 * Daily work first, then money and providers, then content, then system tools.
 */
export const navigationItems = [
    {
        label: 'Dashboard',
        route: 'admin.dashboard',
        startsWith: '/admin/dashboard',
        icon: 'dashboard',
    },
    {
        label: 'People',
        icon: 'users',
        children: [
            {
                label: 'Customers',
                route: 'admin.customers.index',
                startsWith: '/admin/customers',
                permission: 'users.view',
                icon: 'users',
            },
            {
                label: 'Team',
                route: 'admin.team.index',
                startsWith: '/admin/team',
                permission: 'users.view',
                icon: 'team',
            },
        ],
    },
    {
        label: 'Operations',
        icon: 'orders',
        children: [
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
                label: 'Customer Wallets',
                route: 'admin.customer-wallets.index',
                startsWith: '/admin/customer-wallets',
                permission: 'customer-wallets.view',
                icon: 'finance',
            },
            {
                label: 'Approvals',
                route: 'admin.approvals.index',
                startsWith: '/admin/approvals',
                permission: 'approvals.view',
                icon: 'governance',
            },
        ],
    },
    {
        label: 'Providers',
        icon: 'suppliers',
        children: [
            {
                label: 'Providers',
                route: 'admin.suppliers.index',
                startsWith: '/admin/suppliers',
                permission: 'suppliers.view',
                icon: 'suppliers',
            },
            {
                label: 'Provider ledger',
                route: 'admin.provider-wallets.index',
                startsWith: '/admin/provider-wallets',
                permission: 'provider-wallets.view',
                icon: 'finance',
            },
            {
                label: 'Settlements',
                route: 'admin.settlements.index',
                startsWith: '/admin/settlements',
                permission: 'settlements.view',
                icon: 'finance',
            },
            {
                label: 'Provider Health',
                route: 'admin.provider-health.index',
                startsWith: '/admin/provider-health',
                permission: 'provider-health.view',
                icon: 'health',
            },
        ],
    },
    {
        label: 'Content',
        icon: 'content',
        children: [
            {
                label: 'Airports',
                route: 'admin.airports.index',
                startsWith: '/admin/airports',
                permission: 'settings.manage',
                icon: 'airports',
            },
            {
                label: 'Products',
                route: 'admin.home.index',
                startsWith: '/admin/home',
                permission: 'settings.manage',
                icon: 'content',
            },
            {
                label: 'Options & Market',
                route: 'admin.catalog.index',
                startsWith: '/admin/catalog',
                permission: 'settings.manage',
                icon: 'content',
            },
            {
                label: 'Policies & Terms',
                route: 'admin.content.index',
                startsWith: '/admin/content',
                permission: 'settings.manage',
                icon: 'governance',
            },
        ],
    },
    {
        label: 'Engage',
        icon: 'notifications',
        children: [
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
        ],
    },
    {
        label: 'System',
        icon: 'settings',
        children: [
            {
                label: 'Global Search',
                route: 'admin.search.index',
                startsWith: '/admin/search',
                permission: 'search.view',
                icon: 'search',
            },
            {
                label: 'Monitoring',
                route: 'admin.monitoring.index',
                startsWith: '/admin/monitoring',
                permission: 'monitoring.view',
                icon: 'monitoring',
            },
            {
                label: 'Governance',
                route: 'admin.governance.dashboard',
                startsWith: '/admin/governance',
                permission: 'governance.view',
                icon: 'governance',
            },
            {
                label: 'Audit Center',
                route: 'admin.audit.index',
                startsWith: '/admin/audit',
                permission: 'audit.view',
                icon: 'governance',
            },
            {
                label: 'Partners',
                route: 'admin.partners.index',
                startsWith: '/admin/partners',
                permission: 'partners.view',
                icon: 'governance',
            },
            {
                label: 'Settings',
                route: 'admin.settings.index',
                startsWith: '/admin/settings',
                permission: 'settings.manage',
                icon: 'settings',
            },
            {
                label: 'Mobile App',
                route: 'admin.mobile-app.index',
                startsWith: '/admin/mobile-app',
                permission: 'settings.manage',
                icon: 'governance',
            },
            {
                label: 'AI Assistant',
                route: 'admin.ai.settings.index',
                startsWith: '/admin/ai/settings',
                permission: 'settings.manage',
                icon: 'governance',
            },
            {
                label: 'AI Request Logs',
                route: 'admin.ai.logs.index',
                startsWith: '/admin/ai/logs',
                permission: 'settings.manage',
                icon: 'governance',
            },
        ],
    },
];
