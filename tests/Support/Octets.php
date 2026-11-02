<?php

namespace Tests\Support;

/** Chaîne d'octets à encoder comme telle en CBOR (et non comme texte). */
final class Octets
{
    public function __construct(public string $octets)
    {
    }
}
