<?php

namespace App\Enums;

enum StatusPendaftarEnum: string
{
    case BARU = 'Baru';
    case AI_PROCESSING = 'AI Processing';
    case REVIEW_AKADEMIK = 'Review Akademik';
    case PENDING_KAPRODI = 'Pending Kaprodi';
    case REVISI = 'Revisi';
    case APPROVED = 'Approved';
    case REJECTED = 'Rejected';
}
