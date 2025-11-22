<?php

namespace Amrshah\Arbac\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AttributeRuleInterface
{
    /**
     * Whether this rule applies to the given permission name.
     *
     * Example: a PostOwnerRule might return true for "edit post" and "delete post".
     */
    public function supports(string $permission): bool;

    /**
     * Evaluate the rule for the given user, permission and context.
     *
     * @param  array  $context  // arbitrary attributes, e.g. ['post' => $post, 'tenant_id' => 5]
     * @return bool // return true to grant access (false to not grant)
     */
    public function check(Authenticatable $user, string $permission, array $context = []): bool;
}
