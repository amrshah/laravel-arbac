<?php

use Amrshah\Arbac\ArbacManager;
use Amrshah\Arbac\Rules\PostOwnerRule;

uses(Amrshah\Arbac\Tests\TestCase::class);

it('grants permission via ABAC rule when RBAC denies', function () {
    $user = App\Models\User::factory()->create();

    // Simulate a Post model (could be a real model from your app)
    $post = (object) ['user_id' => $user->id];

    // Make sure user does NOT have RBAC permission
    expect($user->can('post.edit'))->toBeFalse();

    $manager = app(ArbacManager::class);
    $manager->registerAttributeRule(PostOwnerRule::class);

    $allowed = $manager->check($user, 'post.edit', ['post' => $post]);

    expect($allowed)->toBeTrue();
});
