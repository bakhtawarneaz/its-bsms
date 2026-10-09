<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class AuthRepository
{
    /**
     * Verify credentials for a site and return the login result row.
     * The row always contains "login_status"; user fields are present only when it is "OK".
     */
    public function attempt(string $login, string $passwordHash, string $siteName): ?object
    {
        return StoredProcedure::first('usp_auth_login', [
            'login'         => $login,
            'password_hash' => $passwordHash,
            'site_name'     => $siteName,
        ]);
    }

        /**
     * Block an active user after too many failed login attempts.
     * Returns true when a user was actually blocked.
     */
    public function block(string $login, string $siteName): bool
    {
        $result = StoredProcedure::first('usp_auth_block_user', [
            'login'     => $login,
            'site_name' => $siteName,
        ]);

        return (int) ($result->blocked ?? 0) > 0;
    }

        /**
     * Save the contact details a user confirms on first login.
     * Returns true when the record was updated.
     */
    public function updateContact(int $userId, string $fullname, string $mobiles, string $emails): bool
    {
        $result = StoredProcedure::first('usp_auth_update_contact', [
            'user_id'  => $userId,
            'fullname' => $fullname,
            'mobiles'  => $mobiles,
            'emails'   => $emails,
        ]);

        return (int) ($result->updated ?? 0) > 0;
    }
    
}