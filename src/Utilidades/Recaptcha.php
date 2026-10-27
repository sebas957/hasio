<?php

namespace App\Utilidades;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Validacion en el servidor del token de reCAPTCHA v3 que manda el formulario
 * (ver templates/_recaptcha.html.twig).
 *
 * Si Google no responde el envio se rechaza: es preferible que un usuario
 * reintente a dejar pasar bots hacia Carbono.
 */
class Recaptcha
{
    private const URL = 'https://www.google.com/recaptcha/api/siteverify';

    /** Puntaje minimo (0 = bot, 1 = humano) para aceptar el envio. */
    private const PUNTAJE_MINIMO = 0.5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'RECAPTCHA_SECRET')] private readonly string $secreto,
    ) {
    }

    /**
     * $accion debe ser la misma con la que el navegador pidio el token, asi un
     * token sacado de un formulario no sirve para otro.
     */
    public function validar(?string $token, string $accion, ?string $ip = null): bool
    {
        if (!$token) {
            return false;
        }

        try {
            $respuesta = $this->httpClient->request('POST', self::URL, [
                'body' => array_filter(['secret' => $this->secreto, 'response' => $token, 'remoteip' => $ip]),
                'timeout' => 5,
            ])->toArray(false);
        } catch (ExceptionInterface) {
            return false;
        }

        return ($respuesta['success'] ?? false) === true
            && ($respuesta['action'] ?? null) === $accion
            && ($respuesta['score'] ?? 0) >= self::PUNTAJE_MINIMO;
    }
}
