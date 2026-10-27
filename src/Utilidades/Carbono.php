<?php

namespace App\Utilidades;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente de la API de Carbono (mesa de ayuda), que guarda los contactos, las
 * inscripciones a eventos y los casos de soporte de la landing.
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

    /** Exito: datos['modulos'] con id y nombre, ordenados por nombre. */
    public function modulos(): array
    {
        return $this->peticion('GET', 'api/soporte/modulo/lista');
    }

    /**
     * $datos: tipo, contacto, correo, telefono, cliente, modulo, descripcion.
     * $archivos: UploadedFile[] que se adjuntan al caso.
     * Exito: datos['id'] es el codigo del caso y datos['adjuntosFallidos']
     * cuantos adjuntos no se pudieron guardar.
     */
    public function nuevoCaso(array $datos, array $archivos = []): array
    {
        $campos = array_map('strval', $datos);
        foreach ($archivos as $archivo) {
            $campos['files'][] = DataPart::fromPath($archivo->getPathname(), $archivo->getClientOriginalName(), $archivo->getClientMimeType());
        }
        $formulario = new FormDataPart($campos);

        return $this->peticion('POST', 'api/soporte/caso/nuevo', [
            'headers' => $formulario->getPreparedHeaders()->toArray(),
            'body' => $formulario->bodyToIterable(),
        ]);
    }

    /** Exito: datos del caso con sus respuestas. Status 404 si el numero y el correo no coinciden. */
    public function caso(int $id, string $correo): array
    {
        return $this->peticion('GET', "api/soporte/caso/{$id}", ['query' => ['correo' => $correo]]);
    }

    protected function cabeceras(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }
}
