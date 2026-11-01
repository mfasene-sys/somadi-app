<?php

namespace App\Support;

class Roles
{
    /** @return array<string,string> rôle => libellé */
    public static function tous(): array
    {
        return config('somadi.roles');
    }

    public static function canonique(?string $role): string
    {
        $role = (string) $role;

        return config('somadi.role_aliases')[$role] ?? $role;
    }

    public static function libelle(?string $role): string
    {
        $c = self::canonique($role);

        return self::tous()[$c] ?? ($c !== '' ? $c : 'Aucun');
    }

    public static function existe(?string $role): bool
    {
        return array_key_exists((string) $role, self::tous());
    }
}
