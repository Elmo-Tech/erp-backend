<?php

namespace App\Enums;

enum TimerStopReason: string
{
    case PAUSE = 'pause';
    case HOLD = 'hold';
    case END = 'end';
    case CONNECTION_LOST = 'connection_lost';
    case AFTER_HOURS_TIMEOUT = 'after_hours_timeout';
    case ADMIN_STOP = 'admin_stop';
}
