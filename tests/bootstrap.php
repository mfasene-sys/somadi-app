<?php

/*
 * Prépare une base de test identique à la production : schéma reconstruit (89 tables, 50 déclencheurs),
 * puis les tables app_*. Les tests s'exécutent ensuite chacun dans une transaction annulée.
 */
require __DIR__.'/../vendor/autoload.php';

$hote = getenv('DB_HOST') ?: '127.0.0.1';
$base = 'somadi_test';
$pdo = new PDO("mysql:host=$hote;charset=utf8mb4", 'somadi', 'somadi', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `$base`");
$pdo->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4");

$sql = file_get_contents(__DIR__.'/../database/legacy/schema_production.sql');
[$ddl, $declencheurs] = explode('DELIMITER $$', $sql, 2);
$pdo->exec("USE `$base`");
$pdo->exec($ddl);
foreach (array_filter(array_map('trim', explode('$$', str_replace('DELIMITER ;', '', $declencheurs)))) as $t) {
    $pdo->exec($t);
}

// Lignes de verrou attendues par les déclencheurs (présentes en production).
$pdo->exec("INSERT INTO modInfra_verrou (id, version_schema, revision) VALUES (1, 117, 0)");
$pdo->exec("INSERT INTO modCompta_exercice_lock (id, schema_version, revision, closed_through) VALUES (1, 116, 0, NULL)");

$sortie = [];
exec('cd '.escapeshellarg(dirname(__DIR__)).' && DB_DATABASE='.$base.' APP_ENV=testing php artisan migrate --force -q 2>&1', $sortie, $code);
if ($code !== 0) {
    fwrite(STDERR, "Échec des migrations de test :\n".implode("\n", $sortie)."\n");
    exit(1);
}
