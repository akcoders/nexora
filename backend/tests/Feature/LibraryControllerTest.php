<?php

namespace Tests\Feature;

use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Models\LibraryResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technician;

    private LibraryCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $this->technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $this->category = LibraryCategory::where('slug', 'installation')->firstOrFail();
    }

    private function guide(array $attributes = [], ?LibraryCategory $category = null): LibraryArticle
    {
        return LibraryArticle::factory()->for($category ?? $this->category, 'category')->create($attributes);
    }

    private function manager(): User
    {
        return tap(User::factory()->create(['status' => 'active']))->assignRole('Manager');
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'How to clean a split AC filter',
            'library_category_id' => $this->category->id,
            'summary' => 'Monthly filter cleaning.',
            'body' => '<h2>Steps</h2><ol><li data-list="ordered"><span class="ql-ui" contenteditable="false"></span>Switch off the MCB</li><li data-list="checked"><span class="ql-ui" contenteditable="false"></span>Filter refitted</li></ol>',
            'device_type' => 'split_ac',
            'manufacturer' => 'daikin',
            'model_numbers' => 'FTKF50',
            'tags' => 'filter, PM, filter',
            'status' => 'published',
            'is_featured' => '0',
        ], $overrides);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('library.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_library_read_are_forbidden(): void
    {
        $guide = $this->guide();
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('library.index'))->assertForbidden();
        $this->actingAs($user)->get(route('library.show', $guide))->assertForbidden();
    }

    public function test_technician_browses_and_reads_published_guides_but_never_sees_drafts(): void
    {
        $published = $this->guide(['title' => 'Cassette drain pump test']);
        $draft = $this->guide(['title' => 'Unfinished chiller notes', 'status' => 'draft', 'published_at' => null]);

        $this->actingAs($this->technician)->get(route('library.index'))
            ->assertOk()
            ->assertSee('Cassette drain pump test')
            ->assertDontSee('Unfinished chiller notes')
            ->assertDontSee(route('library.create'));

        $this->actingAs($this->technician)->get(route('library.show', $published))
            ->assertOk()
            ->assertSee('Cassette drain pump test')
            ->assertDontSee(route('library.edit', $published));
        $this->assertSame(1, $published->fresh()->views_count);

        $this->actingAs($this->technician)->get(route('library.show', $draft))->assertNotFound();
    }

    public function test_reading_a_guide_counts_a_view_without_changing_its_last_updated_date(): void
    {
        $guide = $this->guide(['updated_at' => now()->subWeek()]);
        $updatedAt = $guide->fresh()->updated_at;

        $this->actingAs($this->technician)->get(route('library.show', $guide))->assertOk();
        $this->actingAs($this->technician)->get(route('library.show', $guide))->assertOk();

        $guide->refresh();
        $this->assertSame(1, $guide->views_count);
        $this->assertTrue($guide->updated_at->equalTo($updatedAt));
    }

    public function test_technician_cannot_open_the_editor_or_change_guides(): void
    {
        $guide = $this->guide();
        $this->actingAs($this->technician);

        $this->get(route('library.manage'))->assertForbidden();
        $this->get(route('library.create'))->assertForbidden();
        $this->get(route('library.edit', $guide))->assertForbidden();
        $this->post(route('library.store'), $this->payload())->assertForbidden();
        $this->put(route('library.update', $guide), $this->payload())->assertForbidden();
        $this->delete(route('library.destroy', $guide))->assertForbidden();
        $this->post(route('library.uploads.image'), ['image' => UploadedFile::fake()->image('a.png')])->assertForbidden();
        $this->assertNotSoftDeleted($guide);
    }

    public function test_editors_see_drafts_in_the_library_and_the_manage_list(): void
    {
        $this->guide(['title' => 'Draft VRF commissioning', 'status' => 'draft', 'published_at' => null]);

        $this->actingAs($this->admin)->get(route('library.index'))->assertOk()->assertSee('Draft VRF commissioning')->assertSee('Draft');
        $this->actingAs($this->admin)->get(route('library.manage'))->assertOk()->assertSee('Draft VRF commissioning');
    }

    public function test_search_and_filters_narrow_the_browse_grid(): void
    {
        $safety = LibraryCategory::where('slug', 'safety')->firstOrFail();
        $this->guide(['title' => 'Daikin gas charging', 'device_type' => 'split_ac', 'manufacturer' => 'daikin', 'body_text' => 'Use R32 gauges']);
        $this->guide(['title' => 'Voltas window cleaning', 'device_type' => 'window_ac', 'manufacturer' => 'voltas']);
        $this->guide(['title' => 'Ladder safety basics'], $safety);
        $video = $this->guide(['title' => 'Blower removal video', 'body' => null, 'body_text' => null]);
        LibraryResource::factory()->video()->for($video, 'article')->create();

        $this->actingAs($this->technician);
        $this->get(route('library.index', ['q' => 'R32 gauges']))->assertOk()->assertSee('Daikin gas charging')->assertDontSee('Voltas window cleaning');
        $this->get(route('library.index', ['device_type' => 'window_ac']))->assertSee('Voltas window cleaning')->assertDontSee('Daikin gas charging');
        $this->get(route('library.index', ['manufacturer' => 'daikin']))->assertSee('Daikin gas charging')->assertDontSee('Ladder safety basics');
        $this->get(route('library.index', ['category' => 'safety']))->assertSee('Ladder safety basics')->assertDontSee('Daikin gas charging');
        $this->get(route('library.index', ['content' => 'video']))->assertSee('Blower removal video')->assertDontSee('Ladder safety basics');
        $this->get(route('library.index', ['q' => 'nothing-matches-this']))->assertSee('No guides match your search');
    }

    public function test_creating_a_guide_saves_steps_video_link_and_document(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->admin)->post(route('library.store'), $this->payload([
            'resources' => [
                ['kind' => 'video', 'title' => 'Demo', 'url' => 'https://youtu.be/dQw4w9WgXcQ'],
                ['kind' => 'link', 'title' => '', 'url' => 'https://www.daikin.com/support'],
                ['kind' => 'document', 'title' => 'Manual', 'file' => UploadedFile::fake()->create('manual.pdf', 120, 'application/pdf')],
            ],
        ]));

        $guide = LibraryArticle::query()->latest('id')->firstOrFail();
        $response->assertSessionHasNoErrors()->assertRedirect(route('library.show', $guide));
        $this->assertSame('How to clean a split AC filter', $guide->title);
        $this->assertSame('published', $guide->status);
        $this->assertNotNull($guide->published_at);
        $this->assertSame('filter, PM', $guide->tags);
        $this->assertSame('<h2>Steps</h2><ol><li>Switch off the MCB</li></ol><ul class="nx-checklist"><li data-checked="true">Filter refitted</li></ul>', $guide->body);
        $this->assertSame(['video', 'link', 'document'], $guide->resources->pluck('kind')->all());
        $this->assertSame('dQw4w9WgXcQ', $guide->resources[0]->video_id);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $guide->resources[0]->url);
        $document = $guide->resources[2];
        $this->assertSame('manual.pdf', $document->file_name);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_video_links_must_be_youtube_videos(): void
    {
        $this->actingAs($this->admin)->post(route('library.store'), $this->payload([
            'resources' => [['kind' => 'video', 'url' => 'https://vimeo.com/123456']],
        ]))->assertSessionHasErrors(['resources.0.url' => 'Paste a YouTube video link, e.g. https://www.youtube.com/watch?v=… or https://youtu.be/….']);

        $this->assertDatabaseCount('library_articles', 0);
    }

    public function test_resource_problems_are_reported_together_with_other_field_errors(): void
    {
        $this->actingAs($this->admin)->post(route('library.store'), $this->payload([
            'title' => '',
            'resources' => [
                ['kind' => 'video', 'url' => 'https://vimeo.com/123456'],
                ['kind' => 'document', 'title' => 'Manual'],
                ['kind' => 'link', 'url' => 'not a link'],
            ],
        ]))->assertSessionHasErrors([
            'title',
            'resources.0.url' => 'Paste a YouTube video link, e.g. https://www.youtube.com/watch?v=… or https://youtu.be/….',
            'resources.1.file' => 'Choose the document to upload.',
            'resources.2.url' => 'Enter a full link starting with http:// or https://.',
        ]);

        $this->assertDatabaseCount('library_articles', 0);
    }

    public function test_a_guide_needs_written_steps_or_at_least_one_resource(): void
    {
        $this->actingAs($this->admin)->post(route('library.store'), $this->payload(['body' => '<p><br></p>']))
            ->assertSessionHasErrors(['body' => 'Write the steps, or add a video, document or link.']);

        $this->assertDatabaseCount('library_articles', 0);
    }

    public function test_validation_errors_bring_back_the_entered_resource_rows(): void
    {
        $this->actingAs($this->admin)->followingRedirects()->from(route('library.create'))->post(route('library.store'), $this->payload([
            'title' => '',
            'resources' => [['kind' => 'link', 'title' => 'Error code list', 'url' => 'https://example.com/codes']],
        ]))
            ->assertOk()
            ->assertSee('The guide was not saved.')
            ->assertSee('value="https://example.com/codes"', false)
            ->assertSee('value="Error code list"', false);
    }

    public function test_unsafe_html_is_stripped_before_the_guide_is_saved(): void
    {
        $this->actingAs($this->admin)->post(route('library.store'), $this->payload([
            'body' => '<p onclick="steal()">Check the fuse</p><script>alert(1)</script><a href="javascript:alert(2)">bad</a><img src="x" onerror="alert(3)">',
        ]))->assertSessionHasNoErrors();

        $guide = LibraryArticle::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Check the fuse', $guide->body);
        $this->assertStringNotContainsString('onclick', $guide->body);
        $this->assertStringNotContainsString('<script', $guide->body);
        $this->assertStringNotContainsString('javascript:', $guide->body);
        $this->assertStringNotContainsString('onerror', $guide->body);

        $this->actingAs($this->technician)->get(route('library.show', $guide))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_updating_a_guide_removes_a_dropped_resource_and_its_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('library/9/old.pdf', 'pdf');
        $guide = $this->guide();
        $document = LibraryResource::factory()->document('library/9/old.pdf', 'old.pdf')->for($guide, 'article')->create();
        $link = LibraryResource::factory()->for($guide, 'article')->create(['url' => 'https://example.com/a']);

        $this->actingAs($this->admin)->put(route('library.update', $guide), $this->payload([
            'title' => 'Renamed guide',
            'resources' => [['id' => $link->id, 'kind' => 'link', 'title' => 'Kept link', 'url' => 'https://example.com/a']],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('library.show', $guide));

        $this->assertSame('Renamed guide', $guide->fresh()->title);
        $this->assertModelMissing($document);
        $this->assertSame('Kept link', $link->fresh()->title);
        Storage::disk('local')->assertMissing('library/9/old.pdf');
    }

    public function test_documents_must_have_an_allowed_file_extension(): void
    {
        Storage::fake('local');
        $pdfNamedAsHtml = UploadedFile::fake()->createWithContent('manual.html', "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $this->actingAs($this->admin)->post(route('library.store'), $this->payload([
            'resources' => [['kind' => 'document', 'file' => $pdfNamedAsHtml]],
        ]))->assertSessionHasErrors('resources.0.file');

        $this->assertDatabaseCount('library_resources', 0);
    }

    public function test_a_saved_link_cannot_become_a_document_without_a_file(): void
    {
        $guide = $this->guide();
        $link = LibraryResource::factory()->for($guide, 'article')->create(['url' => 'https://example.com/a']);

        $this->actingAs($this->admin)->put(route('library.update', $guide), $this->payload([
            'resources' => [['id' => $link->id, 'kind' => 'document']],
        ]))->assertSessionHasErrors('resources.0.file');

        $this->assertSame(LibraryResource::KIND_LINK, $link->fresh()->kind);
    }

    public function test_new_guides_cannot_use_a_hidden_category_but_existing_ones_keep_theirs(): void
    {
        $hidden = LibraryCategory::factory()->inactive()->create();
        $guide = $this->guide([], $hidden);

        $this->actingAs($this->admin)->post(route('library.store'), $this->payload(['library_category_id' => $hidden->id]))
            ->assertSessionHasErrors('library_category_id');
        $this->actingAs($this->admin)->put(route('library.update', $guide), $this->payload(['library_category_id' => $hidden->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_manager_can_edit_guides_but_not_delete_them(): void
    {
        $guide = $this->guide();
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('library.edit', $guide))->assertOk()->assertSee($guide->title);
        $this->actingAs($manager)->delete(route('library.destroy', $guide))->assertForbidden();
        $this->assertNotSoftDeleted($guide);
    }

    public function test_admin_deletes_a_guide(): void
    {
        $guide = $this->guide();

        $this->actingAs($this->admin)->delete(route('library.destroy', $guide))->assertRedirect(route('library.manage'));

        $this->assertSoftDeleted($guide);
        $this->actingAs($this->technician)->get(route('library.show', $guide))->assertNotFound();
    }

    public function test_documents_open_inline_or_download_for_readers_of_the_guide(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('library/1/manual.pdf', '%PDF-1.4 demo');
        Storage::disk('local')->put('library/1/sheet.xlsx', 'xlsx');
        $guide = $this->guide();
        $pdf = LibraryResource::factory()->document('library/1/manual.pdf', 'manual.pdf')->for($guide, 'article')->create();
        $sheet = LibraryResource::factory()->document('library/1/sheet.xlsx', 'Log sheet.xlsx')->for($guide, 'article')->create();

        $this->actingAs($this->technician)->get(route('library.files.show', [$guide, $pdf]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=manual.pdf');
        $this->actingAs($this->technician)->get(route('library.files.show', [$guide, $sheet]))
            ->assertOk()
            ->assertDownload('Log sheet.xlsx');
    }

    public function test_document_route_hides_files_of_drafts_and_other_guides(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('library/2/manual.pdf', '%PDF-1.4 demo');
        $draft = $this->guide(['status' => 'draft', 'published_at' => null]);
        $file = LibraryResource::factory()->document('library/2/manual.pdf')->for($draft, 'article')->create();
        $otherGuide = $this->guide();

        $this->actingAs($this->technician)->get(route('library.files.show', [$draft, $file]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('library.files.show', [$otherGuide, $file]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('library.files.show', [$draft, $file]))->assertOk();
    }

    public function test_editor_image_upload_stores_the_picture_and_returns_its_url(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->manager())->postJson(route('library.uploads.image'), ['image' => UploadedFile::fake()->image('coil.png', 800, 600)]);

        $response->assertCreated();
        $url = $response->json('url');
        $this->assertStringStartsWith('/storage/library/images/', $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }

    public function test_editor_image_upload_rejects_files_that_are_not_images(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson(route('library.uploads.image'), ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Only JPG, PNG, WebP or GIF images can be added to a guide.']);
    }
}
