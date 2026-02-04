<?php

namespace App\Enums;

enum CaseStatus: string
{
    case DRAFT = 'Draft';
    case SENT = 'Sent';
    case WAITING_INSTITUTION = 'Waiting for Reply';
    case WAITING_USER = 'Action Required';
    case ESCALATED = 'Escalated';
    case RESOLVED = 'Resolved';
    case CLOSED = 'Closed';
}