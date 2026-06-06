<?php

namespace App\Enums;

enum StatusPendaftarEnum: string
{
    case BARU = 'Baru';
    case AI_PROCESSING = 'AI Processing';
    case PENDING_KAPRODI = 'Pending Kaprodi';
    case REVISI = 'Revisi';
    case APPROVED = 'Approved';
    case REJECTED = 'Rejected';
}
