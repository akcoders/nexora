<?php

use App\Services\LibraryOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 60)->default('book-open');
            $table->string('color', 20)->default('primary');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('library_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_category_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->string('summary', 500)->nullable();
            $table->longText('body')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('device_type', 60)->nullable()->index();
            $table->string('manufacturer', 60)->nullable()->index();
            $table->string('model_numbers', 255)->nullable();
            $table->string('tags', 255)->nullable();
            $table->string('status', 20)->default(LibraryOptions::STATUS_PUBLISHED)->index();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'library_category_id']);
        });

        Schema::create('library_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_article_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('title', 180)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('video_id', 20)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['library_article_id', 'kind']);
        });

        $this->seedPermissions();
        $this->seedMasters();
    }

    public function down(): void
    {
        Schema::dropIfExists('library_resources');
        Schema::dropIfExists('library_articles');
        Schema::dropIfExists('library_categories');
        Permission::where('name', 'like', 'library.%')->delete();
        DB::table('service_master_options')->whereIn('type', array_keys(LibraryOptions::masterGroups()))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedPermissions(): void
    {
        $actions = ['read', 'create', 'update', 'delete', 'manage'];
        foreach ($actions as $action) {
            Permission::findOrCreate("library.{$action}", 'web');
        }

        Role::where('guard_name', 'web')->whereIn('name', ['Super Admin', 'Admin'])->get()->each->givePermissionTo(array_map(fn (string $action) => "library.{$action}", $actions));
        Role::where('guard_name', 'web')->where('name', 'Manager')->first()?->givePermissionTo(['library.read', 'library.create', 'library.update']);
        Role::where('guard_name', 'web')->whereIn('name', ['Technician', 'Viewer'])->get()->each->givePermissionTo('library.read');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedMasters(): void
    {
        $now = now();
        $rows = [];
        foreach (['library_device_type' => LibraryOptions::DEFAULT_DEVICE_TYPES, 'library_manufacturer' => LibraryOptions::DEFAULT_MANUFACTURERS] as $type => $labels) {
            foreach ($labels as $order => $label) {
                $rows[] = ['type' => $type, 'code' => Str::limit(Str::slug($label, '_'), 50, ''), 'label' => $label, 'sort_order' => $order, 'active' => true, 'metadata' => null, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('service_master_options')->insertOrIgnore($rows);

        $categories = [];
        foreach (LibraryOptions::DEFAULT_CATEGORIES as $order => $category) {
            $categories[] = [...$category, 'slug' => Str::slug($category['name']), 'sort_order' => $order, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('library_categories')->insertOrIgnore($categories);
    }
};
