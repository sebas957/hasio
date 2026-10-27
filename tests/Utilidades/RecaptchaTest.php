<?php

namespace App\Tests\Utilidades;

use App\Utilidades\Recaptcha;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RecaptchaTest extends TestCase
{
    private function recaptcha(MockResponse|callable $respuesta): Recaptcha
    {
        return new Recaptcha(new MockHttpClient($respuesta), 'secreto');
    }

    private function google(array $cuerpo): MockResponse
    {
        return new MockResponse(json_encode($cuerpo));
    }

    public function testHumanoConLaAccionCorrectaPasa(): void
    {
        $recaptcha = $this->recaptcha($this->google(['success' => true, 'action' => 'contacto', 'score' => 0.9]));

        $this->assertTrue($recaptcha->validar('token', 'contacto'));
    }

    public function testPuntajeBajoNoPasa(): void
    {
        $recaptcha = $this->recaptcha($this->google(['success' => true, 'action' => 'contacto', 'score' => 0.3]));

        $this->assertFalse($recaptcha->validar('token', 'contacto'));
    }

    public function testTokenDeOtroFormularioNoPasa(): void
    {
        $recaptcha = $this->recaptcha($this->google(['success' => true, 'action' => 'evento', 'score' => 0.9]));

        $this->assertFalse($recaptcha->validar('token', 'contacto'));
    }

    public function testSinTokenNiSiquieraConsultaAGoogle(): void
    {
        $recaptcha = $this->recaptcha(fn () => $this->fail('No debia consultar a Google'));

        $this->assertFalse($recaptcha->validar(null, 'contacto'));
        $this->assertFalse($recaptcha->validar('', 'contacto'));
    }

    public function testGoogleCaidoNoPasa(): void
    {
        $recaptcha = $this->recaptcha(fn () => throw new TransportException('timeout'));

        $this->assertFalse($recaptcha->validar('token', 'contacto'));
    }
}
