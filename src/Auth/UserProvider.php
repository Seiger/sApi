<?php namespace Seiger\sApi\Auth;

use EvolutionCMS\Models\User;

class UserProvider
{
    public function findByUsername(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        return User::query()->where('username', $username)->first();
    }
}
