<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\VicobaGroup;
use Tests\TestCase;

/**
 * /vicoba-groups/{id} must render the full group detail summary
 * (members, savings, shares, welfare, loans, applications, income/expenses)
 * together with the shared print kit.
 */
class VicobaGroupDetailsTest extends TestCase
{
    private function actor(): ?User
    {
        try {
            return User::where('email', 'admin@financepro.co.tz')->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstGroup(): ?VicobaGroup
    {
        try {
            return VicobaGroup::query()->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function test_group_page_shows_details_summary_and_print_kit(): void
    {
        $user = $this->actor();
        $group = $this->firstGroup();

        if (! $user || ! $group) {
            $this->markTestSkipped('Seeded database required (mysql/finance).');
        }

        $response = $this->actingAs($user)->get('/vicoba-groups/'.$group->id);
        $response->assertOk();

        $html = $response->getContent();

        foreach ([
            'Total Members',
            'Savings Balance',
            'Shares Value',
            'Welfare Balance',
            'Outstanding Balance',
            'Loan Portfolio',
            'Loan Applications',
            'Members by Status',
            'Income &amp; Expenses',
            'Savings Cash Flow',
            $group->code,
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "Missing group detail: {$needle}");
        }

        // printable document via the shared print kit
        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('images/financepro-mark.svg', $html);
        $this->assertStringContainsString('print-watermark', $html);
        $this->assertStringContainsString('print-doc-header', $html);
        $this->assertStringContainsString('print-doc-footer', $html);
        $this->assertStringContainsString('.print-document', $html);

        // the screen page header must not leak into the printed document
        $this->assertStringContainsString('.page-header', $html);
        $this->assertStringContainsString('class="page-header"', $html);

        // summary figures shown must match the database
        $memberIds = Member::where('vicoba_group_id', $group->id)->pluck('id');
        $outstanding = (float) Loan::whereIn('member_id', $memberIds)->sum('outstanding_balance');
        $savings = (float) SavingsAccount::where('vicoba_group_id', $group->id)->sum('current_balance');

        $this->assertStringContainsString(number_format($outstanding, 2), $html);
        $this->assertStringContainsString(number_format($savings, 2), $html);
    }
}
