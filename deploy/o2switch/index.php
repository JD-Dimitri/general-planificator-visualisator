<?php

/**
 * Contrôleur frontal de GPV sur hébergement mutualisé.
 *
 * Il vit dans public_html, alors que le code de l'application reste en dehors,
 * dans ~/gpv. Rien de sensible n'est donc servi par Apache : ni .env.local,
 * ni les clés JWT, ni le dossier var.
 *
 * Ajuste $racine si tu déposes le projet ailleurs que dans ~/gpv.
 */

use App\Kernel;

$racine = dirname(__DIR__).'/gpv/backend';

if (!is_file($racine.'/vendor/autoload_runtime.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode([
        'error' => 'install_incomplete',
        'message' => "Dépendances absentes. Lance « composer install --no-dev » dans $racine.",
    ], \JSON_UNESCAPED_UNICODE));
}

require_once $racine.'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
