<?php

namespace App\Domain\AboutMe;

/**
 * Liefert die Inhalte der Seite „Über mich“ aus lib/profil.php.
 * Wird von der Seite selbst und vom Portfolio-Assistenten genutzt (eine Quelle, keine Doppelpflege).
 */
class AboutProfile
{
    private ?array $data = null;

    /**
     * @return array{skill_groups: array, skill_projects: array, strengths: list<array>, stations: list<array>, hobbies: list<array>}
     */
    public function all(): array
    {
        return $this->data ??= require __DIR__.'/lib/profil.php';
    }
}
