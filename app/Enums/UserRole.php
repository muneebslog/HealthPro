<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Doc = 'doc';
    case Staff = 'staff';
}
