<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:'.implode(',', (array) config('hdtms.tickets.attachment_extensions')),
                'mimetypes:'.implode(',', (array) config('hdtms.tickets.attachment_mime_types')),
                'max:'.(int) config('hdtms.tickets.attachment_max_kb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = (int) config('hdtms.tickets.attachment_max_kb') / 1024;

        return [
            'title.required' => 'Give the problem a short title.',
            'title.min' => 'The title needs at least :min characters.',
            'title.max' => 'The title can be at most :max characters.',
            'category_id.required' => 'Choose a category.',
            'category_id.exists' => 'Choose one of the listed categories.',
            'priority.required' => 'Choose how urgent this is.',
            'priority.enum' => 'Choose how urgent this is.',
            'description.required' => 'Describe the problem so an agent knows where to start.',
            'description.min' => 'Please say a little more. The description needs at least :min characters.',
            'description.max' => 'The description can be at most :max characters.',
            'attachment.uploaded' => "The file could not be uploaded. It may be larger than {$maxMb} MB.",
            'attachment.file' => 'Attach one file.',
            'attachment.mimes' => 'Attach a JPG, PNG or PDF file.',
            'attachment.mimetypes' => 'Attach a JPG, PNG or PDF file.',
            'attachment.max' => "The file is larger than {$maxMb} MB. Choose a smaller one.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['category_id' => 'category'];
    }

    /**
     * @return array{title: string, description: string, category_id: int, priority: TicketPriority}
     */
    public function ticketData(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'description' => (string) $this->validated('description'),
            'category_id' => (int) $this->validated('category_id'),
            'priority' => TicketPriority::from((string) $this->validated('priority')),
        ];
    }
}
