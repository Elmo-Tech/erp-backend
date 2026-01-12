<?php

namespace App\Enums;

enum LeadTemperature: string
{
    case HOT = 'hot';
    case WARM = 'warm';
    case COLD = 'cold';

    public function label(): string
    {
        return match($this) {
            self::HOT => __('enums.lead_temperature.hot'),
            self::WARM => __('enums.lead_temperature.warm'),
            self::COLD => __('enums.lead_temperature.cold'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::HOT => 'red',
            self::WARM => 'orange',
            self::COLD => 'blue',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::HOT => '🔥',
            self::WARM => '🌤️',
            self::COLD => '❄️',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::HOT => __('enums.lead_temperature.hot_desc'),
            self::WARM => __('enums.lead_temperature.warm_desc'),
            self::COLD => __('enums.lead_temperature.cold_desc'),
        };
    }
}
