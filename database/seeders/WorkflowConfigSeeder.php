<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InstitutionCategory;

class WorkflowConfigSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Workflows with Escalation Emails & Deadlines...');

        // =====================================================================
        // 1. AVIATION WORKFLOW (Strict EU261 Deadlines)
        // =====================================================================
        $aviationWorkflow = [
            'initial_step' => 'draft_complaint',
            'steps' => [
                'draft_complaint' => [
                    'label' => 'Drafting Complaint',
                    'description' => 'Gathering flight details. Claims have a statute of limitations.',
                    'status_color' => 'slate',
                    'icon' => 'file-edit',
                    'actions' => [
                        ['key' => 'send_to_airline', 'label' => 'Submit to Airline', 'to_step' => 'awaiting_airline_response', 'icon' => 'send']
                    ]
                ],
                'awaiting_airline_response' => [
                    'label' => 'Awaiting Airline Reply',
                    'description' => 'Complaint submitted. Airlines have 14 days to acknowledge receipt.',
                    'status_color' => 'amber',
                    'icon' => 'clock',
                    'waiting_for' => 'Airline',
                    
                    // --- ESCALATION DATA ---
                    'escalation_target' => 'Civil Aviation Authority (CAA)',
                    'escalation_email' => 'passengercomplaints@caa.co.uk',
                    
                    'timeouts' => [
                        ['days' => 14, 'action' => 'suggest_escalation', 'message' => '14 days passed. We recommend escalating to the regulator.']
                    ],
                    'actions' => [
                        ['key' => 'reply_received', 'label' => 'Reply Received', 'to_step' => 'negotiation_phase'],
                        ['key' => 'no_reply_escalate', 'label' => 'Ignored (Escalate)', 'to_step' => 'prepare_neb_escalation']
                    ]
                ],
                'negotiation_phase' => [
                    'label' => 'Airline Negotiation',
                    'description' => 'Reviewing the airline\'s offer.',
                    'status_color' => 'blue',
                    'icon' => 'scale',
                    'waiting_for' => 'User',
                    'escalation_target' => 'Civil Aviation Authority (CAA)',
                    'escalation_email' => 'passengercomplaints@caa.co.uk',
                    'timeouts' => [
                        ['days' => 14, 'action' => 'offer_expiry_warning', 'message' => 'Offers often expire after 2 weeks.']
                    ],
                    'actions' => [
                        ['key' => 'accept_offer', 'label' => 'Accept Offer', 'to_step' => 'case_resolved_success'],
                        ['key' => 'reject_final', 'label' => 'Reject & Escalate', 'to_step' => 'prepare_neb_escalation']
                    ]
                ],
                'prepare_neb_escalation' => [
                    'label' => 'Regulatory Escalation',
                    'description' => 'Preparing dossier for the Civil Aviation Authority.',
                    'status_color' => 'purple',
                    'icon' => 'gavel',
                    'waiting_for' => 'User',
                    'escalation_target' => 'Civil Aviation Authority (CAA)',
                    'escalation_email' => 'passengercomplaints@caa.co.uk',
                    'actions' => [
                        ['key' => 'submit_neb', 'label' => 'Submit to Regulator', 'to_step' => 'regulatory_review']
                    ]
                ],
                'regulatory_review' => [
                    'label' => 'Regulator Review',
                    'description' => 'Case is with the Aviation Authority.',
                    'status_color' => 'purple',
                    'icon' => 'landmark',
                    'waiting_for' => 'Regulator',
                    'escalation_target' => 'Alternative Dispute Resolution (ADR)',
                    'escalation_email' => null, // Typically via web portal
                    'timeouts' => [
                        ['days' => 60, 'action' => 'check_status', 'message' => 'It has been 60 days. Check the regulator portal.']
                    ],
                    'actions' => [
                        ['key' => 'ruling_win', 'label' => 'Ruling: Win', 'to_step' => 'case_resolved_success'],
                        ['key' => 'ruling_loss', 'label' => 'Ruling: Dismissed', 'to_step' => 'case_resolved_failed']
                    ]
                ],
                'case_resolved_success' => ['label' => 'Compensation Secured', 'status_color' => 'emerald', 'icon' => 'check-circle', 'is_final' => true, 'actions' => []],
                'case_resolved_failed' => ['label' => 'Case Closed', 'status_color' => 'slate', 'icon' => 'x-circle', 'is_final' => true, 'actions' => []]
            ]
        ];

        // =====================================================================
        // 2. BANKING & FINTECH WORKFLOW (Fraud / Chargebacks)
        // =====================================================================
        $bankingWorkflow = [
            'initial_step' => 'gathering_evidence',
            'steps' => [
                'gathering_evidence' => [
                    'label' => 'Evidence Collection',
                    'description' => 'Compile logs and proof of non-delivery.',
                    'status_color' => 'slate',
                    'icon' => 'file-search',
                    'timeouts' => [
                        ['days' => 60, 'action' => 'chargeback_deadline_warning', 'message' => 'Chargeback window closing soon (typically 60-120 days).']
                    ],
                    'actions' => [
                        ['key' => 'submit_dispute', 'label' => 'File Chargeback', 'to_step' => 'bank_investigation']
                    ]
                ],
                'bank_investigation' => [
                    'label' => 'Bank Investigation',
                    'description' => 'The bank has 45-90 days to investigate.',
                    'status_color' => 'amber',
                    'icon' => 'search',
                    'waiting_for' => 'Bank',
                    
                    // --- ESCALATION DATA ---
                    'escalation_target' => 'Financial Ombudsman Service',
                    'escalation_email' => 'complaint.info@financial-ombudsman.org.uk',
                    
                    'timeouts' => [
                        ['days' => 15, 'action' => 'check_provisional', 'message' => 'Check for provisional credit.'],
                        ['days' => 45, 'action' => 'check_status', 'message' => 'Investigation taking long. Contact bank support.']
                    ],
                    'actions' => [
                        ['key' => 'decision_made', 'label' => 'Decision Received', 'to_step' => 'decision_review'],
                        ['key' => 'info_request', 'label' => 'Bank Request Info', 'to_step' => 'gathering_evidence']
                    ]
                ],
                'decision_review' => [
                    'label' => 'Outcome Review',
                    'description' => 'Review the bank\'s final decision.',
                    'status_color' => 'blue',
                    'icon' => 'file-check',
                    'waiting_for' => 'User',
                    'escalation_target' => 'Financial Ombudsman Service',
                    'escalation_email' => 'complaint.info@financial-ombudsman.org.uk',
                    'actions' => [
                        ['key' => 'accept_win', 'label' => 'Dispute Won', 'to_step' => 'case_resolved_success'],
                        ['key' => 'appeal_decision', 'label' => 'Appeal / Arbitration', 'to_step' => 'ombudsman_appeal']
                    ]
                ],
                'ombudsman_appeal' => [
                    'label' => 'Ombudsman Appeal',
                    'status_color' => 'purple',
                    'icon' => 'scale',
                    'waiting_for' => 'Ombudsman',
                    'escalation_target' => 'Small Claims Court', // Final step
                    'escalation_email' => null,
                    'actions' => [
                        ['key' => 'final_ruling_win', 'label' => 'Appeal Upheld', 'to_step' => 'case_resolved_success'],
                        ['key' => 'final_ruling_loss', 'label' => 'Appeal Rejected', 'to_step' => 'case_resolved_failed']
                    ]
                ],
                'case_resolved_success' => ['label' => 'Funds Recovered', 'status_color' => 'emerald', 'icon' => 'wallet', 'is_final' => true, 'actions' => []],
                'case_resolved_failed' => ['label' => 'Dispute Closed', 'status_color' => 'slate', 'icon' => 'x-circle', 'is_final' => true, 'actions' => []]
            ]
        ];

        // =====================================================================
        // 3. GENERIC / TELECOM / UTILITIES
        // =====================================================================
        $genericWorkflow = [
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
                    'label' => 'Case Closed',
                    'status_color' => 'slate',
                    'icon' => 'x-circle',
                    'is_final' => true,
                    'actions' => []
                ]
            ]
        ];


        // =====================================================================
        // APPLY TO DATABASE
        // =====================================================================
        $this->applyWorkflow('airline', $aviationWorkflow);
        $this->applyWorkflow('bank', $bankingWorkflow);
        $this->applyWorkflow('fintech', $bankingWorkflow);
        $this->applyWorkflow('telecom', $genericWorkflow);
        $this->applyWorkflow('subscription', $genericWorkflow);
        $this->applyWorkflow('government', $genericWorkflow);
        
        $this->command->info('Workflow seeding completed successfully.');
    }

    private function applyWorkflow(string $slug, array $config)
    {
        $category = InstitutionCategory::where('slug', $slug)->first();
        if ($category) {
            $category->update(['workflow_config' => $config]);
            $this->command->info("✓ Updated {$slug} workflow.");
        } else {
            $this->command->warn("! Category not found: {$slug}");
        }
    }
}