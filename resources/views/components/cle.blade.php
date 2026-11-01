{{-- Clé anti double envoi (consommée côté serveur par App\Support\Requete). --}}
<input type="hidden" name="_cle" value="{{ old('_cle', (string) \Illuminate\Support\Str::uuid()) }}">
