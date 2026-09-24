<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanRepaymentStatus;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\RepaymentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class RepaymentNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Loan $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loan = Loan::factory()->create();
    }

    private function createRepayment(string $number, float $amount = 1000.00): LoanRepayment
    {
        return LoanRepayment::create([
            'loan_id' => $this->loan->id,
            'organization_id' => $this->loan->organization_id,
            'branch_id' => $this->loan->branch_id,
            'member_id' => $this->loan->member_id,
            'repayment_number' => $number,
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => LoanRepaymentStatus::Posted,
        ]);
    }

    public function test_persisted_numbers_are_unique_and_sequential(): void
    {
        $generator = app(RepaymentNumberGenerator::class);

        $numbers = [];
        for ($i = 0; $i < 50; $i++) {
            $number = $generator->generate();
            $this->createRepayment($number);
            $numbers[] = $number;
        }

        $this->assertCount(50, array_unique($numbers));

        $sequences = array_map(fn ($number) => (int) substr($number, -6), $numbers);
        $this->assertSame(range(1, 50), $sequences);
    }

    public function test_generator_resumes_after_persisted_repayment(): void
    {
        $generator = app(RepaymentNumberGenerator::class);

        $this->createRepayment('RPT-'.date('Y').'-000007');

        $next = $generator->generate();

        $this->assertStringEndsWith('000008', $next);
    }

    public function test_format_is_rpt_year_six_digits(): void
    {
        $generator = app(RepaymentNumberGenerator::class);

        $number = $generator->generate();

        $this->assertMatchesRegularExpression('/^RPT-\d{4}-\d{6}$/', $number);
    }

    public function test_duplicate_repayment_number_rejected_by_database(): void
    {
        $generator = app(RepaymentNumberGenerator::class);

        $number = $generator->generate();
        $this->createRepayment($number);

        $this->expectException(QueryException::class);

        $this->createRepayment($number);
    }
}