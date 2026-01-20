<?php

namespace App\Enums;

enum ActionType: string
{
    case ButtonPress = 'button_press';
    case Heartbeat = 'heartbeat';
}

