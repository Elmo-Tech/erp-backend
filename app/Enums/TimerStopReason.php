<?php

namespace App\Enums;

enum TimerStopReason: string
{
    case PAUSE = 'pause';
    case HOLD = 'hold';
    case END = 'end';
    case CONNECTION_LOST = 'connection_lost';
    case ADMIN_STOP = 'admin_stop';
}
