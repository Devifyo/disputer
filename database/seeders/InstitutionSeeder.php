<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Institution;
use App\Models\InstitutionCategory;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Categories (Using firstOrCreate to prevent duplicates)
        $cats = [
            'Bank'      => InstitutionCategory::firstOrCreate(['slug' => 'bank'], ['name' => 'Bank']),
            'Airline'   => InstitutionCategory::firstOrCreate(['slug' => 'airline'], ['name' => 'Airline']),
            'Govt'      => InstitutionCategory::firstOrCreate(['slug' => 'govt'], ['name' => 'Government Agency']),
            'Insurance' => InstitutionCategory::firstOrCreate(['slug' => 'insurance'], ['name' => 'Insurance']),
            'Telecom'   => InstitutionCategory::firstOrCreate(['slug' => 'telecom'], ['name' => 'Telecom & ISP']),
            'Fintech'   => InstitutionCategory::firstOrCreate(['slug' => 'fintech'], ['name' => 'Fintech & Payments']),
        ];

        // 2. Create Common Institutions
        $institutions = [
            // --- BANKS ---
            [
                'name' => 'Chase Bank (JPMorgan Chase)',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'executive.office@chase.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Bank of America',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'claims@bankofamerica.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Wells Fargo',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'fraudclaims@wellsfargo.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Citibank',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'disputes@citi.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Capital One',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'disputes@capitalone.com',
                'is_verified' => true,
            ],
            [
                'name' => 'US Bank',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'fraud_support@usbank.com',
                'is_verified' => true,
            ],

            // --- FINTECH (High Dispute Volume) ---
            [
                'name' => 'PayPal',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'disputes@paypal.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Stripe',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'support@stripe.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Coinbase',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'support@coinbase.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Square / Block',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'support@squareup.com',
                'is_verified' => true,
            ],

            // --- AIRLINES ---
            [
                'name' => 'United Airlines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'customer.care@united.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Delta Air Lines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'ticket_refunds@delta.com',
                'is_verified' => true,
            ],
            [
                'name' => 'American Airlines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'customer.relations@aa.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Southwest Airlines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'refunds@wnco.com',
                'is_verified' => true,
            ],
            [
                'name' => 'JetBlue Airways',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'dearjetblue@jetblue.com',
                'is_verified' => true,
            ],

            // --- TELECOM (Common Billing Disputes) ---
            [
                'name' => 'AT&T',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'customer.care@att.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Verizon',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'executive.relations@verizon.com',
                'is_verified' => true,
            ],
            [
                'name' => 'T-Mobile',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'executive.response@t-mobile.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Comcast / Xfinity',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'we_can_help@cable.comcast.com',
                'is_verified' => true,
            ],

            // --- GOVERNMENT AGENCIES ---
            [
                'name' => 'Internal Revenue Service (IRS)',
                'institution_category_id' => $cats['Govt']->id,
                'contact_email' => 'taxpayer.advocate@irs.gov',
                'is_verified' => true,
            ],
            [
                'name' => 'Social Security Administration (SSA)',
                'institution_category_id' => $cats['Govt']->id,
                'contact_email' => 'support@ssa.gov',
                'is_verified' => true,
            ],
            [
                'name' => 'USCIS (Immigration)',
                'institution_category_id' => $cats['Govt']->id,
                'contact_email' => 'cis.ombudsman@hq.dhs.gov',
                'is_verified' => true,
            ],

            // --- INSURANCE ---
            [
                'name' => 'Geico',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'claims@geico.com',
                'is_verified' => true,
            ],
            [
                'name' => 'State Farm',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'claims.support@statefarm.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Progressive',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'upload@progressive.com',
                'is_verified' => true,
            ],
            [
                'name' => 'Blue Cross Blue Shield (BCBS)',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'appeals@bcbs.com',
                'is_verified' => true,
            ],
        ];

        foreach ($institutions as $inst) {
            // updateOrCreate avoids duplicates if you run the seeder twice
            Institution::updateOrCreate(
                ['name' => $inst['name']],
                $inst
            );
        }
    }
}
