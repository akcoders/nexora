<?php

namespace Tests\Feature;

use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Models\LibraryResource;
use App\Models\ServiceMasterOption;
use App\Models\User;
use App\Services\LibraryOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LibraryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $this->technician = User::where('email', 'technician@nexora.test')->firstOrFail();
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return array_column($response->json('data'), 'id');
    }

    private function guide(LibraryCategory $category, array $attributes = []): LibraryArticle
    {
        return LibraryArticle::factory()->for($category, 'category')->create($attributes);
    }

    public function test_library_endpoints_reject_guests_with_401(): void
    {
        $this->getJson('/api/v1/library/filters')->assertUnauthorized();
        $this->getJson('/api/v1/library')->assertUnauthorized();
        $this->getJson('/api/v1/library/1')->assertUnauthorized();
    }

    public function test_users_without_library_read_get_403(): void
    {
        $guide = $this->guide(LibraryCategory::factory()->create());
        Sanctum::actingAs(User::factory()->create(['status' => 'active']));

        $this->getJson('/api/v1/library/filters')->assertForbidden()->assertJsonPath('message', 'You do not have access to the library.');
        $this->getJson('/api/v1/library')->assertForbidden();
        $this->getJson("/api/v1/library/{$guide->id}")->assertForbidden();
        $this->assertSame(0, $guide->fresh()->views_count);
    }

    public function test_filters_list_active_categories_and_masters_with_counts_of_guides_the_user_can_see(): void
    {
        $category = LibraryCategory::factory()->create(['name' => 'Gas Charging', 'slug' => 'gas-charging', 'icon' => 'wrench', 'color' => 'warning', 'description' => 'Charging steps.']);
        $hidden = LibraryCategory::factory()->inactive()->create();
        $this->guide($category);
        $this->guide($category);
        LibraryArticle::factory()->draft()->for($category, 'category')->create();
        $this->guide($category)->delete();
        $this->guide($hidden);
        ServiceMasterOption::where('type', 'library_device_type')->where('code', 'window_ac')->update(['active' => false]);

        Sanctum::actingAs($this->technician);
        $response = $this->getJson('/api/v1/library/filters')->assertOk();

        $categories = collect($response->json('categories'));
        $this->assertSame(
            ['id' => $category->id, 'name' => 'Gas Charging', 'slug' => 'gas-charging', 'icon' => 'wrench', 'color' => 'warning', 'description' => 'Charging steps.', 'count' => 2],
            $categories->firstWhere('id', $category->id),
        );
        $this->assertNull($categories->firstWhere('id', $hidden->id));
        $this->assertContains(['code' => 'split_ac', 'label' => 'Split AC'], $response->json('device_types'));
        $this->assertNotContains('window_ac', array_column($response->json('device_types'), 'code'));
        $this->assertContains(['code' => 'daikin', 'label' => 'Daikin'], $response->json('manufacturers'));
        $this->assertSame(['article', 'video', 'document', 'link'], array_column($response->json('content_types'), 'key'));
        $response->assertJsonPath('content_types.1', ['key' => 'video', 'label' => 'Video', 'icon' => 'square-play']);

        Sanctum::actingAs($this->admin);
        $adminCategories = collect($this->getJson('/api/v1/library/filters')->assertOk()->json('categories'));
        $this->assertSame(3, $adminCategories->firstWhere('id', $category->id)['count']);
    }

    public function test_list_returns_the_card_shape_and_applies_search_and_filters(): void
    {
        $this->travelTo('2026-11-03 10:00:00');
        $installation = LibraryCategory::factory()->create(['name' => 'Split Installs', 'slug' => 'split-installs', 'icon' => 'hammer', 'color' => 'primary']);
        $chillers = LibraryCategory::factory()->create();
        $split = $this->guide($installation, [
            'title' => 'Clean split AC filters',
            'summary' => 'Wash and dry the filters every month.',
            'device_type' => 'split_ac',
            'manufacturer' => 'daikin',
            'model_numbers' => 'FTKF50',
            'tags' => 'filters, cleaning',
            'views_count' => 4,
        ]);
        LibraryResource::factory()->video('abcdefghijk')->for($split, 'article')->create();
        $chiller = $this->guide($chillers, ['title' => 'Chiller start-up', 'body_text' => 'Check the ZQ-41 compressor oil level first.', 'device_type' => 'chiller', 'manufacturer' => 'voltas']);
        LibraryResource::factory()->document()->for($chiller, 'article')->create();
        $linkOnly = $this->guide($installation, ['body' => null, 'body_text' => null, 'device_type' => 'split_ac', 'manufacturer' => 'voltas']);
        LibraryResource::factory()->for($linkOnly, 'article')->create();

        Sanctum::actingAs($this->technician);

        $this->getJson("/api/v1/library?category={$installation->id}&device_type=split_ac&manufacturer=daikin")->assertOk()
            ->assertJsonPath('data.0', [
                'id' => $split->id,
                'title' => 'Clean split AC filters',
                'summary' => 'Wash and dry the filters every month.',
                'category' => ['id' => $installation->id, 'name' => 'Split Installs', 'slug' => 'split-installs', 'icon' => 'hammer', 'color' => 'primary'],
                'device_type' => ['code' => 'split_ac', 'label' => 'Split AC'],
                'manufacturer' => ['code' => 'daikin', 'label' => 'Daikin'],
                'model_numbers' => 'FTKF50',
                'tags' => ['filters', 'cleaning'],
                'status' => 'published',
                'is_featured' => false,
                'has_steps' => true,
                'counts' => ['video' => 1, 'document' => 0, 'link' => 0],
                'cover_url' => 'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg',
                'views_count' => 4,
                'published_at' => '2026-11-03T10:00:00+05:30',
                'updated_at' => '2026-11-03T10:00:00+05:30',
            ])
            ->assertJsonCount(1, 'data');
        $this->assertSame([$chiller->id], $this->ids($this->getJson('/api/v1/library?q=ZQ-41 compressor oil')->assertOk()));
        $this->assertEqualsCanonicalizing([$split->id, $linkOnly->id], $this->ids($this->getJson('/api/v1/library?category=split-installs')->assertOk()));
        $this->assertSame([$split->id], $this->ids($this->getJson('/api/v1/library?category=split-installs&content=video')->assertOk()));
        $this->assertSame([$split->id], $this->ids($this->getJson('/api/v1/library?category=split-installs&content=article')->assertOk()));
        $this->assertSame([$linkOnly->id], $this->ids($this->getJson('/api/v1/library?category=split-installs&content=link')->assertOk()));
        $this->assertSame([$chiller->id], $this->ids($this->getJson("/api/v1/library?category={$chillers->id}&content=document")->assertOk()));
        $this->getJson('/api/v1/library?content=podcast')->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_list_paginates_featured_guides_first_then_newest(): void
    {
        $category = LibraryCategory::factory()->create();
        $oldest = $this->guide($category, ['published_at' => now()->subDays(3)]);
        $newest = $this->guide($category, ['published_at' => now()->subDay()]);
        $featured = LibraryArticle::factory()->featured()->for($category, 'category')->create(['published_at' => now()->subDays(5)]);

        Sanctum::actingAs($this->technician);

        $first = $this->getJson("/api/v1/library?category={$category->id}&per_page=2")->assertOk()
            ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2, 'per_page' => 2, 'total' => 3]);
        $this->assertSame([$featured->id, $newest->id], $this->ids($first));
        $second = $this->getJson("/api/v1/library?category={$category->id}&per_page=2&page=2")->assertOk()
            ->assertJsonPath('meta.current_page', 2);
        $this->assertSame([$oldest->id], $this->ids($second));
        $this->getJson("/api/v1/library?category={$category->id}&per_page=500")->assertOk()->assertJsonPath('meta.per_page', 50);
        $this->getJson("/api/v1/library?category={$category->id}")->assertOk()->assertJsonPath('meta.per_page', 20);
    }

    public function test_technician_cannot_see_drafts_but_editors_can(): void
    {
        $category = LibraryCategory::factory()->create();
        $published = $this->guide($category);
        $draft = LibraryArticle::factory()->draft()->for($category, 'category')->create();

        Sanctum::actingAs($this->technician);
        $this->assertSame([$published->id], $this->ids($this->getJson("/api/v1/library?category={$category->id}")->assertOk()));
        $this->getJson("/api/v1/library/{$draft->id}")->assertNotFound();

        Sanctum::actingAs($this->admin);
        $this->assertEqualsCanonicalizing([$published->id, $draft->id], $this->ids($this->getJson("/api/v1/library?category={$category->id}")->assertOk()));
        $this->getJson("/api/v1/library/{$draft->id}")->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_soft_deleted_guide_is_not_found(): void
    {
        $guide = $this->guide(LibraryCategory::factory()->create());
        $guide->delete();

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/library/{$guide->id}")->assertNotFound();
    }

    public function test_detail_returns_steps_with_absolute_urls_grouped_resources_related_guides_and_authors(): void
    {
        Storage::fake('local');
        $category = LibraryCategory::factory()->create();
        $other = LibraryCategory::factory()->create();
        $guide = $this->guide($category, [
            'device_type' => 'test_rig',
            'summary' => null,
            'body' => '<p>Open the panel.</p><p><img src="/storage/library/images/panel.png" alt="Panel"></p><p><a href="https://example.com/manual" target="_blank">Manual</a></p>',
            'body_text' => "Open the panel.\nManual",
            'created_by' => $this->admin->id,
            'updated_by' => $this->technician->id,
        ]);
        $video = LibraryResource::factory()->video('dQw4w9WgXcQ')->for($guide, 'article')->create(['title' => 'Filter cleaning', 'sort_order' => 0]);
        $document = LibraryResource::factory()->document("library/{$guide->id}/manual.pdf", 'manual.pdf')->for($guide, 'article')->create(['title' => null, 'sort_order' => 1]);
        $link = LibraryResource::factory()->for($guide, 'article')->create(['title' => null, 'url' => 'https://www.daikin.co.in/support', 'sort_order' => 2]);
        $sameBoth = $this->guide($category, ['device_type' => 'test_rig', 'published_at' => now()->subDays(9)]);
        $sameCategory = $this->guide($category, ['published_at' => now()->subDay()]);
        $sameDevice = $this->guide($other, ['device_type' => 'test_rig', 'published_at' => now()->subDays(2)]);
        $this->guide($other, ['device_type' => 'other_rig']);
        LibraryArticle::factory()->draft()->for($category, 'category')->create();

        Sanctum::actingAs($this->technician);
        $response = $this->getJson("/api/v1/library/{$guide->id}")->assertOk();

        $response->assertJsonPath('data.id', $guide->id)
            ->assertJsonPath('data.full_summary', null)
            ->assertJsonPath('data.body_html', '<p>Open the panel.</p><p><img src="'.url('/storage/library/images/panel.png').'" alt="Panel"></p><p><a href="https://example.com/manual" target="_blank">Manual</a></p>')
            ->assertJsonPath('data.counts', ['video' => 1, 'document' => 1, 'link' => 1])
            ->assertJsonPath('data.cover_url', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->assertJsonPath('data.device_type', ['code' => 'test_rig', 'label' => 'Test Rig'])
            ->assertJsonPath('data.videos', [[
                'id' => $video->id,
                'title' => 'Filter cleaning',
                'video_id' => 'dQw4w9WgXcQ',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
                'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            ]])
            ->assertJsonPath('data.links', [['id' => $link->id, 'title' => 'www.daikin.co.in', 'url' => 'https://www.daikin.co.in/support', 'host' => 'daikin.co.in']])
            ->assertJsonPath('data.author', ['name' => $this->admin->name])
            ->assertJsonPath('data.updated_by', ['name' => $this->technician->name]);
        $response->assertJsonPath('data.documents.0.id', $document->id)
            ->assertJsonPath('data.documents.0.title', 'manual.pdf')
            ->assertJsonPath('data.documents.0.file_name', 'manual.pdf')
            ->assertJsonPath('data.documents.0.extension', 'pdf')
            ->assertJsonPath('data.documents.0.size_label', '122 KB')
            ->assertJsonPath('data.documents.0.mime_type', 'application/pdf');
        $this->assertStringStartsWith(url("/api/v1/library/files/{$document->id}?expires="), $response->json('data.documents.0.url'));
        $this->assertSame([$sameBoth->id, $sameCategory->id, $sameDevice->id], array_column($response->json('data.related'), 'id'));
        $response->assertJsonPath('data.related.0.category.id', $category->id);
    }

    public function test_a_view_counts_once_per_user_and_does_not_change_the_updated_date(): void
    {
        $guide = $this->guide(LibraryCategory::factory()->create(), ['views_count' => 7]);
        $editedAt = $guide->updated_at;
        $this->travel(5)->minutes();

        Sanctum::actingAs($this->technician);
        $this->getJson("/api/v1/library/{$guide->id}")->assertOk()->assertJsonPath('data.views_count', 8);
        $this->getJson("/api/v1/library/{$guide->id}")->assertOk()->assertJsonPath('data.views_count', 8);
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/library/{$guide->id}")->assertOk()->assertJsonPath('data.views_count', 9);

        $this->assertSame(9, $guide->fresh()->views_count);
        $this->assertTrue($guide->fresh()->updated_at->equalTo($editedAt));
    }

    public function test_signed_document_url_streams_the_file_and_rejects_tampered_missing_or_expired_signatures(): void
    {
        Storage::fake('local');
        $guide = $this->guide(LibraryCategory::factory()->create());
        LibraryResource::factory()->document("library/{$guide->id}/manual.pdf", 'Service manual.pdf')->for($guide, 'article')->create();
        Storage::disk('local')->put("library/{$guide->id}/manual.pdf", '%PDF-1.4 service manual');
        Sanctum::actingAs($this->technician);
        $url = $this->getJson("/api/v1/library/{$guide->id}")->assertOk()->json('data.documents.0.url');

        $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="Service manual.pdf"')
            ->assertStreamedContent('%PDF-1.4 service manual');
        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $url))->assertForbidden();
        $this->get(strtok($url, '?'))->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_signed_document_url_stops_working_when_the_reader_can_no_longer_see_the_guide(): void
    {
        Storage::fake('local');
        $guide = $this->guide(LibraryCategory::factory()->create());
        LibraryResource::factory()->document("library/{$guide->id}/manual.pdf")->for($guide, 'article')->create();
        Storage::disk('local')->put("library/{$guide->id}/manual.pdf", '%PDF-1.4');
        Sanctum::actingAs($this->technician);
        $url = $this->getJson("/api/v1/library/{$guide->id}")->assertOk()->json('data.documents.0.url');
        $this->get($url)->assertOk();

        $guide->update(['status' => LibraryOptions::STATUS_DRAFT]);
        $this->get($url)->assertNotFound();

        $guide->update(['status' => LibraryOptions::STATUS_PUBLISHED]);
        $this->technician->update(['status' => 'inactive']);
        $this->get($url)->assertNotFound();
    }

    public function test_documents_that_do_not_open_inline_download_as_attachments(): void
    {
        Storage::fake('local');
        $guide = $this->guide(LibraryCategory::factory()->create());
        $document = LibraryResource::factory()->document("library/{$guide->id}/wiring.docx", 'Wiring diagram.docx')->for($guide, 'article')->create(['mime_type' => 'application/zip']);
        Storage::disk('local')->put($document->file_path, 'docx-bytes');

        $this->get(URL::temporarySignedRoute('api.library.files', now()->addHour(), ['resource' => $document->id, 'user' => $this->technician->id]))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('Content-Disposition', 'attachment; filename="Wiring diagram.docx"');
    }

    public function test_signed_file_route_returns_404_for_links_missing_files_and_deleted_guides(): void
    {
        Storage::fake('local');
        $category = LibraryCategory::factory()->create();
        $link = LibraryResource::factory()->for($this->guide($category), 'article')->create();
        $missing = LibraryResource::factory()->document('library/missing.pdf')->for($this->guide($category), 'article')->create();
        $deletedGuide = $this->guide($category);
        $orphan = LibraryResource::factory()->document("library/{$deletedGuide->id}/manual.pdf")->for($deletedGuide, 'article')->create();
        Storage::disk('local')->put($orphan->file_path, '%PDF-1.4');
        $deletedGuide->delete();
        $signed = fn (LibraryResource $resource): string => URL::temporarySignedRoute('api.library.files', now()->addHour(), ['resource' => $resource->id, 'user' => $this->technician->id]);

        $this->get($signed($link))->assertNotFound();
        $this->get($signed($missing))->assertNotFound();
        $this->get($signed($orphan))->assertNotFound();
    }
}
