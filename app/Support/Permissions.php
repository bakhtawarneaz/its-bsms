<?php

namespace App\Support;

use App\Support\Database\StoredProcedure;

/**
 * Legacy per-account flags (EditUsers, MaskApproval, GWUserCreate) read live from the users row.
 * Bound as scoped, so the database is hit once per request.
 */
class Permissions
{
    public const EDIT_USERS = 'EditUsers';
    public const MASK_APPROVAL = 'MaskApproval';
    public const GW_USER_CREATE = 'GWUserCreate';

    private ?array $flags = null;

    public function has(string $flag): bool
    {
        $user = session('auth_user');

        if (! $user) {
            return false;
        }

        $this->flags ??= (array) (StoredProcedure::first('usp_users_permissions', ['user_id' => (int) $user['id']]) ?? []);

        return (int) ($this->flags[$flag] ?? 0) === 1;
    }
}