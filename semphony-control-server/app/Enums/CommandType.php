<?php

namespace App\Enums;

enum CommandType: string
{
    case ClickButton = 'clickButton';
    case GotoButton = 'gotoButton';
}
