<?php
namespace App\Arbac\Rules;

use Amrshah\Arbac\Contracts\AttributeRuleInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class PostOwnerRule implements AttributeRuleInterface
{
    public function supports(string $permission): bool
    {
        return in_array($permission, ['edit post', 'delete post', 'view post'], true);
    }

    public function check(Authenticatable $user, string $permission, array $context = []): bool
    {
        $post = $context['post'] ?? null;
        if (! $post) {
            return false;
        }

        return $post->user_id === $user->getAuthIdentifier();
    }
}
