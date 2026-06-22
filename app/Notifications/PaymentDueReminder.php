<?php

namespace App\Notifications;

use App\Models\Repayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentDueReminder extends Notification
{
    use Queueable;

    public function __construct(public Repayment $repayment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loan = $this->repayment->loan;
        $daysLeft = now()->diffInDays($this->repayment->due_date, false);

        return (new MailMessage)
            ->subject('Payment Reminder - Installment #'.$this->repayment->installment_number.' Due Soon')
            ->greeting('Hello '.$notifiable->fullname.'!')
            ->line('This is a reminder that your loan installment is due soon.')
            ->line('Loan Purpose: '.$loan->purpose)
            ->line('Installment #'.$this->repayment->installment_number.' of '.$loan->repayments->count())
            ->line('Amount Due: TZS '.number_format($this->repayment->amount, 2))
            ->line('Due Date: '.$this->repayment->due_date->format('F d, Y'))
            ->lineIf($daysLeft >= 0, 'Days Remaining: '.(int) $daysLeft)
            ->lineIf($daysLeft < 0, 'This payment is overdue by '.(int) abs($daysLeft).' day(s). Please make your payment immediately to avoid penalties.')
            ->action('View Loan Details', url(route('loans.show', $loan)))
            ->line('Thank you for using our services!');
    }
}
