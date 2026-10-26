<?php
namespace Tests\Feature;

use App\Models\{User, Assessment, Classroom};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * M15: Generalized ownership-boundary regression test.
 *
 * Existing tests assert cross-owner 403 for specific routes one at a time.
 * This test instead walks the actual route table and asserts the invariant
 * for EVERY assessment-scoped and classroom-scoped GET route automatically,
 * so a future route added without an owner check fails CI without anyone
 * having to remember to write a matching test by hand.
 *
 * It only probes safe, idempotent GET requests. It does not replace the
 * existing behavioural tests for POST/PATCH mutation routes.
 */
class AuthorizationCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'teacher'): User
    {
        $u = new User(['name' => 'Test', 'email' => uniqid().'@example.test', 'password' => 'Testing123456']);
        $u->role = $role; $u->is_active = true; $u->save();
        return $u;
    }

    /** Every GET route scoped by {assessment} or {classroom}, outside the student/admin surfaces (covered elsewhere). */
    private function ownershipScopedGetRoutes(): array
    {
        $found = [];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods(), true)) continue;
            $uri = $route->uri();
            if (!str_contains($uri, '{assessment}') && !str_contains($uri, '{classroom}')) continue;
            if (str_starts_with($uri, 'student/') || str_starts_with($uri, 'admin/')) continue;
            $found[] = $uri;
        }
        $this->assertNotEmpty($found, 'Route sweep found nothing — route list or filter is broken, not a real pass.');
        return $found;
    }

    /** Build a concrete URL from a route URI, pointing {assessment}/{classroom} at the foreign resource and any other numeric param at a harmless placeholder. */
    private function urlFor(string $uri, int $foreignAssessmentId, int $foreignClassroomId): string
    {
        $replacements = ['{assessment}' => $foreignAssessmentId, '{classroom}' => $foreignClassroomId];
        $url = strtr($uri, $replacements);
        $url = preg_replace('/\{[^}]+\}/', '1', $url);
        return '/'.$url;
    }

    public function test_every_assessment_or_classroom_scoped_get_route_denies_a_foreign_teacher(): void
    {
        $owner = $this->user();
        $intruder = $this->user();
        $foreignAssessment = $owner->assessments()->create(['title' => 'Foreign assessment']);
        $foreignClassroom = $owner->classrooms()->create(['name' => 'Foreign classroom', 'grade' => 4, 'school_year' => '2026/2027']);

        $routes = $this->ownershipScopedGetRoutes();
        $this->actingAs($intruder);

        $failures = [];
        foreach ($routes as $uri) {
            $url = $this->urlFor($uri, $foreignAssessment->id, $foreignClassroom->id);
            $status = $this->get($url)->getStatusCode();
            if ($status !== 403) {
                $failures[] = "$uri -> HTTP $status (expected 403)";
            }
        }

        $this->assertSame([], $failures, "Routes leaking foreign-owner data (must be 403, authorize-before-lookup):\n".implode("\n", $failures));
    }

    public function test_every_assessment_or_classroom_scoped_get_route_denies_a_student(): void
    {
        $owner = $this->user();
        $student = $this->user('student');
        $foreignAssessment = $owner->assessments()->create(['title' => 'Foreign assessment 2']);
        $foreignClassroom = $owner->classrooms()->create(['name' => 'Foreign classroom 2', 'grade' => 4, 'school_year' => '2026/2027']);

        $routes = $this->ownershipScopedGetRoutes();
        $this->actingAs($student);

        $failures = [];
        foreach ($routes as $uri) {
            $url = $this->urlFor($uri, $foreignAssessment->id, $foreignClassroom->id);
            $status = $this->get($url)->getStatusCode();
            if ($status !== 403) {
                $failures[] = "$uri -> HTTP $status (expected 403)";
            }
        }

        $this->assertSame([], $failures, "Teacher-only routes reachable by a student account:\n".implode("\n", $failures));
    }

    public function test_route_sweep_itself_stays_meaningful(): void
    {
        // Guards against the sweep silently shrinking to zero if routing conventions change
        // (e.g. parameter renamed from {assessment} to something else) without anyone noticing.
        $routes = $this->ownershipScopedGetRoutes();
        $this->assertGreaterThanOrEqual(25, count($routes), 'Fewer ownership-scoped GET routes than expected; verify the filter still matches current routing conventions before trusting this test suite.');
    }
}
