<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paginas de contenido, sin formularios. Las rutas y sus nombres son los que
 * tenia la landing en Carbono, para no romper enlaces ni lo indexado.
 */
class PaginaController extends AbstractController
{
    #[Route('/', name: 'inicio')]
    public function inicio(): Response
    {
        return $this->render('inicio.html.twig');
    }

    #[Route('/nosotros', name: 'nosotros')]
    public function nosotros(): Response
    {
        return $this->render('nosotros/nosotros.html.twig');
    }

    #[Route('/producto/erp', name: 'producto_erp')]
    public function productoErp(): Response
    {
        return $this->render('producto/erp.html.twig');
    }

    #[Route('/servicio/{servicio}', name: 'servicio', requirements: ['servicio' => 'soporte|consultoria|capacitacion|nube|implementacion|medida'])]
    public function servicio(string $servicio): Response
    {
        return $this->render("servicio/{$servicio}.html.twig");
    }

    #[Route('/soporte/politicasprivacidad', name: 'soporte_politicas_privacidad')]
    public function politicasPrivacidad(): Response
    {
        return $this->render('legal/politicas_privacidad.html.twig');
    }
}
