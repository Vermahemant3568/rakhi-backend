<?php

namespace App\Services\Coach;

class UserContextBlock
{
    public function build($user): string
    {
        $profile = $user->profile;
        $goals = $user->goals->pluck('name')->implode(', ');

        return "
User Name: {$profile->first_name}
Goals: {$goals}
Height: {$profile->height_cm}
Weight: {$profile->weight_kg}

Rakhi should speak like a supportive Indian health coach who already knows this.
";
    }
}