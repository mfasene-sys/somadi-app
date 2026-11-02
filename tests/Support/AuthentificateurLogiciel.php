<?php

namespace Tests\Support;

/**
 * Authentificateur WebAuthn logiciel pour les tests : se comporte comme le capteur d'un téléphone
 * (clé ES256, empreinte « vérifiée »), sans navigateur. Les signatures sont de vraies signatures ECDSA,
 * vérifiées par le serveur comme en production.
 */
class AuthentificateurLogiciel
{
    public \OpenSSLAsymmetricKey $cle;

    public string $credentialId;

    public int $compteur = 0;

    public function __construct(public string $domaine = 'somadi.test', public ?string $origine = null)
    {
        $this->cle = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = random_bytes(32);
        $this->origine ??= 'https://'.$domaine;
    }

    /** Réponse à navigator.credentials.create() pour les options reçues du serveur. */
    public function creer(array $options, ?string $userHandle = null): array
    {
        $clientData = $this->clientData('webauthn.create', $options['publicKey']['challenge']);
        $details = openssl_pkey_get_details($this->cle);
        $cose = self::cbor([
            1 => 2,          // kty : EC2
            3 => -7,         // alg : ES256
            -1 => 1,         // crv : P-256
            -2 => new Octets(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
            -3 => new Octets(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
        ]);
        $authData = hash('sha256', $this->domaine, true)
            .chr(0x01 | 0x04 | 0x40) // présence, vérification de l'utilisateur, données de clé jointes
            .pack('N', $this->compteur)
            .str_repeat("\0", 16)
            .pack('n', strlen($this->credentialId)).$this->credentialId
            .$cose;
        $attestation = self::cbor(['fmt' => 'none', 'attStmt' => [], 'authData' => new Octets($authData)]);

        return ['clientDataJSON' => self::b64u($clientData), 'attestationObject' => self::b64u($attestation)];
    }

    /** Réponse à navigator.credentials.get(). */
    public function signer(array $options, string $userHandle, bool $verifie = true): array
    {
        $this->compteur++;
        $clientData = $this->clientData('webauthn.get', $options['publicKey']['challenge']);
        $authData = hash('sha256', $this->domaine, true).chr(0x01 | ($verifie ? 0x04 : 0)).pack('N', $this->compteur);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->cle, OPENSSL_ALGO_SHA256);

        return [
            'id' => self::b64u($this->credentialId),
            'reponse' => [
                'clientDataJSON' => self::b64u($clientData),
                'authenticatorData' => self::b64u($authData),
                'signature' => self::b64u($signature),
                'userHandle' => self::b64u($userHandle),
            ],
        ];
    }

    public static function b64u(string $binaire): string
    {
        return rtrim(strtr(base64_encode($binaire), '+/', '-_'), '=');
    }

    public static function deB64u(string $b64u): string
    {
        return base64_decode(strtr($b64u, '-_', '+/'));
    }

    private function clientData(string $type, string $defi): string
    {
        return json_encode(['type' => $type, 'challenge' => $defi, 'origin' => $this->origine, 'crossOrigin' => false], JSON_UNESCAPED_SLASHES);
    }

    /** Encodeur CBOR minimal (entiers, textes, octets, tables). */
    private static function cbor(mixed $v): string
    {
        $tete = function (int $type, int $n): string {
            if ($n < 24) {
                return chr(($type << 5) | $n);
            }
            if ($n < 256) {
                return chr(($type << 5) | 24).chr($n);
            }
            if ($n < 65536) {
                return chr(($type << 5) | 25).pack('n', $n);
            }

            return chr(($type << 5) | 26).pack('N', $n);
        };

        return match (true) {
            $v instanceof Octets => $tete(2, strlen($v->octets)).$v->octets,
            is_int($v) && $v >= 0 => $tete(0, $v),
            is_int($v) => $tete(1, -1 - $v),
            is_string($v) => $tete(3, strlen($v)).$v,
            is_array($v) => $tete(5, count($v)).implode('', array_map(fn ($k, $x) => self::cbor($k).self::cbor($x), array_keys($v), $v)),
        };
    }
}
