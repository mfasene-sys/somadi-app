<?php

namespace App\Domaine\Organisation;

use App\Domaine\Ventes;
use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Montant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Marketing WhatsApp assisté (MKT, RG-MKT-001 à 011). Aucun envoi automatique : l'utilisateur envoie dans WhatsApp. */
class Marketing
{
    public const VARIABLES = ['{client_nom}', '{client_tel}', '{date_vente}', '{espece}', '{montant_total}', '{id_vente}'];

    public const ACTIONS = ['OPEN_WA' => ['WhatsApp ouvert', 'info'], 'MARQUE_ENVOYE' => ['Déclaré envoyé', 'ok'], 'IGNORE' => ['Ignoré', 'neutre']];

    /** RG-MKT-003 : +221 suivi des 9 chiffres locaux ; toute autre forme est rejetée (null). */
    public static function normaliser(?string $tel): ?string
    {
        $c = preg_replace('/\D+/', '', (string) $tel);
        if (str_starts_with($c, '00221')) {
            $c = substr($c, 2);
        }
        if (strlen($c) === 9) {
            return '+221'.$c;
        }
        if (strlen($c) === 12 && str_starts_with($c, '221')) {
            return '+'.$c;
        }

        return null;
    }

    // ----- Modèles

    public static function enregistrerModele(?int $id, array $d, ?UploadedFile $image = null): int
    {
        $v = ['titre' => trim((string) $d['titre']), 'message' => trim((string) $d['message']), 'is_actif' => empty($d['is_actif']) ? 0 : 1];
        if ($v['titre'] === '' || $v['message'] === '') {
            throw new RegleMetier('Le titre et le message sont obligatoires.');
        }
        if ($image) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($image->getRealPath()) ?: '';
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? throw new RegleMetier('Image JPEG, PNG ou WebP uniquement.');
            if ($image->getSize() > 2 * 1024 * 1024) {
                throw new RegleMetier('Image de 2 Mo au plus.');
            }
            $nom = 'mkt_'.now()->format('Ymd_His').'_'.Str::lower(Str::random(6)).'.'.$ext;
            $image->move(storage_path('app/marketing'), $nom);
            $v['image_path'] = 'app:'.$nom;
        }
        if ($id) {
            $avant = DB::table('modMkt_templates')->where('id', $id)->first() ?? throw new RegleMetier('Modèle introuvable.');
            DB::table('modMkt_templates')->where('id', $id)->update($v);
            Journal::enregistrer('mkt_modele.modification', 'MKT', 'modele', $id, (array) $avant, $v);

            return $id;
        }
        $id = DB::table('modMkt_templates')->insertGetId($v);
        Journal::enregistrer('mkt_modele.creation', 'MKT', 'modele', $id, apres: $v);

        return $id;
    }

    public static function cheminImage(object $t): ?string
    {
        $p = (string) $t->image_path;
        if ($p === '') {
            return null;
        }
        if (str_starts_with($p, 'app:')) {
            $f = storage_path('app/marketing/'.basename(substr($p, 4)));

            return is_file($f) ? $f : null;
        }
        $base = realpath((string) config('somadi.ovins.medias.racine_ancienne'));
        $f = $base ? realpath($base.'/'.ltrim($p, '/')) : false;

        return $f && str_starts_with($f, $base.DIRECTORY_SEPARATOR) && is_file($f) ? $f : null;
    }

    // ----- Campagnes

    public static function creerCampagne(string $titre, int $modele): int
    {
        $titre = trim($titre);
        if ($titre === '' || mb_strlen($titre) > 150) {
            throw new RegleMetier('Le titre de la campagne est obligatoire (150 caractères).');
        }
        if (! DB::table('modMkt_templates')->where('id', $modele)->where('is_actif', 1)->exists()) {
            throw new RegleMetier('Choisissez un modèle actif.');
        }
        $id = DB::table('modMkt_campagnes')->insertGetId(['titre' => $titre, 'id_template' => $modele, 'statut' => 'brouillon']);
        Journal::enregistrer('mkt_campagne.creation', 'MKT', 'campagne', $id, apres: compact('titre', 'modele'));

        return $id;
    }

    public static function changerStatut(int $id, string $statut): void
    {
        if (! in_array($statut, ['brouillon', 'en_cours', 'terminee'], true)) {
            throw new RegleMetier('Statut invalide.');
        }
        DB::table('modMkt_campagnes')->where('id', $id)->update(['statut' => $statut]);
    }

    /**
     * RG-MKT-002, 004 : clients issus des ventes filtrées, regroupés par téléphone normalisé (dernière vente retenue).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function clients(array $f, int $campagne)
    {
        $ventes = DB::table('modVente_Ventes')
            ->when($f['du'] ?? null, fn ($q, $v) => $q->where('date_vente', '>=', $v))
            ->when($f['au'] ?? null, fn ($q, $v) => $q->where('date_vente', '<=', $v))
            ->when($f['produit'] ?? null, fn ($q, $v) => $q->where('espece', $v))
            ->when(($f['montant_min'] ?? '') !== '' ? Montant::versBase(Montant::saisie($f['montant_min'], 'Le montant')) : null, fn ($q, $v) => $q->where('montant_total', '>=', $v))
            ->orderByDesc('date_vente')->orderByDesc('id')->limit(20000)->get(['id', 'client_nom', 'client_tel', 'date_vente', 'espece', 'montant_total']);
        $noire = (($f['exclure'] ?? '1') === '1') ? DB::table('modMkt_blacklist')->pluck('client_tel_norm')->flip() : collect();
        $etats = DB::table('modMkt_logs')->where('id_campagne', $campagne)->orderBy('id')->get()->groupBy('client_tel_norm');
        $out = [];
        foreach ($ventes as $v) {
            $n = self::normaliser($v->client_tel);
            if (! $n || isset($out[$n]) || $noire->has($n)) {
                continue;
            }
            $log = $etats->get($n);
            $v->tel_norm = $n;
            $v->nb_achats = 0;
            $v->etat = $log ? self::etat($log) : null;
            $out[$n] = $v;
        }
        foreach ($ventes as $v) {
            $n = self::normaliser($v->client_tel);
            if ($n && isset($out[$n])) {
                $out[$n]->nb_achats++;
            }
        }
        $liste = collect(array_values($out));
        if (($f['etat'] ?? '') !== '') {
            $liste = $liste->filter(fn ($c) => $f['etat'] === 'a_faire' ? in_array($c->etat, [null, 'OPEN_WA'], true) : $c->etat === $f['etat'])->values();
        }

        return $liste;
    }

    /** RG-MKT-010 : état dérivé des journaux de la campagne (envoyé ou ignoré l'emporte sur une simple ouverture). */
    private static function etat($logs): string
    {
        $actions = $logs->pluck('action')->all();

        return in_array('MARQUE_ENVOYE', $actions, true) ? 'MARQUE_ENVOYE' : (in_array('IGNORE', $actions, true) ? 'IGNORE' : 'OPEN_WA');
    }

    /** RG-MKT-005 : remplacement textuel des variables à partir de la vente du client. */
    public static function personnaliser(string $message, object $c): string
    {
        return strtr($message, [
            '{client_nom}' => (string) $c->client_nom, '{client_tel}' => (string) $c->client_tel, '{date_vente}' => Format::date($c->date_vente),
            '{espece}' => Ventes::libelle($c->espece), '{montant_total}' => Montant::fcfa(Montant::base($c->montant_total)), '{id_vente}' => (string) $c->id,
        ]);
    }

    public static function lienWhatsapp(string $telNorm, string $texte): string
    {
        return 'https://wa.me/'.ltrim($telNorm, '+').'?text='.rawurlencode($texte);
    }

    /** RG-MKT-008, 009 : journal d'action (déclaration, pas d'accusé de réception). */
    public static function journaliser(int $campagne, string $action, string $telBrut, ?string $nom): string
    {
        if (! isset(self::ACTIONS[$action])) {
            throw new RegleMetier('Action invalide.');
        }
        $n = self::normaliser($telBrut) ?? throw new RegleMetier('Téléphone non reconnu.');
        $c = DB::table('modMkt_campagnes')->where('id', $campagne)->first() ?? throw new RegleMetier('Campagne introuvable.');
        if ($c->statut === 'terminee') {
            throw new RegleMetier('Cette campagne est terminée.');
        }
        DB::table('modMkt_logs')->insert(['id_campagne' => $campagne, 'client_nom' => $nom ? mb_substr($nom, 0, 150) : null, 'client_tel_raw' => mb_substr($telBrut, 0, 50),
            'client_tel_norm' => $n, 'action' => $action, 'user_login' => mb_substr(Format::auteur(), 0, 80)]);
        if ($c->statut === 'brouillon') {
            DB::table('modMkt_campagnes')->where('id', $campagne)->update(['statut' => 'en_cours']);
        }

        return $n;
    }

    public static function blacklister(string $tel, ?string $motif): void
    {
        $n = self::normaliser($tel) ?? throw new RegleMetier('Numéro sénégalais non reconnu (9 chiffres, ou 221 suivi de 9 chiffres).');
        if (DB::table('modMkt_blacklist')->where('client_tel_norm', $n)->exists()) {
            throw new RegleMetier('Ce numéro est déjà sur la liste d’exclusion.');
        }
        DB::table('modMkt_blacklist')->insert(['client_tel_norm' => $n, 'motif' => $motif ? mb_substr($motif, 0, 150) : null]);
        Journal::enregistrer('mkt.exclusion', 'MKT', 'telephone', null, apres: ['tel' => $n, 'motif' => $motif]);
    }

    public static function retirerBlacklist(int $id): void
    {
        $l = DB::table('modMkt_blacklist')->where('id', $id)->first() ?? throw new RegleMetier('Numéro introuvable.');
        DB::table('modMkt_blacklist')->where('id', $id)->delete();
        Journal::enregistrer('mkt.exclusion_retrait', 'MKT', 'telephone', null, avant: (array) $l);
    }
}
