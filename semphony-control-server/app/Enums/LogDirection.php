<?php

namespace App\Enums;

enum LogDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}

