<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class PublicHomeTest extends TestCase {
    use RefreshDatabase;
    public function test_public_front_page_is_not_login_and_does_not_open_private_class_content(): void {
        Http::preventStrayRequests(); config(['ai.api_key'=>'PRIVATE_HOME_TEST_SECRET']);
        $this->get('/')->assertOk()->assertSee('Angka kecil,')->assertSee('Coba latihan seru')->assertDontSee('name="password"',false)->assertDontSee('PRIVATE_HOME_TEST_SECRET');
        foreach(['/question-bank','/student','/assessments'] as $url) $this->get($url)->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Masuk ke AgentMath')->assertSee('name="password"',false)->assertSee('Kembali ke halaman depan'); Http::assertNothingSent();
    }
    public function test_existing_account_can_visit_public_home_without_losing_session_or_role(): void {
        $u=new User(['name'=>'Student','email'=>'home-student@example.test','password'=>'TestingPassword123']);$u->role='student';$u->is_active=true;$u->save();
        $this->actingAs($u)->get('/')->assertOk()->assertSee('Ruang saya')->assertDontSee('name="password"',false);
        $this->assertAuthenticatedAs($u); $this->get('/login')->assertRedirect('/dashboard'); $this->get('/question-bank')->assertForbidden();
    }
}
