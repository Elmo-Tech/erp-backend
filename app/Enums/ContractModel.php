<?php

namespace App\Enums;

enum ContractModel: string
{
    case STANDARD         = 'standard';
    case SAAS_SUBSCRIPTION = 'saas_subscription';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD          => 'Standard',
            self::SAAS_SUBSCRIPTION => 'SaaS Subscription',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
