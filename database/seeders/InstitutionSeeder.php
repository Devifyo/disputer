<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Institution;
use App\Models\InstitutionCategory;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $cats = [
            'Bank'      => InstitutionCategory::firstOrCreate(['slug' => 'bank'], ['name' => 'Bank']),
            'Airline'   => InstitutionCategory::firstOrCreate(['slug' => 'airline'], ['name' => 'Airline']),
            'Govt'      => InstitutionCategory::firstOrCreate(['slug' => 'govt'], ['name' => 'Government Agency']),
            'Insurance' => InstitutionCategory::firstOrCreate(['slug' => 'insurance'], ['name' => 'Insurance']),
            'Telecom'   => InstitutionCategory::firstOrCreate(['slug' => 'telecom'], ['name' => 'Telecom & ISP']),
            'Fintech'   => InstitutionCategory::firstOrCreate(['slug' => 'fintech'], ['name' => 'Fintech & Payments']),
        ];

        $institutions = [

            // --- BANKS ---
            [
                'name' => 'Chase Bank (JPMorgan Chase)',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'executive.office@chase.com',
                'escalation_email' => 'escalations@chase.com',
                'escalation_contact_name' => 'Senior Resolution Officer',
                'is_verified' => true,
            ],
            [
                'name' => 'Bank of America',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'claims@bankofamerica.com',
                'escalation_email' => 'executive.support@bankofamerica.com',
                'escalation_contact_name' => 'Executive Escalation Manager',
                'is_verified' => true,
            ],
            [
                'name' => 'Wells Fargo',
                'institution_category_id' => $cats['Bank']->id,
                'contact_email' => 'fraudclaims@wellsfargo.com',
                'escalation_email' => 'executive.office@wellsfargo.com',
                'escalation_contact_name' => 'Customer Advocacy Lead',
                'is_verified' => true,
            ],

            // --- FINTECH ---
            [
                'name' => 'PayPal',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'disputes@paypal.com',
                'escalation_email' => 'executiveoffice@paypal.com',
                'escalation_contact_name' => 'Risk Escalation Officer',
                'is_verified' => true,
            ],
            [
                'name' => 'Stripe',
                'institution_category_id' => $cats['Fintech']->id,
                'contact_email' => 'support@stripe.com',
                'escalation_email' => 'executive@stripe.com',
                'escalation_contact_name' => 'Head of Merchant Risk',
                'is_verified' => true,
            ],

            // --- AIRLINES ---
            [
                'name' => 'United Airlines',
                'institution_category_id' => $cats['Airline']->id,
                'contact_email' => 'customer.care@united.com',
                'escalation_email' => 'executive.office@united.com',
                'escalation_contact_name' => 'Customer Relations Director',
                'is_verified' => true,
            ],

            // --- TELECOM ---
            [
                'name' => 'AT&T',
                'institution_category_id' => $cats['Telecom']->id,
                'contact_email' => 'customer.care@att.com',
                'escalation_email' => 'executive.support@att.com',
                'escalation_contact_name' => 'Escalation Case Manager',
                'is_verified' => true,
            ],

            // --- GOVERNMENT ---
            [
                'name' => 'Internal Revenue Service (IRS)',
                'institution_category_id' => $cats['Govt']->id,
                'contact_email' => 'taxpayer.advocate@irs.gov',
                'escalation_email' => 'executive.office@irs.gov',
                'escalation_contact_name' => 'Taxpayer Advocate Officer',
                'is_verified' => true,
            ],

            // --- INSURANCE ---
            [
                'name' => 'Geico',
                'institution_category_id' => $cats['Insurance']->id,
                'contact_email' => 'claims@geico.com',
                'escalation_email' => 'executive.claims@geico.com',
                'escalation_contact_name' => 'Senior Claims Escalation Lead',
                'is_verified' => true,
            ],
        ];

        foreach ($institutions as $inst) {
            Institution::updateOrCreate(
                ['name' => $inst['name']],
                $inst
            );
        }
    }
}