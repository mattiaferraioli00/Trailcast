<?php

namespace App\Costants;

enum ForecastStatus: string
{
    case Available = 'available';
    case NotYetAvailable = 'not_yet_available';
    case Unavailable = 'unavailable';
}
