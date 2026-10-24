<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $template = Template::firstOrCreate(
            ['name' => 'Standard Life Insurance Sales Call'],
            [
                'description'    => 'Evaluates agent performance on compliance, needs discovery, product explanation, and professionalism for inbound/outbound life insurance calls.',
                'pass_threshold' => 80,
                'created_by'     => $admin->id,
            ]
        );

        if ($template->criteria()->count() > 0) {
            return; // already seeded
        }

        // Reproduced verbatim from docs/example_criteria.md
        // Types: pass_fail | scale_1_5 | yes_no_na | extract
        // Critical criteria: #2, #8, #9 (recording disclosure, no guaranteed approval, no pressure)
        $criteria = [
            [
                'name'            => 'Opening – Agent Identification',
                'description'     => 'Agent clearly states their full name and the company name within the first 30 seconds.',
                'fail_conditions' => 'Agent does not state name OR does not state company name.',
                'type'            => 'pass_fail',
                'weight'          => 2,
                'is_critical'     => false,
                'phase_hint'      => 'opening',
            ],
            [
                'name'            => 'Opening – Call Recording Disclosure',
                'description'     => 'Agent informs the customer that the call may be recorded before collecting any personal data or discussing any product.',
                'fail_conditions' => 'No recording disclosure is given, or it is given after personal data has been collected.',
                'type'            => 'yes_no_na',
                'weight'          => 3,
                'is_critical'     => true,
                'phase_hint'      => 'opening',
            ],
            [
                'name'            => 'Opening – Purpose of Call',
                'description'     => 'Agent clearly explains the reason for the call (e.g. follow-up on enquiry, renewal discussion, new quote).',
                'fail_conditions' => 'Customer is not told why the agent is calling before product discussion begins.',
                'type'            => 'pass_fail',
                'weight'          => 2,
                'is_critical'     => false,
                'phase_hint'      => 'opening',
            ],
            [
                'name'            => 'Needs Discovery – Age and Health Status',
                'description'     => 'Agent asks for the customer\'s age and any significant health conditions relevant to life insurance underwriting before recommending a product.',
                'fail_conditions' => 'No age asked, or product recommended before basic health/age information is collected.',
                'type'            => 'pass_fail',
                'weight'          => 3,
                'is_critical'     => false,
                'phase_hint'      => 'discovery',
            ],
            [
                'name'            => 'Needs Discovery – Existing Cover and Dependants',
                'description'     => 'Agent asks whether the customer has any existing life cover and how many financial dependants they have.',
                'fail_conditions' => 'Neither existing cover nor dependants are discussed.',
                'type'            => 'pass_fail',
                'weight'          => 2,
                'is_critical'     => false,
                'phase_hint'      => 'discovery',
            ],
            [
                'name'            => 'Needs Discovery – Budget',
                'description'     => 'Agent asks or explores what monthly premium the customer is comfortable paying before quoting a figure.',
                'fail_conditions' => 'Premium is quoted cold with no budget discussion.',
                'type'            => 'pass_fail',
                'weight'          => 2,
                'is_critical'     => false,
                'phase_hint'      => 'discovery',
            ],
            [
                'name'            => 'Product Explanation – Coverage Amount and Term',
                'description'     => 'Agent clearly states the coverage amount (sum assured) and the policy term (or that it is whole-of-life) being quoted.',
                'fail_conditions' => 'Coverage amount or term is not stated during the pitch.',
                'type'            => 'pass_fail',
                'weight'          => 3,
                'is_critical'     => false,
                'phase_hint'      => 'pitch',
            ],
            [
                'name'            => 'Compliance – No Guaranteed-Approval Claims',
                'description'     => 'Agent never claims or implies that the application is guaranteed to be approved or that cover is guaranteed regardless of health history.',
                'fail_conditions' => 'Agent uses phrases such as "guaranteed approval", "you\'re definitely covered", "no medical questions", or similar.',
                'type'            => 'yes_no_na',
                'weight'          => 4,
                'is_critical'     => true,
                'phase_hint'      => 'anywhere',
            ],
            [
                'name'            => 'Compliance – No Pressure Tactics',
                'description'     => 'Agent does not use high-pressure, urgency-manufacturing, or fear-based language to push the customer into a decision.',
                'fail_conditions' => 'Agent uses artificial urgency, excessive fear language, or refuses to give the customer time to think.',
                'type'            => 'yes_no_na',
                'weight'          => 4,
                'is_critical'     => true,
                'phase_hint'      => 'anywhere',
            ],
            [
                'name'            => 'Compliance – Cancellation / Cooling-Off Period',
                'description'     => 'Agent explains that the customer can cancel within the cooling-off period and get a full refund (30 days for UK life insurance, which is a pure protection contract under FCA ICOBS 7.1; called the free-look period in the US, usually 10 to 30 days depending on the state), before any payment is taken or application is submitted.',
                'fail_conditions' => 'Cooling-off period is not mentioned before close or payment step.',
                'type'            => 'pass_fail',
                'weight'          => 3,
                'is_critical'     => false,
                'phase_hint'      => 'close',
            ],
            [
                'name'            => 'Close – Clear Next Steps',
                'description'     => 'Agent summarises what happens next (e.g. application sent by email, direct debit set up, underwriting process) before ending the call.',
                'fail_conditions' => 'Call ends without any explanation of what the customer should expect next.',
                'type'            => 'pass_fail',
                'weight'          => 2,
                'is_critical'     => false,
                'phase_hint'      => 'close',
            ],
            [
                'name'            => 'Professionalism and Tone',
                'description'     => 'Agent is polite, patient, and clear throughout. Speaks at an appropriate pace, does not talk over the customer, and handles objections calmly. 5=exemplary; 4=good; 3=acceptable; 2=noticeable issues; 1=poor.',
                'fail_conditions' => null,
                'type'            => 'scale_1_5',
                'weight'          => 2,
                'is_critical'     => false,
                'phase_hint'      => 'anywhere',
            ],
            [
                'name'            => 'Extract – Premium Quoted',
                'description'     => 'Record the exact monthly premium figure quoted to the customer. Informational only — does not affect the call score.',
                'fail_conditions' => null,
                'type'            => 'extract',
                'weight'          => 0,
                'is_critical'     => false,
                'phase_hint'      => 'pitch',
            ],
        ];

        foreach ($criteria as $position => $data) {
            $template->criteria()->create(array_merge($data, ['position' => $position]));
        }
    }
}
