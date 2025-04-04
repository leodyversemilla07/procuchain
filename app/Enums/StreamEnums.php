<?php

namespace App\Enums;

enum StreamEnums: string
{
    case DOCUMENTS = 'procurement.documents';
    case STATUS = 'procurement.status';
    case EVENTS = 'procurement.events';
    case CORRECTION = 'procurement.correction';
}
