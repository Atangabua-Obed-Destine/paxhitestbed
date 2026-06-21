<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Web\Announcement;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $language;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default language
        $this->language = Language::create([
            'name' => 'English',
            'code' => 'en',
            'direction' => 0,
            'default' => 1,
            'status' => 1,
        ]);

        // Create permissions
        Permission::create(['name' => 'announcement-view', 'guard_name' => 'web']);
        Permission::create(['name' => 'announcement-create', 'guard_name' => 'web']);
        Permission::create(['name' => 'announcement-edit', 'guard_name' => 'web']);
        Permission::create(['name' => 'announcement-delete', 'guard_name' => 'web']);

        // Create admin role with permissions
        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['announcement-view', 'announcement-create', 'announcement-edit', 'announcement-delete']);

        // Create admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole($role);
    }

    /** @test */
    public function admin_can_view_announcements_index()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.announcement.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.web.announcement.index');
    }

    /** @test */
    public function admin_can_view_create_announcement_form()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.announcement.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.web.announcement.create');
        $response->assertViewHas('languages');
    }

    /** @test */
    public function admin_can_create_announcement()
    {
        $this->actingAs($this->admin);

        $data = [
            'language_id' => $this->language->id,
            'message' => 'Test announcement message',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addDays(30)->toDateString(),
            'status' => 1,
        ];

        $response = $this->post(route('admin.announcement.store'), $data);

        $response->assertRedirect(route('admin.announcement.index'));
        $this->assertDatabaseHas('announcements', [
            'message' => 'Test announcement message',
            'status' => 1,
        ]);
    }

    /** @test */
    public function admin_can_edit_announcement()
    {
        $this->actingAs($this->admin);

        $announcement = Announcement::create([
            'language_id' => $this->language->id,
            'message' => 'Original message',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => null,
            'status' => 1,
        ]);

        $response = $this->get(route('admin.announcement.edit', $announcement->id));

        $response->assertStatus(200);
        $response->assertViewIs('admin.web.announcement.edit');
        $response->assertViewHas('announcement', $announcement);
    }

    /** @test */
    public function admin_can_update_announcement()
    {
        $this->actingAs($this->admin);

        $announcement = Announcement::create([
            'language_id' => $this->language->id,
            'message' => 'Original message',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => null,
            'status' => 1,
        ]);

        $updatedData = [
            'language_id' => $this->language->id,
            'message' => 'Updated message',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addDays(15)->toDateString(),
            'status' => 0,
        ];

        $response = $this->put(route('admin.announcement.update', $announcement->id), $updatedData);

        $response->assertRedirect(route('admin.announcement.index'));
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'message' => 'Updated message',
            'status' => 0,
        ]);
    }

    /** @test */
    public function admin_can_delete_announcement()
    {
        $this->actingAs($this->admin);

        $announcement = Announcement::create([
            'language_id' => $this->language->id,
            'message' => 'To be deleted',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => null,
            'status' => 1,
        ]);

        $response = $this->delete(route('admin.announcement.destroy', $announcement->id));

        $response->assertRedirect(route('admin.announcement.index'));
        $this->assertDatabaseMissing('announcements', [
            'id' => $announcement->id,
        ]);
    }

    /** @test */
    public function active_announcement_displays_on_homepage()
    {
        Announcement::create([
            'language_id' => $this->language->id,
            'message' => 'Welcome announcement',
            'start_date' => Carbon::now()->subDays(1)->toDateString(),
            'end_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Welcome announcement', false);
    }

    /** @test */
    public function inactive_announcement_does_not_display()
    {
        Announcement::create([
            'language_id' => $this->language->id,
            'message' => 'Inactive announcement',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => null,
            'status' => 0,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertDontSee('Inactive announcement');
    }

    /** @test */
    public function expired_announcement_does_not_display()
    {
        Announcement::create([
            'language_id' => $this->language->id,
            'message' => 'Expired announcement',
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->subDays(2)->toDateString(),
            'status' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertDontSee('Expired announcement');
    }

    /** @test */
    public function future_announcement_does_not_display()
    {
        Announcement::create([
            'language_id' => $this->language->id,
            'message' => 'Future announcement',
            'start_date' => Carbon::now()->addDays(5)->toDateString(),
            'end_date' => Carbon::now()->addDays(30)->toDateString(),
            'status' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertDontSee('Future announcement');
    }

    /** @test */
    public function announcement_validation_requires_message()
    {
        $this->actingAs($this->admin);

        $data = [
            'language_id' => $this->language->id,
            'message' => '', // Empty message
            'status' => 1,
        ];

        $response = $this->post(route('admin.announcement.store'), $data);

        $response->assertSessionHasErrors('message');
    }

    /** @test */
    public function announcement_end_date_must_be_after_start_date()
    {
        $this->actingAs($this->admin);

        $data = [
            'language_id' => $this->language->id,
            'message' => 'Test message',
            'start_date' => Carbon::now()->addDays(10)->toDateString(),
            'end_date' => Carbon::now()->toDateString(), // End before start
            'status' => 1,
        ];

        $response = $this->post(route('admin.announcement.store'), $data);

        $response->assertSessionHasErrors('end_date');
    }

    /** @test */
    public function guest_cannot_access_admin_announcement_pages()
    {
        $response = $this->get(route('admin.announcement.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.announcement.create'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function user_without_permission_cannot_create_announcement()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.announcement.create'));

        $response->assertStatus(403);
    }
}
