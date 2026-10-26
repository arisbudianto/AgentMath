<?php

namespace App\Policies;

use App\Models\{Competency, User};

class CompetencyPolicy
{
    public function view(User $user, Competency $competency): bool
    {
        return $user->is_active && $user->role === 'teacher' && $user->id === $competency->owner_id;
    }
    public function update(User $user, Competency $competency): bool { return $this->view($user, $competency); }
    public function delete(User $user, Competency $competency): bool { return $this->view($user, $competency); }
}
