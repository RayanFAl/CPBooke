export const navigationItems = [
    {
        label: 'Dashboard',
        route: 'admin.dashboard',
        startsWith: '/admin/dashboard',
    },
    {
        label: 'Users',
        route: 'admin.users.index',
        startsWith: '/admin/users',
        permission: 'users.view',
    },
    {
        label: 'Orders',
        route: 'admin.orders.index',
        startsWith: '/admin/orders',
        permission: 'orders.view',
    },
    {
        label: 'Finance',
        route: 'admin.finance.index',
        startsWith: '/admin/finance',
        permission: 'finance.view',
    },
    {
        label: 'Governance',
        route: 'admin.governance.dashboard',
        startsWith: '/admin/governance',
        permission: 'governance.view',
    },
    {
        label: 'Support',
        route: 'admin.support.index',
        startsWith: '/admin/support',
        permission: 'support.view',
    },
    {
        label: 'Loyalty',
        route: 'admin.loyalty.index',
        startsWith: '/admin/loyalty',
        permission: 'loyalty.view',
    },
    {
        label: 'Notifications',
        route: 'admin.notifications.index',
        startsWith: '/admin/notifications',
        permission: 'notifications.view',
    },
];