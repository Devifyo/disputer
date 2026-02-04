    <?php

return [
    'standard' => [
        'name' => 'Standard Dispute Process',
        'steps' => [
            [
                'step' => 1,
                'name' => 'Formal Dispute Notice',
                'wait_days' => 14,
                'action' => 'email_institution'
            ],
            [
                'step' => 2,
                'name' => 'Escalation / Follow-up',
                'wait_days' => 30,
                'action' => 'email_reminder'
            ],
            [
                'step' => 3,
                'name' => 'Regulatory Filing Preparation',
                'wait_days' => 0,
                'action' => 'generate_pdf'
            ]
        ]
    ]
];