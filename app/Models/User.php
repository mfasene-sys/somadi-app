<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

/**
 * Utilisateur, sur la table existante `utilisateurs` (structure inchangée).
 *
 * @property int $id
 * @property string|null $nom
 * @property string|null $prenom
 * @property string|null $email
 * @property string|null $mot_de_passe
 * @property string $role
 * @property int|null $actif
 */
class User extends Model implements Authenticatable, AuthorizableContract
{
    use AuthenticatableTrait, Authorizable;

    protected $table = 'utilisateurs';

    public $timestamps = false;

    protected $fillable = ['nom', 'prenom', 'email', 'role', 'actif'];

    protected $hidden = ['mot_de_passe'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }

    /** La table n'a pas de colonne « se souvenir de moi » : fonction désactivée. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        // Volontairement vide : pas de colonne remember_token dans utilisateurs.
    }

    /** Rôle canonique (alias de l'ancien code rabattus, EC03). */
    public function roleCanonique(): string
    {
        return Roles::canonique($this->role);
    }

    public function estAdmin(): bool
    {
        return $this->roleCanonique() === 'admin';
    }

    public function nomComplet(): string
    {
        return trim(($this->prenom ?? '').' '.($this->nom ?? '')) ?: (string) $this->email;
    }

    public function initiales(): string
    {
        $p = mb_substr((string) $this->prenom, 0, 1);
        $n = mb_substr((string) $this->nom, 0, 1);

        return mb_strtoupper(($p.$n) ?: mb_substr((string) $this->email, 0, 1));
    }

    /** Définit le mot de passe avec password_hash(), lisible par l'ancienne application. */
    public function definirMotDePasse(string $clair): void
    {
        $this->mot_de_passe = password_hash($clair, PASSWORD_BCRYPT, ['cost' => (int) config('hashing.bcrypt.rounds', 10)]);
    }

    public function verifierMotDePasse(string $clair): bool
    {
        return is_string($this->mot_de_passe) && $this->mot_de_passe !== '' && password_verify($clair, $this->mot_de_passe);
    }
}
