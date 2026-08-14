<?php

namespace Tests\Feature;

use App\Mail\TicketSurveyMail;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SurveyTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->customer = Customer::create([
            'code' => 'ARIA', 'name' => 'شرکت آریا', 'email' => 'customer@aria.test',
        ]);
        $this->staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_SUPPORT_STAFF,
        ]);
    }

    private function ticket(): Ticket
    {
        $t = Ticket::create(['customer_id' => $this->customer->id, 'subject' => 'خرابی', 'description' => 'شرح']);
        $t->update(['status' => Ticket::STATUS_IN_PROGRESS]);

        return $t;
    }

    public function test_resolving_ticket_sends_survey_invite(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);

        Mail::assertSent(TicketSurveyMail::class, fn ($mail) => $mail->hasTo('customer@aria.test')
            && $mail->ticket->is($ticket));
    }

    public function test_no_survey_without_customer_email(): void
    {
        $this->customer->update(['email' => null]);
        $ticket = $this->ticket();
        $ticket->update(['status' => Ticket::STATUS_RESOLVED]);

        Mail::assertNothingSent();
    }

    public function test_no_survey_on_other_status_transitions(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['status' => Ticket::STATUS_WAITING_CUSTOMER]);

        Mail::assertNothingSent();
    }

    public function test_survey_page_requires_valid_signature(): void
    {
        $ticket = $this->ticket();

        $this->get(route('survey.show', $ticket))->assertForbidden();
    }

    public function test_signed_survey_link_shows_the_page(): void
    {
        $ticket = $this->ticket();
        $url = URL::temporarySignedRoute('survey.show', now()->addDays(30), ['ticket' => $ticket]);

        $this->get($url)->assertOk()->assertSee($ticket->number);
    }

    public function test_submitting_rating_saves_it(): void
    {
        $ticket = $this->ticket();
        $url = URL::temporarySignedRoute('survey.show', now()->addDays(30), ['ticket' => $ticket]);

        $response = $this->post($url, ['rating' => 5, 'rating_comment' => 'عالی بود']);

        $response->assertOk();
        $ticket->refresh();
        $this->assertSame(5, $ticket->rating);
        $this->assertSame('عالی بود', $ticket->rating_comment);
    }

    public function test_second_submission_does_not_overwrite_rating(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['rating' => 4, 'rating_comment' => 'خوب بود']);
        $url = URL::temporarySignedRoute('survey.show', now()->addDays(30), ['ticket' => $ticket]);

        $this->post($url, ['rating' => 1, 'rating_comment' => 'بد بود']);

        $ticket->refresh();
        $this->assertSame(4, $ticket->rating);
        $this->assertSame('خوب بود', $ticket->rating_comment);
    }

    public function test_already_rated_ticket_shows_thanks_on_get(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['rating' => 5]);
        $url = URL::temporarySignedRoute('survey.show', now()->addDays(30), ['ticket' => $ticket]);

        $this->get($url)->assertOk()->assertSee(__('survey.already_rated'));
    }

    public function test_invalid_rating_value_is_rejected(): void
    {
        $ticket = $this->ticket();
        $url = URL::temporarySignedRoute('survey.show', now()->addDays(30), ['ticket' => $ticket]);

        $this->post($url, ['rating' => 9])->assertSessionHasErrors('rating');
    }
}
