@php
    use App\Support\Format;
    $carte = function ($n, string $role, bool $afficher = true) {
        if (! $afficher) {
            return '<div aria-hidden="true"></div>';
        }
        if (! $n) {
            return '<div class="rounded-lg border border-dashed border-stone-300 px-3 py-2 text-xs text-stone-400">'.e($role).' inconnu'.(str_ends_with($role, 'mère') || $role === 'Mère' ? 'e' : '').'</div>';
        }
        $photo = $n->photo ? '<img src="'.route('ovins.medias.show', $n->photo).'" alt="" class="size-10 shrink-0 rounded-full object-cover">' : '<span class="grid size-10 shrink-0 place-items-center rounded-full '.($n->sexe === 'F' ? 'bg-rose-50 text-rose-700' : 'bg-sky-50 text-sky-700').'">'.($n->sexe === 'F' ? '♀' : '♂').'</span>';
        $sorti = $n->statut !== 'en_ferme' ? ' <span class="text-stone-400">('.e(\App\Domaine\Ovins\Animaux::statut($n->statut)).')</span>' : '';

        return '<a href="'.route('ovins.animaux.show', $n->id_animal).'" class="flex items-center gap-2 rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm hover:border-ferme-300">'.$photo
            .'<span class="min-w-0"><span class="block text-[11px] uppercase tracking-wide text-stone-400">'.e($role).'</span><span class="block truncate font-medium">'.e($n->code_animal ?: '#'.$n->id_animal).$sorti.'</span>'
            .'<span class="block truncate text-xs text-stone-500">'.e(trim(($n->nom_animal ?? '').' '.Format::date($n->date_naissance))).'</span></span></a>';
    };
    $r = $racine;
@endphp
<x-layouts.app :titre="'Généalogie de '.($a->code_animal ?: '#'.$a->id_animal)" sous-titre="Trois générations d’ascendants, et la descendance directe">
    <x-slot:actions>
        <a href="{{ route('ovins.animaux.show', $a->id_animal) }}" class="btn-secondaire print:hidden">Fiche</a>
        <button type="button" onclick="window.print()" class="btn-secondaire print:hidden">Imprimer</button>
    </x-slot:actions>

    <div class="overflow-x-auto">
        <div class="grid min-w-[52rem] auto-rows-[minmax(3.25rem,auto)] grid-cols-4 items-center gap-x-3 gap-y-2">
            <div class="row-span-8 self-center">{!! $carte($r, 'Animal') !!}</div>
            @foreach ([['mere', 'Mère'], ['pere', 'Père']] as [$k, $lib])
                @php($p = $r->$k)
                <div class="col-start-2 row-span-4">{!! $carte($p, $lib) !!}</div>
                @foreach ([['mere', 'Grand-mère'], ['pere', 'Grand-père']] as [$k2, $lib2])
                    @php($gp = $p?->$k2)
                    <div class="col-start-3 row-span-2">{!! $carte($gp, $lib2, (bool) $p) !!}</div>
                    <div class="col-start-4">{!! $carte($gp?->mere, 'Arrière-grand-mère', (bool) $gp) !!}</div>
                    <div class="col-start-4">{!! $carte($gp?->pere, 'Arrière-grand-père', (bool) $gp) !!}</div>
                @endforeach
            @endforeach
        </div>
    </div>

    <section class="mt-8">
        <h2 class="mb-3 font-semibold text-stone-900">Descendance directe <span class="font-normal text-stone-500">· {{ $enfants->count() }}</span></h2>
        @if ($enfants->isEmpty())
            <p class="text-sm text-stone-500">Aucun descendant enregistré.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($enfants as $e)
                    <a href="{{ route('ovins.animaux.arbre', $e->id_animal) }}" class="carte px-3 py-2 text-sm hover:border-ferme-200">{{ $e->sexe === 'F' ? '♀' : '♂' }} {{ $e->code_animal ?: '#'.$e->id_animal }} <span class="text-xs text-stone-500">{{ Format::date($e->date_naissance) }}{{ $e->statut !== 'en_ferme' ? ' · '.\App\Domaine\Ovins\Animaux::statut($e->statut) : '' }}</span></a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
