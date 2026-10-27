<?php

namespace App\Utilidades;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente de la API de Carbono (mesa de ayuda), que guarda los contactos y las
 * inscripciones a eventos de la landing.
 *
 * Se autentica con "Authorization: Bearer <token>". El token se crea en
 * Carbono con: php bin/console app:api-token crear hasio
 */
class Carbono extends ClienteApi
{
    /** Proyecto de Carbono al que quedan asociados los contactos del sitio web. */
    private const PROYECTO_SITIO = 3;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire(env: 'BASE_CARBONO')] string $urlBase,
        #[Autowire(env: 'CARBONO_TOKEN')] private readonly string $token,
    ) {
        parent::__construct($httpClient, $urlBase, 'Carbono');
    }

    /**
     * $datos: nombre, telefono, correo, empresa, descripcion.
     * Exito: datos['id'] es el codigo del contacto creado.
     */
    public function nuevoContacto(array $datos): array
    {
        return $this->peticion('POST', 'api/contacto/nuevo', [
            'json' => $datos + ['codigoProyecto' => self::PROYECTO_SITIO],
        ]);
    }

    /** Exito: datos con id, nombre y fecha. Status 404 si no existe. */
    public function evento(int $id): array
    {
        return $this->peticion('GET', "api/evento/{$id}");
    }

    /** $datos: nombre, correo, telefono, empresa. */
    public function inscribirEvento(int $id, array $datos): array
    {
        return $this->peticion('POST', "api/evento/{$id}/inscripcion", ['json' => $datos]);
    }

    protected function cabeceras(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }
}
