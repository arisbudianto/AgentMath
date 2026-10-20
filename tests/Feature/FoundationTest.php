<?php
namespace Tests\Feature;
use App\Models\{User, Assessment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, RateLimiter};
use Tests\TestCase;
class FoundationTest extends TestCase
{
    use RefreshDatabase;
    private function user(string $role = 'teacher', bool $active = true): User
    {
        $user = new User(['name' => 'Test User', 'email' => uniqid().'@example.test', 'password' => 'Testing123456']);
        $user->role = $role; $user->is_active = $active; $user->save(); return $user;
    }
    public function test_guest_and_health(): void
    {
        $this->get('/health')->assertOk()->assertJson(['app' => 'AgentMath']);
        $this->get('/assessments')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Masuk ke AgentMath');
        $this->get('/register')->assertNotFound();
    }
    public function test_valid_login_and_logout(): void
    {
        $u = $this->user();
        $this->post('/login', ['email' => $u->email, 'password' => 'Testing123456'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->get('/dashboard')->assertOk();
        $this->post('/logout')->assertRedirect('/login'); $this->assertGuest();
    }
    public function test_invalid_and_inactive_login_are_rejected(): void
    {
        $u = $this->user(active: false);
        $this->post('/login', ['email' => $u->email, 'password' => 'Testing123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $u->is_active = true; $u->save();
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
    public function test_login_is_rate_limited(): void
    {
        $u = $this->user();
        $key = 'login:'.hash('sha256', strtolower($u->email).'|127.0.0.1');
        for ($i=0; $i<5; $i++) RateLimiter::hit($key, 60);
        $this->post('/login', ['email' => $u->email, 'password' => 'Testing123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
    public function test_owner_is_assigned_by_server_and_cross_owner_access_is_denied(): void
    {
        $a = $this->user(); $b = $this->user();
        $foreign = $b->assessments()->create(['title' => 'PRIVATE FOREIGN TITLE']);
        $this->actingAs($a)->post('/assessments', ['title' => 'My math', 'owner_id' => $b->id])->assertRedirect();
        $own = Assessment::where('title', 'My math')->firstOrFail();
        $this->assertSame($a->id, $own->owner_id);
        $this->get('/assessments')->assertOk()->assertSee('My math')->assertDontSee('PRIVATE FOREIGN TITLE');
        $this->get('/assessments/'.$foreign->id)->assertForbidden();
        $this->patch('/assessments/'.$foreign->id, ['title' => 'Hacked'])->assertForbidden();
        $this->get('/assessments/'.$own->id)->assertOk();
        $this->patch('/assessments/'.$own->id, ['title' => 'Updated', 'owner_id' => $b->id])->assertRedirect();
        $this->assertDatabaseHas('assessments', ['id' => $own->id, 'owner_id' => $a->id, 'title' => 'Updated']);
    }
    public function test_teacher_cannot_manage_users_or_ai(): void
    {
        $this->actingAs($this->user())->get('/admin/users')->assertForbidden();
        $this->get('/admin/ai')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
    }
    public function test_admin_can_create_accounts_without_receiving_assessment_access(): void
    {
        $admin = $this->user('admin'); $teacher = $this->user();
        $assessment = $teacher->assessments()->create(['title' => 'Private']);
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->post('/admin/users', ['name' => 'Teacher', 'email' => 'teacher@example.test', 'role' => 'teacher', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertRedirect();
        $created = User::where('email', 'teacher@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('NewPassword123', $created->password));
        $this->assertSame('teacher', $created->role);
        $this->get('/assessments/'.$assessment->id)->assertForbidden();
        $this->patch('/assessments/'.$assessment->id, ['title' => 'Changed'])->assertForbidden();
    }
    public function test_disabled_session_loses_access(): void
    {
        $u = $this->user(); $this->actingAs($u);
        $u->is_active = false; $u->save();
        $this->get('/dashboard')->assertRedirect('/login'); $this->assertGuest();
    }
    public function test_ai_key_is_never_rendered_and_no_requests_are_made(): void
    {
        config(['ai.api_key' => 'secret-test-value', 'ai.model' => 'test-model']);
        $this->actingAs($this->user('admin'))->get('/admin/ai')->assertOk()->assertSee('Tersedia')->assertDontSee('secret-test-value');
    }
    public function test_admin_cannot_disable_self_but_can_disable_teacher(): void
    {
        $admin = $this->user('admin'); $teacher = $this->user();
        $this->actingAs($admin)->patch('/admin/users/'.$admin->id.'/disable')->assertStatus(422);
        $this->patch('/admin/users/'.$teacher->id.'/disable')->assertRedirect();
        $this->assertFalse($teacher->fresh()->is_active);
    }
    public function test_password_and_role_validation(): void
    {
        $this->actingAs($this->user('admin'))->post('/admin/users', ['name'=>'Test', 'email'=>'valid@example.test', 'role'=>'superadmin', 'password'=>'123', 'password_confirmation'=>'123'])->assertSessionHasErrors(['role', 'password']);
    }
}
