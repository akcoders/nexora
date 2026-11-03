<?php

namespace Tests\Feature;

use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Models\ServiceMasterOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibrarySettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@nexora.test')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function category(array $overrides = []): array
    {
        return array_replace(['name' => 'Gas charging', 'description' => 'Charging by weight.', 'icon' => 'snowflake', 'color' => 'teal', 'sort_order' => '12'], $overrides);
    }

    public function test_settings_need_the_library_manage_permission(): void
    {
        $manager = tap(User::factory()->create(['status' => 'active']))->assignRole('Manager');
        $category = LibraryCategory::where('slug', 'safety')->firstOrFail();

        $this->actingAs($manager)->get(route('library.settings'))->assertForbidden();
        $this->actingAs($manager)->post(route('library.categories.store'), $this->category())->assertForbidden();
        $this->actingAs($manager)->patch(route('library.categories.toggle', $category))->assertForbidden();
        $this->actingAs($manager)->post(route('library.masters.store'), ['type' => 'library_manufacturer', 'label' => 'Hisense'])->assertForbidden();

        $this->actingAs($this->admin)->get(route('library.settings'))->assertOk()->assertSee('Troubleshooting &amp; error codes', false)->assertSee('Mitsubishi Electric');
    }

    public function test_admin_adds_a_category_with_icon_and_colour(): void
    {
        $this->actingAs($this->admin)->post(route('library.categories.store'), $this->category())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('library.settings'));

        $this->assertDatabaseHas('library_categories', ['name' => 'Gas charging', 'slug' => 'gas-charging', 'icon' => 'snowflake', 'color' => 'teal', 'sort_order' => 12, 'is_active' => true]);
    }

    public function test_a_numeric_category_name_never_gets_a_slug_that_looks_like_an_id(): void
    {
        $this->actingAs($this->admin)->post(route('library.categories.store'), $this->category(['name' => '3']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('library_categories', ['name' => '3', 'slug' => 'category-3']);
    }

    public function test_category_names_must_be_unique_and_icons_come_from_the_picker(): void
    {
        $this->actingAs($this->admin)->post(route('library.categories.store'), $this->category(['name' => 'Safety', 'icon' => 'skull']))
            ->assertSessionHasErrors(['name' => 'A category with this name already exists.', 'icon' => 'Pick one of the icons shown.']);
    }

    public function test_editing_a_category_keeps_its_slug(): void
    {
        $category = LibraryCategory::where('slug', 'training')->firstOrFail();

        $this->actingAs($this->admin)->put(route('library.categories.update', $category), $this->category(['name' => 'Technician training', 'color' => 'purple', 'sort_order' => '']))
            ->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('Technician training', $category->name);
        $this->assertSame('training', $category->slug);
        $this->assertSame('purple', $category->color);
        $this->assertSame(7, $category->sort_order);
    }

    public function test_hiding_a_category_removes_its_tile_from_the_library(): void
    {
        $category = LibraryCategory::where('slug', 'safety')->firstOrFail();

        $this->actingAs($this->admin)->patch(route('library.categories.toggle', $category))->assertRedirect(route('library.settings'));

        $this->assertFalse($category->fresh()->is_active);
        $this->actingAs($this->admin)->get(route('library.index'))->assertOk()->assertDontSee('?category=safety', false);
    }

    public function test_admin_adds_renames_and_deactivates_a_manufacturer(): void
    {
        $this->actingAs($this->admin)->post(route('library.masters.store'), ['type' => 'library_manufacturer', 'label' => 'Hisense'])
            ->assertRedirect(route('library.settings', ['tab' => 'library_manufacturer']));
        $option = ServiceMasterOption::where('type', 'library_manufacturer')->where('code', 'hisense')->firstOrFail();
        LibraryArticle::factory()->create(['manufacturer' => 'hisense', 'title' => 'Hisense remote pairing']);

        $this->actingAs($this->admin)->put(route('library.masters.update', $option), ['label' => 'Hisense India'])->assertSessionHasNoErrors();
        $this->assertSame('Hisense India', $option->fresh()->label);
        $this->assertSame('hisense', $option->fresh()->code);

        $this->actingAs($this->admin)->patch(route('library.masters.toggle', $option))->assertSessionHasNoErrors();
        $this->assertFalse($option->fresh()->active);
        $this->actingAs($this->admin)->get(route('library.index'))->assertDontSee('<option value="hisense"', false);
        $this->actingAs($this->admin)->get(route('library.index', ['q' => 'remote pairing']))->assertSee('Hisense India');
    }

    public function test_duplicate_list_entries_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('library.masters.store'), ['type' => 'library_device_type', 'label' => 'split ac'])
            ->assertSessionHasErrors(['label' => '"split ac" is already in this list.']);
    }

    public function test_masters_of_other_modules_cannot_be_changed_from_library_settings(): void
    {
        $option = ServiceMasterOption::create(['type' => 'nature_of_complaint', 'code' => 'not_cooling', 'label' => 'Not cooling', 'active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)->put(route('library.masters.update', $option), ['label' => 'Changed'])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('library.masters.toggle', $option))->assertNotFound();
        $this->assertSame('Not cooling', $option->fresh()->label);
        $this->assertTrue($option->fresh()->active);
    }
}
