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


    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'bg-slate-100 text-slate-600 border-slate-200',
            self::SENT, self::WAITING_INSTITUTION => 'bg-blue-50 text-blue-700 border-blue-100',
            self::WAITING_USER => 'bg-rose-50 text-rose-700 border-rose-100',
            self::ESCALATED => 'bg-purple-50 text-purple-700 border-purple-100',
            self::RESOLVED, self::CLOSED => 'bg-emerald-50 text-emerald-700 border-emerald-100',
        };
    }
}