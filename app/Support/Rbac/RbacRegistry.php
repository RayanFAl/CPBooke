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
            ['name' => 'users.view', 'label' => 'View users', 'description' => 'Access the users module and inspect user records.'],
            ['name' => 'users.create', 'label' => 'Create users', 'description' => 'Create administrative users from the users module.'],
            ['name' => 'users.update', 'label' => 'Update users', 'description' => 'Edit user profiles and assigned roles.'],
            ['name' => 'orders.view', 'label' => 'View orders', 'description' => 'Access the orders module.'],
            ['name' => 'orders.change-status', 'label' => 'Change order status', 'description' => 'Change order lifecycle states from the admin panel.'],
            ['name' => 'orders.financials.view', 'label' => 'View order financials', 'description' => 'Access order totals and currency amounts.'],
            ['name' => 'support.view', 'label' => 'View support', 'description' => 'Access the support module.'],
            ['name' => 'finance.view', 'label' => 'View finance', 'description' => 'Access finance dashboards and reports.'],
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
                'support.view',
                'finance.view',
            ],
            self::ROLE_TEAM_MEMBER => [
                'users.view',
                'orders.view',
                'support.view',
            ],
            self::ROLE_OPERATIONS_MANAGER => [
                'orders.view',
                'orders.change-status',
            ],
            self::ROLE_SUPPORT_AGENT => [
                'orders.view',
                'orders.change-status',
                'support.view',
            ],
            self::ROLE_FINANCE_MANAGER => [
                'orders.view',
                'orders.financials.view',
                'finance.view',
            ],
        ];
    }
}