<?php

namespace App\Services\Candidate;

use App\Models\Skill;
use Illuminate\Support\Str;

class SkillResolver
{
    /**
     * Turn a list of skill name strings into skill ids, creating any that do
     * not exist yet (matched by slug so casing/spacing is normalised).
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    public function resolve(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $skill = Skill::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );

            $ids[$skill->id] = $skill->id;
        }

        return array_values($ids);
    }
}
