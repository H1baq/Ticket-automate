<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to get an attachment. Files live on the private disk, so there is no public address
 * for them; this route checks that the person may see the ticket first.
 */
class TicketAttachmentController extends Controller
{
    public function __invoke(Ticket $ticket): StreamedResponse
    {
        $this->authorize('view', $ticket);

        $path = $ticket->attachment_path;
        $disk = Storage::disk('local');

        abort_if($path === null || ! $disk->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Always a download, never shown inside the page, and the browser must not guess another type.
        return $disk->download($path, "{$ticket->ticket_no}-attachment.{$extension}", [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
