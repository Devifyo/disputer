<?php

return [
    'standard' => [
        'initial_step' => 'ticket_open',
            'steps' => [
                'ticket_open' => [
                    'label' => 'Ticket Open',
                    'description' => 'Complaint sent. Waiting for ticket assignment.',
                    'status_color' => 'slate',
                    'icon' => 'ticket',
                    'actions' => [
                        ['key' => 'mark_sent', 'label' => 'Confirm Submission', 'to_step' => 'waiting_support']
                    ]
                ],

                'waiting_support' => [
                    'label' => 'Waiting for Support',
                    'description' => 'Pending reply from customer service.',
                    'status_color' => 'amber',
                    'icon' => 'user-check',
                    'waiting_for' => 'Support Team',
                    'escalation_target' => 'Ombudsman Services',
                    'escalation_email' => 'contact@ombudsman-services.org',
                    'timeouts' => [
                        ['days' => 56, 'action' => 'escalate_manager', 'message' => '8 weeks passed. You can now go to the Ombudsman.']
                    ],
                    'actions' => [
                        ['key' => 'reply_received', 'label' => 'Reply Received', 'to_step' => 'active_discussion'],
                        ['key' => 'auto_close', 'label' => 'Resolved Self', 'to_step' => 'case_resolved_success']
                    ]
                ],

                'active_discussion' => [
                    'label' => 'In Discussion',
                    'description' => 'Negotiating a solution.',
                    'status_color' => 'blue',
                    'icon' => 'message-circle',
                    'waiting_for' => 'User',
                    'escalation_target' => 'Ombudsman Services',
                    'escalation_email' => 'contact@ombudsman-services.org',
                    'actions' => [
                        ['key' => 'escalate_manager', 'label' => 'Ask for Manager', 'to_step' => 'manager_review'],
                        ['key' => 'solve', 'label' => 'Solution Accepted', 'to_step' => 'case_resolved_success'],
                        ['key' => 'reject_solution', 'label' => 'Reject Solution', 'to_step' => 'case_resolved_failed']
                    ]
                ],

                'manager_review' => [
                    'label' => 'Manager / Tier 2',
                    'description' => 'Escalated to senior staff.',
                    'status_color' => 'purple',
                    'icon' => 'shield-alert',
                    'waiting_for' => 'Manager',
                    'escalation_target' => 'Ombudsman Services',
                    'escalation_email' => 'contact@ombudsman-services.org',
                    'actions' => [
                        ['key' => 'final_resolution_success', 'label' => 'Final Resolution Accepted', 'to_step' => 'case_resolved_success'],
                        ['key' => 'final_resolution_failed', 'label' => 'Final Resolution Rejected', 'to_step' => 'case_resolved_failed']
                    ]
                ],

                'case_resolved_success' => [
                    'label' => 'Compensation Secured',
                    'status_color' => 'emerald',
                    'icon' => 'check-circle',
                    'is_final' => true,
                    'actions' => []
                ],

                'case_resolved_failed' => [
                    'label' => 'Compensation Not Secured',
                    'status_color' => 'slate',
                    'icon' => 'x-circle',
                    'is_final' => true,
                    'actions' => []
                ]
            ]
        ]
];
