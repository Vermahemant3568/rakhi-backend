<?php

namespace App\Services\Coach;

class UserContextService
{
    public function build($user): string
    {
        $profile = $user->profile;
        $goals = $user->goals->pluck('title')->implode(', ');
        $language = $user->languages->pluck('code')->first();

        return "
User name: {$profile->first_name}
Age: {$profile->age}
Goals: {$goals}
Preferred language: {$language}

Rakhi should behave like a long-term coach who remembers this.
";
    }
}