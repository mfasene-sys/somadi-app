<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Roles;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Liste filtrable des utilisateurs (recherche instantanée, filtre rôle et statut). */
class ListeUtilisateurs extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: 'actifs')]
    public string $statut = 'actifs';

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('ACC.voir'), 403);

        $q = User::query()->orderByDesc('actif')->orderBy('nom')->orderBy('prenom');
        if ($this->recherche !== '') {
            $terme = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->recherche).'%';
            $q->where(fn ($w) => $w->where('nom', 'like', $terme)->orWhere('prenom', 'like', $terme)->orWhere('email', 'like', $terme));
        }
        if ($this->role !== '' && Roles::existe($this->role)) {
            $q->where('role', $this->role);
        }
        if ($this->statut === 'actifs') {
            $q->where('actif', 1);
        } elseif ($this->statut === 'inactifs') {
            $q->where(fn ($w) => $w->where('actif', 0)->orWhereNull('actif'));
        }

        return view('livewire.liste-utilisateurs', [
            'utilisateurs' => $q->paginate(20),
            'roles' => Roles::tous(),
        ]);
    }
}
