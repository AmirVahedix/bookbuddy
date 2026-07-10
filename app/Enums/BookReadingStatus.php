<?php

namespace App\Enums;

enum BookReadingStatus: string
{
    case CurrentlyReading = 'currently_reading';
    case Done = 'done';
    case PlannedForFuture = 'planned_for_future';
    case Abandoned = 'abandoned';
}
