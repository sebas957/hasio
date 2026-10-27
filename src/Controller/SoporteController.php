<?php

namespace App\Controller;

use App\Form\CasoSoporteType;
use App\Form\ConsultaCasoType;
use App\Utilidades\Carbono;
use App\Utilidades\Recaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Soporte y autogestion de los clientes. Los casos se crean y se consultan en
 * Carbono (mesa de ayuda) a traves de su API.
 */
class SoporteController extends AbstractController
{
    /** Tipos de caso de Carbono que se pueden abrir desde la landing. */
    private const TIPOS = [
        'SOP' => ['nombre' => '¿Cómo se hace?', 'icono' => 'fa-question-circle', 'descripcion' => 'Dudas sobre el uso del ERP: procesos, manuales y recursos de apoyo.'],
        'ERR' => ['nombre' => 'Reporte de fallas', 'icono' => 'fa-bug', 'descripcion' => 'Errores 500 o fallas técnicas en el funcionamiento de la plataforma.'],
        'CES' => ['nombre' => 'Consultoría especializada', 'icono' => 'fa-chart-pie', 'descripcion' => 'Temas avanzados: costo de inventario, distribución de nómina, información financiera.'],
        'DES' => ['nombre' => 'Solicitud de desarrollo', 'icono' => 'fa-code-branch', 'descripcion' => 'Funcionalidades nuevas de uso general o mejoras a las existentes.'],
        'SOL' => ['nombre' => 'Solicitud especial', 'icono' => 'fa-database', 'descripcion' => 'Respaldos de bases de datos y especificaciones técnicas de nuestros productos.'],
        'SER' => ['nombre' => 'Productos y servicios', 'icono' => 'fa-concierge-bell', 'descripcion' => 'Capacitaciones, implementaciones, desarrollo a la medida, infraestructura y formatos.'],
    ];

    #[Route('/soporte', name: 'soporte')]
    public function soporte(
        Request $request,
        Carbono $carbono,
        RateLimiterFactoryInterface $consultaCasoLimiter,
    ): Response {
        $caso = null;
        $form = $this->createForm(ConsultaCasoType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$consultaCasoLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $form->addError(new FormError('Ha hecho demasiadas consultas, intente de nuevo en unos minutos.'));
            } else {
                $respuesta = $carbono->caso($form->get('codigo')->getData(), $form->get('correo')->getData());
                if (!$respuesta['error']) {
                    $caso = $respuesta['datos'];
                } elseif ($respuesta['status'] === 404) {
                    $form->addError(new FormError('No encontramos un caso con ese número y correo.'));
                } else {
                    $form->addError(new FormError('No pudimos consultar el caso, por favor intente de nuevo más tarde.'));
                }
            }
        }

        return $this->render('soporte/soporte.html.twig', [
            'tipos' => self::TIPOS,
            'form' => $form->createView(),
            'caso' => $caso,
        ]);
    }

    #[Route('/soporte/nuevo/{tipo}', name: 'soporte_nuevo', requirements: ['tipo' => 'SOP|ERR|CES|DES|SOL|SER'])]
    public function nuevo(
        string $tipo,
        Request $request,
        Carbono $carbono,
        Recaptcha $recaptcha,
        RateLimiterFactoryInterface $formularioPublicoLimiter,
    ): Response {
        $respuesta = $carbono->modulos();
        if ($respuesta['error']) {
            // Sin modulos no se puede crear el caso: la pagina ofrece los otros canales
            return $this->render('soporte/nuevo.html.twig', [
                'tipo' => self::TIPOS[$tipo],
                'form' => null,
            ]);
        }
        $modulos = array_column($respuesta['datos']['modulos'], 'id', 'nombre');

        $form = $this->createForm(CasoSoporteType::class, null, ['modulos' => $modulos]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // El limite va antes del captcha para que los envios rechazados tambien cuenten
            if (!$formularioPublicoLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $form->addError(new FormError('Ha enviado demasiadas solicitudes, intente de nuevo en unos minutos.'));
            } elseif (!$recaptcha->validar($request->request->get('recaptcha_token'), 'soporte', $request->getClientIp())) {
                $form->addError(new FormError('Lo siento, parece que eres un robot.'));
            } else {
                $datos = $form->getData();
                $archivos = $datos['archivos'] ?? [];
                unset($datos['archivos']);
                $respuesta = $carbono->nuevoCaso(['tipo' => $tipo] + $datos, $archivos);
                if (!$respuesta['error']) {
                    if (($respuesta['datos']['adjuntosFallidos'] ?? 0) > 0) {
                        $this->addFlash('warning', 'El caso se creó, pero algunos adjuntos no se pudieron guardar. Puede enviarlos a soporte@semantica.com.co indicando el número del caso.');
                    }

                    return $this->redirectToRoute('soporte_caso_informacion', ['id' => $respuesta['datos']['id']]);
                }
                $form->addError(new FormError('No pudimos crear el caso, por favor intente de nuevo más tarde.'));
            }
        }

        return $this->render('soporte/nuevo.html.twig', [
            'tipo' => self::TIPOS[$tipo],
            'form' => $form->createView(),
        ]);
    }

    #[Route('/soporte/caso/{id}', name: 'soporte_caso_informacion', requirements: ['id' => '\d+'])]
    public function casoInformacion(int $id): Response
    {
        return $this->render('soporte/informacion.html.twig', [
            'codigoCaso' => $id,
        ]);
    }

    #[Route('/soporte/descarga', name: 'soporte_descarga')]
    public function descarga(): Response
    {
        return $this->render('soporte/descarga.html.twig');
    }
}
