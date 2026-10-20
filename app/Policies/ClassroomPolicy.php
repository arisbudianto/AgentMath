<?php

namespace App\Policies;

use App\Models\{Classroom, User};

class ClassroomPolicy
{
    public function view(User $user, Classroom $classroom): bool
    {
        return $user->is_active && $user->role === 'teacher' && $user->id === $classroom->owner_id;
    }
    public function update(User $user, Classroom $classroom): bool { return $this->view($user, $classroom); }
}
