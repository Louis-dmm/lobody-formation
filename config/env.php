<?php
/**
 * Chargeur de configuration.
 * Lit le fichier .env situé à la racine du projet et expose les variables
 * via getenv(). Aucune dépendance externe requise.
 */

(function () {
    $cheminEnv = dirname(__DIR__) . '/.env';

    if (!is_readable($cheminEnv)) {
        return;
    }

    foreach (file($cheminEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);

        if ($ligne === '' || $ligne[0] === '#' || strpos($ligne, '=') === false) {
            continue;
        }

        list($cle, $valeur) = explode('=', $ligne, 2);
        $cle = trim($cle);
        $valeur = trim($valeur);

        if (strlen($valeur) >= 2) {
            $premier = $valeur[0];
            $dernier = $valeur[strlen($valeur) - 1];
            if (($premier === '"' && $dernier === '"') || ($premier === "'" && $dernier === "'")) {
                $valeur = substr($valeur, 1, -1);
            }
        }

        putenv("$cle=$valeur");
        $_ENV[$cle] = $valeur;
    }
})();
