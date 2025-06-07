<?php

namespace App\Enums;

enum UserRoleEnums: string
{
    case BAC_SECRETARIAT = 'bac_secretariat';
    case BAC_CHAIRMAN = 'bac_chairman';
    case HOPE = 'hope';
    case ADMIN = 'admin';
}
