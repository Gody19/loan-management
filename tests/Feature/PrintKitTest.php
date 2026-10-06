<?php

namespace Tests\Feature;

use App\Models\AiIntelligenceReport;
use App\Models\Loan;
use App\Models\SavingsTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Every page that supports printing must use the shared print kit
 * (layouts.print.*): the FinancePro mark taken from the login brand, the
 * branded header, the watermark and the footer line.
 */
class PrintKitTest extends TestCase
{
    private function actor(): User
    {
        $user = User::where('email', 'admin@financepro.co.tz')->first();

        if (! $user) {
            $this->markTestSkipped('Seeded database required (mysql/finance).');
        }

        return $user;
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function firstOrSkip(string $model): Model
    {
        try {
            $record = $model::query()->first();
        } catch (\Throwable) {
            $record = null;
        }

        if (! $record) {
            $this->markTestSkipped('Seeded database required (mysql/finance).');
        }

        return $record;
    }

    private function assertKit(string $html): void
    {
        $this->assertStringContainsString('images/financepro-mark.svg', $html);
        $this->assertStringContainsString('print-watermark', $html);
        $this->assertStringContainsString('print-brand', $html);
        $this->assertStringContainsString('print-brand-text', $html);
        $this->assertStringContainsString('VICOBA System', $html);
        $this->assertStringContainsString('print-doc-footer', $html);
    }

    public function test_loan_statement_uses_shared_print_kit(): void
    {
        $loan = $this->firstOrSkip(Loan::class);

        $response = $this->actingAs($this->actor())->get('/loans/'.$loan->id.'/statement');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertKit($html);
        $this->assertStringContainsString('print-doc-header', $html);
        $this->assertStringContainsString('.print-document', $html);
        $this->assertStringContainsString('vicoba-navbar', $html, 'The app chrome must be hidden when printing.');
        $this->assertStringNotContainsString('financepro-logo.svg', $html);
    }

    public function test_ai_report_print_uses_shared_print_kit(): void
    {
        try {
            $report = AiIntelligenceReport::query()->get()
                ->first(fn (AiIntelligenceReport $r) => $r->status->isReportable());
        } catch (\Throwable) {
            $report = null;
        }

        if (! $report) {
            $this->markTestSkipped('Seeded database required (mysql/finance).');
        }

        $response = $this->actingAs($this->actor())->get('/ai/reports/'.$report->id.'/print');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertKit($html);
        $this->assertStringContainsString('print-doc-header', $html);
        // standalone document pages must never hide their own content
        $this->assertStringNotContainsString('.print-document', $html);
    }

    public function test_savings_receipt_uses_shared_print_kit(): void
    {
        $transaction = $this->firstOrSkip(SavingsTransaction::class);

        $response = $this->actingAs($this->actor())->get('/savings-transactions/'.$transaction->id.'/receipt');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertKit($html);
        $this->assertStringContainsString('receipt-brand', $html);
        $this->assertStringNotContainsString('.print-document', $html);
    }
}
