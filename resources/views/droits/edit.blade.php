<x-layouts.app titre="Droits par rôle" sous-titre="Cochez les actions autorisées. L'administrateur a toujours tous les droits.">
    <form method="POST" action="{{ route('droits.update') }}">
        @csrf
        @method('PUT')

        <div class="carte overflow-x-auto">
            <table class="tableau">
                <thead class="sticky top-0 bg-stone-50">
                    <tr>
                        <th class="sticky left-0 z-10 min-w-56 bg-stone-50">Module et action</th>
                        @foreach ($roles as $cle => $libelle)
                            <th class="text-center normal-case tracking-normal">{{ $libelle }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groupes as $g => $titreGroupe)
                        @php($mods = collect($modules)->filter(fn ($m) => $m['groupe'] === $g))
                        @continue($mods->isEmpty())
                        <tr class="bg-stone-100/70"><td colspan="{{ count($roles) + 1 }}" class="sticky left-0 py-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $titreGroupe }}</td></tr>
                        @foreach ($mods as $code => $m)
                            @foreach ($m['actions'] as $action => $libelleAction)
                                @php($perm = "$code.$action")
                                <tr class="border-t border-stone-100 hover:bg-stone-50">
                                    <td class="sticky left-0 bg-white">
                                        @if ($loop->first)<span class="font-medium text-stone-900">{{ $m['titre'] }}</span><br>@endif
                                        <span class="text-stone-500">{{ $libelleAction }}</span>
                                    </td>
                                    @foreach ($roles as $role => $libelle)
                                        <td class="text-center">
                                            @if (in_array($perm, $reservees, true))
                                                <span class="text-xs text-stone-400" title="Réservé à l'administrateur">admin</span>
                                            @else
                                                <input type="checkbox" name="droits[{{ $role }}][]" value="{{ $perm }}" @checked(isset($matrice[$role][$perm]))
                                                       class="size-5 rounded border-stone-300 text-ferme-700 focus:ring-ferme-600" aria-label="{{ $libelle }} : {{ $m['titre'] }}, {{ $libelleAction }}">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="sticky bottom-20 mt-4 flex justify-end lg:bottom-4">
            <button type="submit" class="btn-primaire shadow-lg">Enregistrer la matrice</button>
        </div>
    </form>
</x-layouts.app>
