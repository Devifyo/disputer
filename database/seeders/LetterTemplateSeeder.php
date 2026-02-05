<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LetterTemplate;
use App\Models\InstitutionCategory;
use Illuminate\Support\Str;

class LetterTemplateSeeder extends Seeder
{
    public function run()
    {
        // ---------------------------------------------------------
        // 1. SETUP CATEGORIES (Ensure they exist & get IDs)
        // ---------------------------------------------------------
        
        $catIds = [];
        $categories = [
            ['name' => 'Bank', 'slug' => 'bank'],
            ['name' => 'Airline', 'slug' => 'airline'],
            ['name' => 'Government Agency', 'slug' => 'govt'],
            ['name' => 'Insurance', 'slug' => 'insurance'],
            ['name' => 'Fintech & Payments', 'slug' => 'fintech'],
            ['name' => 'Telecom & ISP', 'slug' => 'telecom'],
        ];

        foreach ($categories as $cat) {
            $record = InstitutionCategory::firstOrCreate(
                ['slug' => $cat['slug']], 
                ['name' => $cat['name'], 'is_verified' => true]
            );
            $catIds[$cat['slug']] = $record->id;
        }

        // ---------------------------------------------------------
        // 2. DEFINE PROFESSIONAL TEMPLATES
        // ---------------------------------------------------------

        $templates = [
            // --- BANKING ---
            [
                'institution_category_id' => $catIds['bank'],
                'title' => 'Unauthorized Transaction Dispute',
                'description' => 'Formal notice to dispute a charge you did not authorize (Fraud/Theft).',
                'content' => "Date: [CURRENT_DATE]\n\nTo the Fraud Department,\n\nI am writing to formally dispute a transaction on my account #[ACCOUNT_NUMBER]. I did not authorize the charge listed below, nor did anyone authorized to use my account.\n\nTransaction Details:\n- Merchant: [MERCHANT_NAME]\n- Date: [TRANSACTION_DATE]\n- Amount: [AMOUNT]\n\nI have not received any goods or services related to this charge. I request that you investigate this matter immediately, credit my account for the disputed amount, and waive any interest or fees associated with it.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'credit-card',
                'color' => 'blue'
            ],
            [
                'institution_category_id' => $catIds['bank'],
                'title' => 'Goodwill Late Fee Removal',
                'description' => 'Polite request to remove a late fee based on good payment history.',
                'content' => "Date: [CURRENT_DATE]\n\nTo Customer Service,\n\nI have been a loyal customer of [BANK_NAME] for several years and have always maintained a perfect payment history. Unfortunately, due to an oversight, my recent payment was processed late.\n\nBecause this is an isolated incident and not indicative of my usual financial habits, I respectfully request a 'Goodwill Adjustment' to remove the late fee of [FEE_AMOUNT] charged to my account.\n\nThank you for your understanding and continued service.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'heart-handshake',
                'color' => 'emerald'
            ],

            // --- AIRLINE ---
            [
                'institution_category_id' => $catIds['airline'],
                'title' => 'Flight Cancellation Refund Request',
                'description' => 'Demand a full cash refund (not a voucher) for a flight cancelled by the airline.',
                'content' => "Date: [CURRENT_DATE]\n\nRe: Booking Reference [BOOKING_REF]\n\nTo [AIRLINE_NAME] Refunds,\n\nMy flight #[FLIGHT_NUMBER] scheduled for [FLIGHT_DATE] was cancelled by your airline. Under the Department of Transportation (DOT) regulations, I am entitled to a full refund to my original form of payment when the carrier cancels a flight, regardless of the reason.\n\nI am rejecting the offer of a travel voucher/credit. Please process a refund of [TOTAL_PRICE] to my original credit card immediately.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'plane',
                'color' => 'sky'
            ],
            [
                'institution_category_id' => $catIds['airline'],
                'title' => 'Luggage Damage Claim',
                'description' => 'File a claim for checked luggage damaged during transit.',
                'content' => "Date: [CURRENT_DATE]\n\nRe: Baggage Claim Ticket [TAG_NUMBER]\n\nTo Baggage Claims Department,\n\nUpon retrieving my checked luggage from Flight #[FLIGHT_NUMBER] on [DATE], I noticed significant damage that occurred while the bag was in your custody. I immediately filed a report at the airport (Report #[REPORT_ID]).\n\nThe damage renders the suitcase unusable. I am requesting compensation of [COST] to replace the luggage. Attached are photos of the damage and the purchase receipt for the original bag.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'briefcase',
                'color' => 'slate'
            ],

            // --- INSURANCE ---
            [
                'institution_category_id' => $catIds['insurance'],
                'title' => 'Claim Denial Appeal',
                'description' => 'Formally appeal an insurance claim that was wrongly rejected.',
                'content' => "Date: [CURRENT_DATE]\n\nClaim Number: [CLAIM_NUMBER]\nPolicy Number: [POLICY_NUMBER]\n\nTo the Appeals Department,\n\nI am writing to formally appeal the denial of my claim regarding [SERVICE_OR_INCIDENT] on [DATE]. Your denial letter states the reason as [REASON_FROM_LETTER].\n\nHowever, under the terms of my policy (Section [SECTION_NUMBER]), this service is covered when deemed medically necessary/accident-related. Attached is additional documentation from [DOCTOR/MECHANIC] supporting this fact.\n\nPlease review this new evidence and reverse your decision.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'shield-alert',
                'color' => 'red'
            ],

            // --- GOVERNMENT ---
            [
                'institution_category_id' => $catIds['govt'],
                'title' => 'Identity Theft Affidavit',
                'description' => 'Alert the government/bureau that your ID was stolen to freeze records.',
                'content' => "Date: [CURRENT_DATE]\n\nTo Whom It May Concern,\n\nI am a victim of identity theft. I am writing to request that you place a fraud alert and security freeze on my file immediately.\n\nI did not authorize any new accounts opened in my name after [DATE_THEFT_BEGAN]. Attached is a copy of my police report and a copy of my government-issued ID for verification purposes.\n\nPlease confirm in writing once the freeze is active.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'landmark', 
                'color' => 'slate'
            ],

            // --- FINTECH ---
            [
                'institution_category_id' => $catIds['fintech'],
                'title' => 'P2P Transfer Error Dispute',
                'description' => 'Dispute a transfer sent to the wrong person or a scammer via apps like Venmo/CashApp.',
                'content' => "Date: [CURRENT_DATE]\n\nTo [APP_NAME] Support,\n\nI am disputing a transaction ID #[TRANS_ID] for [AMOUNT] made on [DATE].\n\nReason: [Scam / Wrong Person / Technical Error].\n\nI have attempted to contact the recipient to request a refund but have received no response. As this transaction was [unauthorized/fraudulent], I request that you reverse the transaction and refund the funds to my balance.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'smartphone',
                'color' => 'violet'
            ],

            // --- TELECOM ---
            [
                'institution_category_id' => $catIds['telecom'],
                'title' => 'Service Outage Credit Request',
                'description' => 'Request a bill credit for days when your internet or phone service was down.',
                'content' => "Date: [CURRENT_DATE]\n\nAccount Number: [ACC_NUMBER]\n\nTo Customer Billing,\n\nMy internet service was completely unavailable from [START_DATE] to [END_DATE]. This outage was confirmed by your technical support team (Ticket #[TICKET_ID]).\n\nSince I pay for a full month of service, I am requesting a prorated credit for the [NUMBER] days I was without service. Please apply this credit to my next statement.\n\nSincerely,\n[YOUR_NAME]",
                'icon' => 'wifi',
                'color' => 'orange'
            ]
        ];

        // ---------------------------------------------------------
        // 3. INSERT OR UPDATE (Prevents Duplicates)
        // ---------------------------------------------------------
        foreach ($templates as $t) {
            // Generate a slug from the title
            $slug = Str::slug($t['title']);

            LetterTemplate::updateOrCreate(
                ['slug' => $slug], // Check if this slug exists
                array_merge($t, ['slug' => $slug]) // If found, update these fields. If not, create new.
            );
        }
    }
}