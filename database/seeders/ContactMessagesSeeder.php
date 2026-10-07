<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;

class ContactMessagesSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $messages = [
            ['Grace Mwakalinga', 'grace.mwakalinga@example.test', '+255754112201', 'Request for membership information', 'I would like to know the membership requirements and the monthly savings contributions for joining the group.'],
            ['Peter Ndosi', 'peter.ndosi@example.test', '+255733450912', 'Loan repayment schedule request', 'Could you send me a copy of my loan repayment schedule for the business expansion loan?'],
            ['Fatuma Ally', 'fatuma.ally@example.test', '+255712334556', 'Welfare benefit claim', 'I submitted a welfare benefit request last month for medical treatment. Could you confirm its status?'],
            ['Joseph Kimaro', 'joseph.kimaro@example.test', '+255765443321', 'Change of phone number', 'I have changed my phone number and would like it updated on my member profile.'],
            ['Rehema Mushi', 'rehema.mushi@example.test', null, 'Share account statement', 'Please share my share account statement showing the total shares I own this year.'],
            ['Daniel Kileo', 'daniel.kileo@example.test', '+255789112233', 'Interest rate enquiry', 'Are the interest rates on the small business loan fixed for the whole term?'],
            ['Neema Sanga', 'neema.sanga@example.test', '+255746778899', 'Group meeting reschedule', 'Is it possible to move our group meeting from Wednesday to Friday afternoons?'],
            ['Salum Said', 'salum.said@example.test', '+255721234567', 'Certificate collection', 'I finished my certificate in economics. When can I collect it from the office?'],
            ['Mwajuma Shabani', 'mwajuma.shabani@example.test', '+255759876543', 'Loan application status', 'I applied for a loan three weeks ago and I have not received feedback. Please advise.'],
            ['Asha Mbuguu', 'asha.mbuguu@example.test', '+255734567891', 'Withdrawal limit enquiry', 'What is the monthly limit for withdrawing from my savings account without prior notice?'],
            ['Kelvin Masae', 'kelvin.masae@example.test', '+255798765432', 'Registration of business', 'I want to register my small business so I can apply for a business loan. What documents are needed?'],
            ['Zainab Ally', 'zainab.ally@example.test', '+255715566778', 'Passbook lost', 'I lost my savings passbook and I would like to request for a replacement.'],
            ['Omary Rajab', 'omary.rajab@example.test', '+255743322110', 'Thank you note', 'Just wanted to thank the team for the smooth loan process. Everything was explained clearly.'],
            ['Halima Nuru', 'halima.nuru@example.test', '+255767788990', 'Savings account opening', 'I would like to open a savings account but I am not sure which product suits me.'],
            ['Baraka Sanga', 'baraka.sanga@example.test', '+255781122334', 'Guarantor requirements', 'How many guarantors do I need for a loan of five million shillings?'],
            ['Marianne Rutaro', 'marianne.rutaro@example.test', '+255755443322', 'Statement of account request', 'Please send me my statement of account for the last six months for my records.'],
            ['Yusuf Hamisi', 'yusuf.hamisi@example.test', '+255733221100', 'Online banking access', 'Is it possible to check my balance online instead of visiting the office?'],
            ['Monica Sery', 'monica.sery@example.test', '+255749988776', 'Group savings balance', 'Our group seems to be below the monthly savings target. Can we get the group statement?'],
            ['Emmanuel Mlelwa', 'emmanuel.mlelwa@example.test', '+255726655443', 'Loan restructuring request', 'My business has been slow this quarter. Can we discuss restructuring my loan instalments?'],
            ['Rose Muthoni', 'rose.muthoni@example.test', '+255711223344', 'Membership card', 'My membership card is damaged and I would like a replacement card issued.'],
            ['Ibrahim Tsuma', 'ibrahim.tsuma@example.test', '+255738899001', 'Collateral valuation', 'What is the process for valuing land as collateral for a larger loan?'],
            ['Lucy Ndaki', 'lucy.ndaki@example.test', '+255775544332', 'Referral to a member', 'My cousin would like to join the group. Who should she speak to about registration?'],
            ['Simba Kito', 'simba.kito@example.test', '+255702233445', 'Audit and compliance', 'Do you publish annual audit statements for members? I would like to review them.'],
            ['Leila Hamad', 'leila.hamad@example.test', '+255761122998', 'Welfare fund contribution increase', 'Can I increase my monthly welfare contribution from fifty thousand to one hundred thousand?'],
            ['Jackson Mwita', 'jackson.mwita@example.test', '+255729988776', 'Loan balance confirmation', 'I need written confirmation of my outstanding loan balance for a personal loan application.'],
        ];

        foreach ($messages as $index => $message) {
            if ($this->gap('contact_messages', 25) <= 0) {
                break;
            }

            $createdAt = $this->daysAgo(120, 1);

            ContactMessage::create([
                'name' => $message[0],
                'email' => $message[1],
                'phone' => $message[2],
                'subject' => $message[3],
                'message' => $message[4],
                'is_read' => $index % 3 !== 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->bump('contact_messages');
        }

        $this->report('Contact messages ready.');
    }
}
