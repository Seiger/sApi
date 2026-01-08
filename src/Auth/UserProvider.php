<?php namespace Seiger\sApi\Auth;

use EvolutionCMS\Models\User;

/**
 * Class UserProvider
 *
 * Provides user lookup and password verification logic
 * compatible with Evolution CMS authentication mechanisms.
 *
 * This provider supports multiple legacy hash types used by Evolution CMS:
 * - phpass
 * - md5
 * - v1
 */
class UserProvider
{
    /**
     * Find a user by username.
     *
     * Trims input value and returns null if the username is empty
     * or if no matching user is found.
     *
     * @param string $username
     * @return User|null
     */
    public function findByUsername(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        return User::query()->where('username', $username)->first();
    }

    /**
     * Verify a user's password against the stored hash.
     *
     * The hash type is automatically detected using Evolution CMS manager API
     * and validated using the corresponding legacy authentication function.
     *
     * Supported hash types:
     * - phpass
     * - md5
     * - v1
     *
     * @param User   $user      User model instance
     * @param string $password Plain text password
     * @return bool  True if password is valid, false otherwise
     */
    public function checkPassword(User $user, string $password): bool
    {
        $password = trim($password);
        if ($password === '') {
            return false;
        }

        $hash = (string) ($user->password ?? '');
        if ($hash === '') {
            return false;
        }

        try {
            $managerApi = evo()->getManagerApi();
            if (!is_object($managerApi) || !method_exists($managerApi, 'getHashType')) {
                return false;
            }

            $hashType = (string) $managerApi->getHashType($hash);
        } catch (\Throwable) {
            // Any failure in hash detection should be treated as invalid credentials
            return false;
        }

        return match ($hashType) {
            'phpass' => function_exists('login') && (bool)\login((string)$user->username, $password, $hash),
            'md5' => function_exists('loginMD5') && (bool)\loginMD5($user->getKey(), $password, $hash, (string)$user->username),
            'v1' => function_exists('loginV1') && (bool)\loginV1($user->getKey(), $password, $hash, (string)$user->username),
            default => false,
        };
    }
}
