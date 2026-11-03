<?php

namespace App\Enums;

/**
 * Business roles confirmed by Faran, plus two system roles (finance, admin)
 * required by the governance model of the brief (§2).
 */
enum Role: string
{
    case SalesExpert = 'sales_expert';
    case SalesHead = 'sales_head';
    case MarketingManager = 'marketing_manager';
    case SalesVp = 'sales_vp';
    case Finance = 'finance';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::SalesExpert => 'کارشناس فروش',
            self::SalesHead => 'رئیس فروش',
            self::MarketingManager => 'مدیر بازاریابی',
            self::SalesVp => 'معاونت فروش و بازاریابی',
            self::Finance => 'مالی',
            self::Admin => 'مدیر سامانه',
        };
    }

    /** Roles that are paid commission by the engine. */
    public function earnsCommission(): bool
    {
        return in_array($this, [self::SalesExpert, self::SalesHead], true);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::SalesExpert => ['dashboard.own', 'action_plan.submit', 'activity.submit', 'calc.view'],
            self::SalesHead => ['dashboard.own', 'dashboard.team', 'action_plan.submit', 'action_plan.review', 'activity.submit', 'activity.review', 'calc.view'],
            self::MarketingManager => ['dashboard.own', 'dashboard.team', 'dashboard.all', 'imports.view', 'targets.manage', 'customers.manage',
                'action_plan.review', 'activity.review', 'calc.view'],
            self::SalesVp => ['dashboard.own', 'dashboard.team', 'dashboard.all', 'imports.view', 'audit.view', 'rules.view', 'targets.manage',
                'customers.manage', 'action_plan.review', 'activity.review', 'calc.view', 'calc.run'],
            self::Finance => ['dashboard.all', 'imports.view', 'imports.manage', 'audit.view', 'rules.view', 'targets.manage', 'customers.manage',
                'calc.view', 'calc.run'],
            // Admin manages users and the system, but has no automatic right to financial data (brief §2).
            self::Admin => ['users.manage', 'audit.view', 'system.manage'],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
