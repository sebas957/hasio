<?php

namespace App\Controller;

use App\Form\ContactoType;
use App\Utilidades\Carbono;
use App\Utilidades\Recaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class ContactoController extends AbstractController
{
    #[Route('/contacto', name: 'contacto')]
    public function contacto(
        Request $request,
        Carbono $carbono,
        Recaptcha $recaptcha,
        RateLimiterFactoryInterface $formularioPublicoLimiter,
    ): Response {
        $form = $this->createForm(ContactoType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // El limite va antes del captcha para que los envios rechazados tambien cuenten
            if (!$formularioPublicoLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $form->addError(new FormError('Ha enviado demasiadas solicitudes, intente de nuevo en unos minutos.'));
            } elseif (!$recaptcha->validar($request->request->get('recaptcha_token'), 'contacto', $request->getClientIp())) {
                $form->addError(new FormError('Lo siento, parece que eres un robot.'));
            } else {
                $respuesta = $carbono->nuevoContacto($form->getData());
                if (!$respuesta['error']) {
                    return $this->redirectToRoute('contacto_informacion', ['id' => $respuesta['datos']['id']]);
                }
                $form->addError(new FormError('No pudimos enviar su mensaje, por favor intente de nuevo más tarde.'));
            }
        }

        return $this->render('contacto/contacto.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/contacto/informacion/{id}', name: 'contacto_informacion', requirements: ['id' => '\d+'])]
    public function informacion(int $id): Response
    {
        return $this->render('contacto/informacion.html.twig', [
            'codigoContacto' => $id,
        ]);
    }
}
