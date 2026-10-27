<?php

namespace App\Controller;

use App\Form\InscripcionEventoType;
use App\Utilidades\Carbono;
use App\Utilidades\Recaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class EventoController extends AbstractController
{
    #[Route('/evento/{id}', name: 'evento', requirements: ['id' => '\d+'])]
    public function evento(
        Request $request,
        Carbono $carbono,
        Recaptcha $recaptcha,
        RateLimiterFactoryInterface $formularioPublicoLimiter,
        int $id,
    ): Response {
        $respuesta = $carbono->evento($id);
        if ($respuesta['status'] === 404) {
            throw $this->createNotFoundException('El evento no existe');
        }
        // Si Carbono no responde la pagina se muestra igual, con el nombre por defecto
        $evento = $respuesta['error'] ? null : $respuesta['datos'];

        $form = $this->createForm(InscripcionEventoType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$formularioPublicoLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $form->addError(new FormError('Ha enviado demasiadas solicitudes, intente de nuevo en unos minutos.'));
            } elseif (!$recaptcha->validar($request->request->get('recaptcha_token'), 'evento', $request->getClientIp())) {
                $form->addError(new FormError('Lo siento, parece que eres un robot.'));
            } else {
                $respuesta = $carbono->inscribirEvento($id, $form->getData());
                if (!$respuesta['error']) {
                    $this->addFlash('success', 'El evento ha sido registrado');
                    return $this->redirectToRoute('evento', ['id' => $id]);
                }
                $form->addError(new FormError('No pudimos registrar su inscripción, por favor intente de nuevo más tarde.'));
            }
        }

        return $this->render('evento/evento.html.twig', [
            'form' => $form->createView(),
            'evento' => $evento,
        ]);
    }
}
