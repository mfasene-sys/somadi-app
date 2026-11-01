<x-layouts.app :titre="'Clôture de l’exercice '.$annee" sous-titre="Vérifiez l’aperçu : après validation, l’exercice est figé et ne pourra plus être modifié">
    @if ($perime)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Des données ont changé depuis la préparation de cet aperçu.
            <form method="POST" action="{{ route('exercices.apercu', $annee) }}" class="inline">@csrf<button class="font-semibold underline">Recalculer l’aperçu</button></form></div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="min-w-0">@include('commercial.exercices._bilan', ['b' => $r['bilan']])</div>
        <div class="space-y-6">
            @include('commercial.exercices._controles', ['c' => $r['controles']])
            @unless ($perime || $r['controles']['bloque'])
                <form method="POST" action="{{ route('exercices.cloturer', $annee) }}" class="carte space-y-3 p-4" onsubmit="return confirm('Clôturer définitivement l’exercice {{ $annee }} ?')">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $jeton['revision'] }}">
                    <input type="hidden" name="empreinte" value="{{ $jeton['empreinte'] }}">
                    <x-champ name="commentaire" label="Commentaire de clôture" type="textarea" required minlength="10" maxlength="4000" aide="Au moins 10 caractères ; il figure dans l’archive et le PDF."/>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" required class="mt-0.5 size-5">
                        <span>Je comprends que les ventes et opérations jusqu’au 31/12/{{ $annee }} ne pourront plus être modifiées.</span></label>
                    @error('confirmation')<p class="erreur">{{ $message }}</p>@enderror
                    <button class="btn-primaire w-full"><x-icone nom="lock" class="size-4"/> Clôturer {{ $annee }}</button>
                </form>
            @endunless
            <a href="{{ route('exercices.index', ['annee' => $annee]) }}" class="btn-secondaire w-full">Revenir sans clôturer</a>
        </div>
    </div>
</x-layouts.app>
