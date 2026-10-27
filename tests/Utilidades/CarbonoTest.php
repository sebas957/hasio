<?php

namespace App\Tests\Utilidades;

use App\Utilidades\Carbono;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class CarbonoTest extends TestCase
{
    private function carbono(MockResponse|callable $respuesta): Carbono
    {
        return new Carbono(new MockHttpClient($respuesta), 'https://carbono.test/', 'token-de-prueba');
    }

    public function testNuevoContactoMandaTokenYProyectoDelSitio(): void
    {
        $peticion = null;
        $carbono = $this->carbono(function (string $metodo, string $url, array $opciones) use (&$peticion) {
            $peticion = compact('metodo', 'url', 'opciones');

            return new MockResponse('{"id": 15}');
        });

        $respuesta = $carbono->nuevoContacto(['nombre' => 'Ana', 'correo' => 'ana@empresa.co']);

        $this->assertFalse($respuesta['error']);
        $this->assertSame(15, $respuesta['datos']['id']);
        $this->assertSame('POST', $peticion['metodo']);
        $this->assertSame('https://carbono.test/api/contacto/nuevo', $peticion['url']);
        $this->assertContains('Authorization: Bearer token-de-prueba', $peticion['opciones']['headers']);
        $this->assertSame(
            ['nombre' => 'Ana', 'correo' => 'ana@empresa.co', 'codigoProyecto' => 3],
            json_decode($peticion['opciones']['body'], true)
        );
    }

    public function testEventoInexistenteDevuelve404(): void
    {
        $respuesta = $this->carbono(new MockResponse('{"mensaje": "El evento no existe"}', ['http_code' => 404]))->evento(99);

        $this->assertTrue($respuesta['error']);
        $this->assertSame(404, $respuesta['status']);
        $this->assertSame('El evento no existe', $respuesta['mensaje']);
    }

    public function testInscribirEventoUsaLaRutaDelEvento(): void
    {
        $url = null;
        $carbono = $this->carbono(function (string $metodo, string $urlPedida) use (&$url) {
            $url = $urlPedida;

            return new MockResponse('{"id": 7}');
        });

        $respuesta = $carbono->inscribirEvento(4, ['nombre' => 'Ana']);

        $this->assertFalse($respuesta['error']);
        $this->assertSame('https://carbono.test/api/evento/4/inscripcion', $url);
    }

    public function testCarbonoCaidoNoLanzaExcepcion(): void
    {
        $respuesta = $this->carbono(fn () => throw new TransportException('Connection refused'))->evento(1);

        $this->assertTrue($respuesta['error']);
        $this->assertSame(0, $respuesta['status']);
    }
}
