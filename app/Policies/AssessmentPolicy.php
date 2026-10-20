<?php
namespace App\Policies;
use App\Models\{Assessment, User};
class AssessmentPolicy
{
    public function view(User $user, Assessment $assessment): bool
    {
        return $user->is_active && $user->role === 'teacher' && $user->id === $assessment->owner_id;
    }
    public function update(User $user, Assessment $assessment): bool { return $this->view($user, $assessment); }
}
