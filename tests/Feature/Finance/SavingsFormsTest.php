<?php

namespace Tests\Feature\Finance;

use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\User;
use Tests\TestCase;

/**
 * The savings deposit/withdraw forms must render and post:
 * correct route names (do-deposit / do-withdraw) and the
 * payment_method_id field expected by the form requests.
 */
class SavingsFormsTest extends TestCase
{
    public function test_deposit_and_withdraw_forms_render_and_post(): void
    {
        try {
            $user = User::where('email', 'admin@financepro.co.tz')->first();
            $account = SavingsAccount::find(50) ?? SavingsAccount::query()->first();
            $paymentMethods = PaymentMethod::where('status', 'active')->count();
        } catch (\Throwable) {
            $this->markTestSkipped('Seeded database required (mysql/finance).');
        }

        if (! $user || ! $account || $paymentMethods === 0) {
            $this->markTestSkipped('Seeded database required (mysql/finance).');
        }

        $deposit = $this->actingAs($user)->get('/savings-accounts/'.$account->id.'/deposit');
        $deposit->assertOk();
        $html = $deposit->getContent();
        $this->assertStringContainsString('action="'.route('savings-accounts.do-deposit', $account).'"', $html);
        $this->assertStringContainsString('name="payment_method_id"', $html);
        $this->assertStringContainsString(number_format($account->current_balance, 2), $html);

        $withdraw = $this->actingAs($user)->get('/savings-accounts/'.$account->id.'/withdraw');
        $withdraw->assertOk();
        $html = $withdraw->getContent();
        $this->assertStringContainsString('action="'.route('savings-accounts.do-withdraw', $account).'"', $html);
        $this->assertStringContainsString('name="payment_method_id"', $html);

        // invalid payload -> route resolves, validation rejects (no mutation)
        $this->actingAs($user)
            ->post(route('savings-accounts.do-deposit', $account), ['amount' => 0])
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)
            ->post(route('savings-accounts.do-withdraw', $account), ['amount' => 0])
            ->assertRedirect()
            ->assertSessionHasErrors('amount');
    }
}
