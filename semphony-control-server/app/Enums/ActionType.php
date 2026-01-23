<?php

namespace App\Enums;

enum ActionType: string
{
    case ButtonPress = 'button_press';
    case Request = 'request';
    case Heartbeat = 'heartbeat';
}

