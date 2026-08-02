<?php

namespace App\Support\Rbac;

class RbacRegistry
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEAM_MEMBER = 'team_member';

    public const ROLE_OPERATIONS_MANAGER = 'operations_manager';

    public const ROLE_SUPPORT_AGENT = 'support_agent';

    public const ROLE_FINANCE_MANAGER = 'finance_manager';

    public const ROLE_LOYALTY_MANAGER = 'loyalty_manager';

    public const ROLE_READ_ONLY_ANALYST = 'read_only_analyst';

    /**
     * Get the supported role definitions.
     *
     * @return array<int, array{name: string, label: string, description: string}>
     */
    public static function roles(): array
    {
        return [
            [
                'name' => self::ROLE_SUPER_ADMIN,
                'label' => 'Super Admin',
                'description' => 'Full platform control with role and permission management access.',
            ],
            [
                'name' => self::ROLE_ADMIN,
                'label' => 'Admin',
                'description' => 'Operational administrator with module access based on assigned permissions.',
            ],
            [
                'name' => self::ROLE_TEAM_MEMBER,
                'label' => 'Team Member',
                'description' => 'Restricted back-office member with only explicitly granted module permissions.',
            ],
            [
                'name' => self::ROLE_OPERATIONS_MANAGER,
                'label' => 'Operations Manager',
                'description' => 'Owns the booking pipeline and operational order transitions.',
            ],
            [
                'name' => self::ROLE_SUPPORT_AGENT,
                'label' => 'Support Agent',
                'description' => 'Handles booking issues, customer follow-up, and status remediation.',
            ],
            [
                'name' => self::ROLE_FINANCE_MANAGER,
                'label' => 'Finance Manager',
                'description' => 'Reviews booking financials and finance-side order reconciliation.',
            ],
            [
                'name' => self::ROLE_LOYALTY_MANAGER,
                'label' => 'Loyalty Manager',
                'description' => 'Owns loyalty tiers, rules, benefits, and customer progression settings.',
            ],
            [
                'name' => self::ROLE_READ_ONLY_ANALYST,
                'label' => 'Read-Only Analyst',
                'description' => 'Cross-module read visibility without mutating operational or financial state.',
            ],
        ];
    }

    /**
     * Get the supported permission definitions.
     *
     * @return array<int, array{name: string, label: string, description: string}>
     */
    public static function permissions(): array
    {
        return [
            self::permission('users.view', 'users', 'view', 'View users', 'Access the users module and inspect user records.'),
            self::permission('users.create', 'users', 'create', 'Create users', 'Create administrative users from the users module.'),
            self::permission('users.update', 'users', 'update', 'Update users', 'Edit user profiles and assigned roles.'),
            self::permission('orders.view', 'orders', 'view', 'View orders', 'Access the orders module.'),
            self::permission('orders.change-status', 'orders', 'update', 'Change order status', 'Change order lifecycle states from the admin panel.'),
            self::permission('orders.update-notes', 'orders', 'update', 'Update order notes', 'Edit internal administrative notes for orders.'),
            self::permission('orders.view-history', 'orders', 'view_history', 'View order history', 'Review the operational change history for orders.'),
            self::permission('orders.financials.view', 'orders', 'view_financials', 'View order financials', 'Access order totals and currency amounts.'),
            self::permission('support.view', 'support', 'view', 'View support', 'Access the support module.'),
            self::permission('support.view-order-actions', 'support', 'view_order_actions', 'View support order actions', 'Inspect the order action workspace inside support tickets.'),
            self::permission('support.cancel-order', 'support', 'cancel', 'Cancel orders from support', 'Cancel linked orders from the support workspace.'),
            self::permission('support.full-refund', 'support', 'refund_full', 'Apply full refunds from support', 'Run full refund actions from linked support tickets.'),
            self::permission('support.partial-refund', 'support', 'refund_partial', 'Apply partial refunds from support', 'Run partial refunds and compensations from linked support tickets.'),
            self::permission('finance.view', 'finance', 'view', 'View finance', 'Access finance dashboards and reports.'),
            self::permission('finance.export', 'finance', 'export', 'Export finance reports', 'Export finance drill-down reports for audit and review.'),
            self::permission('finance.reconcile', 'finance', 'reconcile', 'Run finance reconciliation', 'Run financial reconciliation and anomaly detection workflows.'),
            self::permission('finance.reverse-refund', 'finance', 'reverse_refund', 'Reverse refunds', 'Reverse support-side refunds using compensating finance transactions.'),
            self::permission('provider-wallets.view', 'provider_wallets', 'view', 'View provider wallets', 'Access prepaid provider wallet balances and movement history.'),
            self::permission('provider-wallets.manage', 'provider_wallets', 'manage', 'Manage provider wallets', 'Create provider wallets and record deposits or adjustments.'),
            self::permission('suppliers.view', 'suppliers', 'view', 'View suppliers', 'Access supplier profiles, contracts, and commercial terms.'),
            self::permission('suppliers.manage', 'suppliers', 'manage', 'Manage suppliers', 'Create and update supplier commercial profiles.'),
            self::permission('approvals.view', 'approvals', 'view', 'View approvals', 'Access the approval queue for financial and operational actions.'),
            self::permission('approvals.approve', 'approvals', 'approve', 'Approve requests', 'Approve or reject pending refund, cancellation, and wallet actions.'),
            self::permission('settlements.view', 'settlements', 'view', 'View settlements', 'Access supplier settlement periods and variance reviews.'),
            self::permission('settlements.manage', 'settlements', 'manage', 'Manage settlements', 'Create periods, import invoices, resolve variances, and close settlements.'),
            self::permission('provider-health.view', 'provider_health', 'view', 'View provider health', 'Access the provider network operations health dashboard.'),
            self::permission('monitoring.view', 'monitoring', 'view', 'View monitoring', 'Access system health, queues, failures, and operational alerts.'),
            self::permission('monitoring.manage', 'monitoring', 'manage', 'Manage monitoring', 'Run health probes and operational monitoring actions.'),
            self::permission('audit.view', 'audit', 'view', 'View audit center', 'Access the unified audit trail of who changed what across the platform.'),
            self::permission('search.view', 'search', 'view', 'Use global search', 'Search orders, customers, tickets, wallets, and settlements from one place.'),
            self::permission('governance.view', 'governance', 'view', 'View governance dashboard', 'Access the centralized governance and operations control center.'),
            self::permission('loyalty.view', 'loyalty', 'view', 'View loyalty', 'Access the loyalty dashboard, tiers, and analytics.'),
            self::permission('loyalty.manage', 'loyalty', 'update', 'Manage loyalty tiers', 'Edit loyalty tiers and their top-level profile settings.'),
            self::permission('loyalty.manage-rules', 'loyalty', 'update_rules', 'Manage loyalty rules', 'Modify dynamic upgrade and downgrade rules from the admin panel.'),
            self::permission('loyalty.manage-benefits', 'loyalty', 'update_benefits', 'Manage loyalty benefits', 'Edit tier privileges and customer benefits.'),
            self::permission('loyalty.settings.manage', 'loyalty', 'manage_settings', 'Manage loyalty settings', 'Manage global loyalty settings and pricing-safe loyalty configuration.'),
            self::permission('notifications.view', 'notifications', 'view', 'View notifications', 'Access notification logs, channel health, and delivery visibility.'),
            self::permission('notifications.manage-templates', 'notifications', 'update_templates', 'Manage notification templates', 'Edit notification templates and channel settings.'),
            self::permission('notifications.retry-failed', 'notifications', 'retry', 'Retry failed notifications', 'Retry failed notification deliveries from the admin dashboard.'),
            self::permission('settings.manage', 'admin', 'manage_settings', 'Manage settings', 'Access platform settings and global administrative configuration.'),
            self::permission('partners.view', 'partners', 'view', 'View partners', 'Access partner integrations, API keys metadata, and webhook endpoints.'),
            self::permission('partners.manage', 'partners', 'manage', 'Manage partners', 'Create partners, issue/revoke API keys, and configure webhook endpoints.'),
        ];
    }

    /**
     * Get the full list of permission names.
     *
     * @return array<int, string>
     */
    public static function permissionNames(): array
    {
        return array_column(self::permissions(), 'name');
    }

    /**
     * Get the default permission map for each role.
     *
     * @return array<string, array<int, string>>
     */
    public static function rolePermissions(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => self::permissionNames(),
            self::ROLE_ADMIN => [
                'users.view',
                'users.create',
                'users.update',
                'orders.view',
                'orders.change-status',
                'orders.update-notes',
                'orders.view-history',
                'support.view',
                'support.view-order-actions',
                'support.cancel-order',
                'support.full-refund',
                'support.partial-refund',
                'finance.view',
                'finance.export',
                'finance.reconcile',
                'finance.reverse-refund',
                'provider-wallets.view',
                'provider-wallets.manage',
                'suppliers.view',
                'suppliers.manage',
                'approvals.view',
                'approvals.approve',
                'settlements.view',
                'settlements.manage',
                'provider-health.view',
                'monitoring.view',
                'monitoring.manage',
                'audit.view',
                'search.view',
                'governance.view',
                'loyalty.view',
                'loyalty.manage',
                'loyalty.manage-rules',
                'loyalty.manage-benefits',
                'notifications.view',
                'notifications.manage-templates',
                'notifications.retry-failed',
                'settings.manage',
                'partners.view',
                'partners.manage',
            ],
            self::ROLE_TEAM_MEMBER => [
                'users.view',
                'orders.view',
                'support.view',
                'search.view',
            ],
            self::ROLE_OPERATIONS_MANAGER => [
                'orders.view',
                'orders.change-status',
                'orders.update-notes',
                'orders.view-history',
                'approvals.view',
                'approvals.approve',
                'provider-health.view',
                'monitoring.view',
                'audit.view',
                'search.view',
            ],
            self::ROLE_SUPPORT_AGENT => [
                'orders.view',
                'orders.change-status',
                'orders.update-notes',
                'orders.view-history',
                'loyalty.view',
                'support.view',
                'support.view-order-actions',
                'support.cancel-order',
                'support.full-refund',
                'support.partial-refund',
                'approvals.view',
                'provider-health.view',
                'audit.view',
                'search.view',
            ],
            self::ROLE_FINANCE_MANAGER => [
                'orders.view',
                'orders.financials.view',
                'support.view',
                'support.view-order-actions',
                'finance.view',
                'finance.export',
                'finance.reconcile',
                'finance.reverse-refund',
                'provider-wallets.view',
                'provider-wallets.manage',
                'suppliers.view',
                'suppliers.manage',
                'approvals.view',
                'approvals.approve',
                'settlements.view',
                'settlements.manage',
                'provider-health.view',
                'monitoring.view',
                'monitoring.manage',
                'audit.view',
                'search.view',
                'governance.view',
                'loyalty.view',
                'notifications.view',
                'partners.view',
            ],
            self::ROLE_LOYALTY_MANAGER => [
                'orders.view',
                'loyalty.view',
                'loyalty.manage',
                'loyalty.manage-rules',
                'loyalty.manage-benefits',
                'notifications.view',
            ],
            self::ROLE_READ_ONLY_ANALYST => [
                'users.view',
                'orders.view',
                'orders.view-history',
                'orders.financials.view',
                'support.view',
                'finance.view',
                'finance.export',
                'provider-wallets.view',
                'suppliers.view',
                'approvals.view',
                'settlements.view',
                'provider-health.view',
                'monitoring.view',
                'audit.view',
                'search.view',
                'governance.view',
                'loyalty.view',
                'notifications.view',
                'partners.view',
            ],
        ];
    }

    /**
     * @return array{name: string, module: string, action: string, scope: string, label: string, description: string}
     */
    private static function permission(string $name, string $module, string $action, string $label, string $description, string $scope = 'all'): array
    {
        return [
            'name' => $name,
            'module' => $module,
            'action' => $action,
            'scope' => $scope,
            'label' => $label,
            'description' => $description,
        ];
    }
}