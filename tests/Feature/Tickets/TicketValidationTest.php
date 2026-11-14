<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * TC16: every limit of the new ticket form, tested on both sides of the boundary.
 */
class TicketValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->seed([CategorySeeder::class, SlaPolicySeeder::class]);
        $this->me = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return [
            'title' => 'Wi-Fi keeps disconnecting',
            'category_id' => Category::query()->where('name', 'Network')->value('id'),
            'priority' => 'medium',
            'description' => 'It drops every ten minutes in the training room.',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return TestResponse<Response>
     */
    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->me)->from('/tickets/create')->post('/tickets', $this->input($overrides));
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertAccepted(TestResponse $response): void
    {
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tickets', 1);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertRefused(TestResponse $response, string $field): void
    {
        $response->assertRedirect('/tickets/create')->assertSessionHasErrors($field);
        $this->assertDatabaseCount('tickets', 0);
    }

    // --- title: 5 to 150 characters -----------------------------------------------------------

    public function test_tc16_a_title_of_four_characters_is_refused_and_five_is_accepted(): void
    {
        $this->assertRefused($this->submit(['title' => 'abcd']), 'title');
        $this->assertAccepted($this->submit(['title' => 'abcde']));
    }

    public function test_tc16_a_title_of_150_characters_is_accepted_and_151_is_refused(): void
    {
        $this->assertAccepted($this->submit(['title' => str_repeat('a', 150)]));
        Ticket::query()->delete();
        $this->assertRefused($this->submit(['title' => str_repeat('a', 151)]), 'title');
    }

    public function test_the_title_is_counted_in_characters_not_bytes(): void
    {
        $this->assertAccepted($this->submit(['title' => 'éééée']));
        Ticket::query()->delete();
        $this->assertRefused($this->submit(['title' => 'éééé']), 'title');
    }

    public function test_spaces_around_the_title_do_not_count_toward_its_length(): void
    {
        $this->assertRefused($this->submit(['title' => '   abcd   ']), 'title');
    }

    public function test_a_title_is_required(): void
    {
        $this->assertRefused($this->submit(['title' => '']), 'title');
        $this->assertRefused($this->submit(['title' => null]), 'title');
    }

    // --- description: 10 to 5000 characters ------------------------------------------------------

    public function test_tc16_a_description_of_nine_characters_is_refused_and_ten_is_accepted(): void
    {
        $this->assertRefused($this->submit(['description' => str_repeat('a', 9)]), 'description');
        $this->assertAccepted($this->submit(['description' => str_repeat('a', 10)]));
    }

    public function test_tc16_a_description_of_5000_characters_is_accepted_and_5001_is_refused(): void
    {
        $this->assertAccepted($this->submit(['description' => str_repeat('a', 5000)]));
        Ticket::query()->delete();
        $this->assertRefused($this->submit(['description' => str_repeat('a', 5001)]), 'description');
    }

    public function test_a_description_is_required(): void
    {
        $this->assertRefused($this->submit(['description' => '']), 'description');
    }

    // --- category and priority -------------------------------------------------------------------

    public function test_a_category_is_required_and_must_exist(): void
    {
        $this->assertRefused($this->submit(['category_id' => '']), 'category_id');
        $this->assertRefused($this->submit(['category_id' => 99999]), 'category_id');
        $this->assertRefused($this->submit(['category_id' => 'abc']), 'category_id');
        $this->assertRefused($this->submit(['category_id' => [1, 2]]), 'category_id');
    }

    public function test_a_deactivated_category_cannot_be_chosen(): void
    {
        $category = Category::factory()->inactive()->create();

        $this->assertRefused($this->submit(['category_id' => $category->id]), 'category_id');
    }

    public function test_leaving_the_priority_unchosen_is_refused_with_a_plain_message_and_no_default_is_applied(): void
    {
        $data = $this->input();
        unset($data['priority']);

        $this->actingAs($this->me)->from('/tickets/create')->post('/tickets', $data)->assertSessionHasErrors(['priority' => 'Choose how urgent this is.']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_the_priority_must_be_one_of_the_three(): void
    {
        $this->assertRefused($this->submit(['priority' => 'urgent']), 'priority');
        $this->assertRefused($this->submit(['priority' => '']), 'priority');
        $this->assertRefused($this->submit(['priority' => 'HIGH']), 'priority');

        foreach (['low', 'medium', 'high'] as $priority) {
            $this->submit(['priority' => $priority])->assertSessionHasNoErrors();
        }
    }

    // --- attachment: jpg, png or pdf, 5 MB at most ----------------------------------------------------

    public function test_tc16_a_file_of_exactly_five_megabytes_is_accepted(): void
    {
        $this->assertAccepted($this->submit(['attachment' => UploadedFile::fake()->create('scan.pdf', 5120, 'application/pdf')]));
    }

    public function test_tc16_a_file_just_over_five_megabytes_and_one_of_six_are_refused(): void
    {
        $this->assertRefused($this->submit(['attachment' => UploadedFile::fake()->create('scan.pdf', 5121, 'application/pdf')]), 'attachment');
        $this->assertRefused($this->submit(['attachment' => UploadedFile::fake()->create('scan.pdf', 6 * 1024, 'application/pdf')]), 'attachment');
    }

    public function test_the_size_message_says_how_big_a_file_may_be(): void
    {
        $this->submit(['attachment' => UploadedFile::fake()->create('scan.pdf', 6144, 'application/pdf')]);

        $this->get('/tickets/create')->assertSee('The file is larger than 5 MB. Choose a smaller one.');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function allowedFiles(): array
    {
        return [
            'jpg' => ['photo.jpg', 'image/jpeg'],
            'jpeg' => ['photo.jpeg', 'image/jpeg'],
            'png' => ['photo.png', 'image/png'],
            'pdf' => ['scan.pdf', 'application/pdf'],
        ];
    }

    #[DataProvider('allowedFiles')]
    public function test_tc16_jpg_png_and_pdf_files_are_accepted(string $name, string $mime): void
    {
        $this->assertAccepted($this->submit(['attachment' => UploadedFile::fake()->create($name, 100, $mime)]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedFiles(): array
    {
        return [
            'gif' => ['animation.gif', 'image/gif'],
            'exe' => ['setup.exe', 'application/x-msdownload'],
            'php' => ['shell.php', 'application/x-php'],
            'text' => ['notes.txt', 'text/plain'],
            'svg' => ['logo.svg', 'image/svg+xml'],
            'html' => ['page.html', 'text/html'],
            'zip' => ['files.zip', 'application/zip'],
            'word' => ['letter.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_tc16_other_file_types_are_refused(string $name, string $mime): void
    {
        $this->assertRefused($this->submit(['attachment' => UploadedFile::fake()->create($name, 100, $mime)]), 'attachment');
    }

    public function test_a_php_script_renamed_to_jpg_is_refused_because_its_content_is_checked(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<?php echo shell_exec($_GET["cmd"]); ?>');
        $disguised = new UploadedFile($path, 'holiday.jpg', 'image/jpeg', null, true);

        $this->assertRefused($this->submit(['attachment' => $disguised]), 'attachment');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_an_html_page_renamed_to_png_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<html><script>alert(1)</script></html>');

        $this->assertRefused($this->submit(['attachment' => new UploadedFile($path, 'logo.png', 'image/png', null, true)]), 'attachment');
    }

    public function test_a_real_image_is_accepted_even_with_a_misleading_name(): void
    {
        $image = UploadedFile::fake()->image('report.pdf');

        $this->assertAccepted($this->submit(['attachment' => $image]));
    }

    public function test_only_one_file_may_be_attached(): void
    {
        $this->assertRefused($this->submit(['attachment' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]]), 'attachment');
    }

    public function test_a_refused_ticket_leaves_no_file_behind(): void
    {
        $this->submit(['title' => 'abc', 'attachment' => UploadedFile::fake()->image('a.png')]);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // --- messages ---------------------------------------------------------------------------------

    public function test_each_error_is_a_plain_sentence_shown_next_to_the_field(): void
    {
        $this->submit(['title' => 'abc', 'description' => 'short', 'priority' => '', 'category_id' => '']);

        $this->get('/tickets/create')
            ->assertSee('The title needs at least 5 characters.')
            ->assertSee('Please say a little more. The description needs at least 10 characters.')
            ->assertSee('Choose how urgent this is.')
            ->assertSee('Choose a category.');
    }

    public function test_the_form_keeps_what_was_typed_after_an_error(): void
    {
        $category = Category::query()->where('name', 'Printing')->firstOrFail();
        $this->submit(['title' => 'abc', 'description' => 'My typed description here', 'priority' => 'high', 'category_id' => $category->id]);

        $this->get('/tickets/create')
            ->assertSee('value="abc"', false)
            ->assertSee('My typed description here')
            ->assertSee('<option value="'.$category->id.'" selected>Printing</option>', false)
            ->assertSee('value="high"', false);
    }
}
