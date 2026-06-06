<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
    case AKADEMIK = 'akademik';
    case KAPRODI = 'kaprodi';
}
