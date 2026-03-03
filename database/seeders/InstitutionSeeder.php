<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Institution;
use App\Models\{InstitutionCategory, User};

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
        $adminRole = config('roles.admin.name');
        $admin = User::role($adminRole)->first();
        // 2. Create Common Institutions
        $institutions = [
            // --- BANKS ---
            [
                'name' => 'Chase Bank (JPMorgan Chase)',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'executive.office@chase.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Bank of America',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'claims@bankofamerica.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,

            ],
            [
                'name' => 'Wells Fargo',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'fraudclaims@wellsfargo.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Citibank',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'disputes@citi.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Capital One',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'disputes@capitalone.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,

            ],
            [
                'name' => 'US Bank',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'fraud_support@usbank.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],

            // --- FINTECH (High Dispute Volume) ---
            [
                'name' => 'PayPal',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'disputes@paypal.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Stripe',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'support@stripe.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Coinbase',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'support@coinbase.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Square / Block',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'support@squareup.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],

            // --- AIRLINES ---
            [
                'name' => 'United Airlines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'customer.care@united.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Delta Air Lines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'ticket_refunds@delta.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'American Airlines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'customer.relations@aa.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Southwest Airlines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'refunds@wnco.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'JetBlue Airways',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'dearjetblue@jetblue.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],

            // --- TELECOM (Common Billing Disputes) ---
            [
                'name' => 'AT&T',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'customer.care@att.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Verizon',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'executive.relations@verizon.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'T-Mobile',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'executive.response@t-mobile.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Comcast / Xfinity',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'we_can_help@cable.comcast.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],

            // --- GOVERNMENT AGENCIES ---
            [
                'name' => 'Internal Revenue Service (IRS)',
                'institution_category_id' => $cats['Govt']->id,
                'contact_email' => 'taxpayer.advocate@irs.gov',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Social Security Administration (SSA)',
                'institution_category_id' => $cats['Govt']->id,
                'contact_email' => 'support@ssa.gov',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'USCIS (Immigration)',
                'institution_category_id' => $cats['Govt']->id,
                'contact_email' => 'cis.ombudsman@hq.dhs.gov',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],

            // --- INSURANCE ---
            [
                'name' => 'Geico',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'claims@geico.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'State Farm',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'claims.support@statefarm.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Progressive',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'upload@progressive.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
            ],
            [
                'name' => 'Blue Cross Blue Shield (BCBS)',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'appeals@bcbs.com',
                'is_verified' => true,
                'is_internal' => true,
                'created_by' => $admin ? $admin->id : null,
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
