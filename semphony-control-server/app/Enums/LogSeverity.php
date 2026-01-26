<?php

namespace App\Enums;

enum LogSeverity: string
{
    case Info = 'info';
    case Error = 'error';
    case Critical = 'critical';
}
