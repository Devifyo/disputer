<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InstitutionCategory;

class WorkflowConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ==========================================
        // 1. AVIATION WORKFLOW (Complex, Regulation Heavy)
        // ==========================================
        $airlineWorkflow = [
            'initial_step' => 'draft',
            'steps' => [
                'draft' => [
                    'label' => 'Draft Complaint',
                    'description' => 'Drafting the initial formal complaint letter.',
                    'status_color' => 'gray',
                    'actions' => [
                        [
                            'key' => 'send_email',
                            'label' => 'Send Complaint',
                            'to_step' => 'waiting_for_response',
                            'required_fields' => ['body_text', 'subject']
                        ]
                    ]
                ],
                'waiting_for_response' => [
                    'label' => 'Waiting for Airline',
                    'description' => 'Complaint sent. Waiting for the airline to reply.',
                    'status_color' => 'amber',
                    'timeouts' => [
                        [
                            'days' => 14,
                            'action' => 'suggest_escalation', 
                            'message' => 'It has been 14 days. We recommend sending a follow-up or escalating.'
                        ]
                    ],
                    'actions' => [
                        ['key' => 'mark_response_received', 'label' => 'I received a reply', 'to_step' => 'reviewing_response'],
                        ['key' => 'mark_no_reply', 'label' => 'No Reply (Escalate)', 'to_step' => 'escalation_draft']
                    ]
                ],
                'reviewing_response' => [
                    'label' => 'Reviewing Reply',
                    'description' => 'Review the airline\'s offer or rejection.',
                    'status_color' => 'blue',
                    'actions' => [
                        ['key' => 'accept_offer', 'label' => 'Accept Offer', 'to_step' => 'resolved_success'],
                        ['key' => 'reject_and_escalate', 'label' => 'Reject & Escalate', 'to_step' => 'escalation_draft'],
                        ['key' => 'reply_continue', 'label' => 'Reply & Keep Waiting', 'to_step' => 'waiting_for_response']
                    ]
                ],
                'escalation_draft' => [
                    'label' => 'Drafting Escalation',
                    'description' => 'Preparing submission for the National Enforcement Body (NEB).',
                    'status_color' => 'purple',
                    'actions' => [
                        ['key' => 'send_escalation', 'label' => 'Submit Escalation', 'to_step' => 'escalated_pending']
                    ]
                ],
                'escalated_pending' => [
                    'label' => 'Regulatory Review',
                    'description' => 'Case is with the Regulator. This can take up to 90 days.',
                    'status_color' => 'purple',
                    'timeouts' => [
                        ['days' => 30, 'action' => 'check_status', 'message' => '30 Day Check-in: Check regulatory portal status.']
                    ],
                    'actions' => [
                        ['key' => 'mark_resolved', 'label' => 'Case Won', 'to_step' => 'resolved_success'],
                        ['key' => 'mark_failed', 'label' => 'Case Dismissed', 'to_step' => 'resolved_failed']
                    ]
                ],
                'resolved_success' => ['label' => 'Won / Settled', 'is_final' => true, 'status_color' => 'emerald'],
                'resolved_failed' => ['label' => 'Closed / Dismissed', 'is_final' => true, 'status_color' => 'slate']
            ]
        ];

        // ==========================================
        // 2. BANKING & FINTECH WORKFLOW (Standard Dispute)
        // ==========================================
        $financeWorkflow = [
            'initial_step' => 'draft',
            'steps' => [
                'draft' => [
                    'label' => 'Draft Dispute',
                    'description' => 'Prepare your transaction dispute details.',
                    'status_color' => 'gray',
                    'actions' => [['key' => 'submit', 'label' => 'Submit to Bank', 'to_step' => 'bank_review']]
                ],
                'bank_review' => [
                    'label' => 'Bank Investigation',
                    'description' => 'The bank has up to 90 days to investigate.',
                    'status_color' => 'amber',
                    'timeouts' => [
                        ['days' => 10, 'action' => 'temp_credit_check', 'message' => 'Check if temporary credit was applied.']
                    ],
                    'actions' => [
                        ['key' => 'outcome_received', 'label' => 'Outcome Received', 'to_step' => 'outcome_review']
                    ]
                ],
                'outcome_review' => [
                    'label' => 'Review Outcome',
                    'description' => 'Did the bank rule in your favor?',
                    'status_color' => 'blue',
                    'actions' => [
                        ['key' => 'accept', 'label' => 'Accept Decision', 'to_step' => 'resolved_success'],
                        ['key' => 'appeal', 'label' => 'File Appeal', 'to_step' => 'appeal_review']
                    ]
                ],
                'appeal_review' => [
                    'label' => 'Appeal / Ombudsman',
                    'status_color' => 'purple',
                    'actions' => [
                        ['key' => 'final_win', 'label' => 'Appeal Won', 'to_step' => 'resolved_success'],
                        ['key' => 'final_loss', 'label' => 'Appeal Lost', 'to_step' => 'resolved_failed']
                    ]
                ],
                'resolved_success' => ['label' => 'Dispute Won', 'is_final' => true, 'status_color' => 'emerald'],
                'resolved_failed' => ['label' => 'Dispute Lost', 'is_final' => true, 'status_color' => 'slate']
            ]
        ];

        // ==========================================
        // 3. GENERIC WORKFLOW (Fallback for Govt, Telecom, etc.)
        // ==========================================
        $genericWorkflow = [
            'initial_step' => 'open',
            'steps' => [
                'open' => [
                    'label' => 'Case Open', 
                    'status_color' => 'gray',
                    'actions' => [['key' => 'send', 'label' => 'Send Message', 'to_step' => 'waiting']]
                ],
                'waiting' => [
                    'label' => 'Waiting for Reply',
                    'status_color' => 'amber',
                    'timeouts' => [['days' => 7, 'action' => 'follow_up', 'message' => 'Time to follow up?']],
                    'actions' => [['key' => 'reply', 'label' => 'Reply Received', 'to_step' => 'review']]
                ],
                'review' => [
                    'label' => 'In Discussion',
                    'status_color' => 'blue',
                    'actions' => [
                        ['key' => 'resolve', 'label' => 'Resolve', 'to_step' => 'closed'],
                        ['key' => 'reply', 'label' => 'Reply Again', 'to_step' => 'waiting']
                    ]
                ],
                'closed' => ['label' => 'Closed', 'is_final' => true, 'status_color' => 'slate']
            ]
        ];

        // Apply Configs to Existing Categories
        InstitutionCategory::where('slug', 'airline')->update(['workflow_config' => $airlineWorkflow]);
        
        InstitutionCategory::whereIn('slug', ['bank', 'fintech'])->update(['workflow_config' => $financeWorkflow]);
        
        InstitutionCategory::whereIn('slug', ['govt', 'insurance', 'telecom'])->update(['workflow_config' => $genericWorkflow]);
    }
}