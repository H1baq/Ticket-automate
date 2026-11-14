<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'tickets/2026/11/abcDEF1234567890abcDEF1234567890abcDEF12.pdf';

    private User $me;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Storage::disk('local')->put(self::PATH, '%PDF-1.4 the secret contents');
        $this->me = User::factory()->create();
        $this->ticket = Ticket::factory()->for($this->me, 'requester')->create(['ticket_no' => 'HD-2026-00142', 'attachment_path' => self::PATH]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function download(?User $user = null, string $number = 'HD-2026-00142'): TestResponse
    {
        return $this->actingAs($user ?? $this->me)->get("/tickets/{$number}/attachment");
    }

    public function test_the_requester_downloads_their_attachment_as_a_named_file(): void
    {
        $response = $this->download();

        $response->assertOk();
        $this->assertSame('%PDF-1.4 the secret contents', $response->streamedContent());
        $response->assertHeader('Content-Disposition', 'attachment; filename=HD-2026-00142-attachment.pdf');
    }

    public function test_the_download_is_never_shown_inline_and_the_browser_may_not_guess_its_type(): void
    {
        $response = $this->download();

        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_stored_name_is_never_used_as_the_download_name(): void
    {
        $this->assertStringNotContainsString('abcDEF', (string) $this->download()->headers->get('Content-Disposition'));
    }

    public function test_another_user_cannot_download_it(): void
    {
        $this->download(User::factory()->create())->assertForbidden();
    }

    public function test_the_assigned_agent_and_an_administrator_can_download_it_but_other_agents_cannot(): void
    {
        $agent = User::factory()->agent()->create();
        $this->ticket->forceFill(['agent_id' => $agent->id])->save();

        $this->download($agent)->assertOk();
        $this->download(User::factory()->admin()->create())->assertOk();
        $this->download(User::factory()->agent()->create())->assertForbidden();
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_sign_in(): void
    {
        $this->get('/tickets/HD-2026-00142/attachment')->assertRedirect('/login');
    }

    public function test_a_deactivated_user_cannot_download(): void
    {
        $this->me->forceFill(['is_active' => false])->save();

        $this->download()->assertRedirect('/login');
    }

    public function test_a_ticket_without_an_attachment_gives_404(): void
    {
        $this->ticket->forceFill(['attachment_path' => null])->save();

        $this->download()->assertNotFound();
    }

    public function test_a_missing_file_gives_404_instead_of_an_error_page(): void
    {
        Storage::disk('local')->delete(self::PATH);

        $this->download()->assertNotFound();
    }

    public function test_an_unknown_ticket_gives_404(): void
    {
        $this->download(null, 'HD-2026-99999')->assertNotFound();
    }

    public function test_the_ticket_page_offers_the_download_only_when_there_is_one_and_hides_the_stored_path(): void
    {
        $html = $this->actingAs($this->me)->get('/tickets/HD-2026-00142')->assertOk()->assertSee('Download attachment')->assertSee('(PDF)')->getContent();

        $this->assertStringContainsString(route('tickets.attachment', $this->ticket), $html);
        $this->assertStringNotContainsString('abcDEF1234567890', $html);
        $this->assertStringNotContainsString('tickets/2026/11', $html);

        $this->ticket->forceFill(['attachment_path' => null])->save();
        $this->actingAs($this->me)->get('/tickets/HD-2026-00142')->assertDontSee('Download attachment');
    }

    public function test_files_are_not_reachable_from_the_public_web_folder(): void
    {
        $this->assertFalse(Storage::disk('public')->exists(self::PATH));
        $this->get('/storage/'.self::PATH)->assertNotFound();
    }

    public function test_the_attachment_route_is_guarded_by_sign_in_and_an_active_account(): void
    {
        $route = app('router')->getRoutes()->getByName('tickets.attachment');

        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
    }
}
