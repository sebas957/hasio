<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscripcion a un evento. Los largos maximos son los de las columnas de
 * evento_detalle en Carbono.
 */
class InscripcionEventoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Escriba su nombre.'),
                    new Assert\Length(max: 200, maxMessage: 'Máximo {{ limit }} caracteres.'),
                ],
            ])
            ->add('correo', EmailType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Escriba su correo electrónico.'),
                    new Assert\Email(message: 'El correo electrónico no es válido.'),
                    new Assert\Length(max: 200, maxMessage: 'Máximo {{ limit }} caracteres.'),
                ],
            ])
            ->add('telefono', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Escriba un teléfono de contacto.'),
                    new Assert\Length(max: 100, maxMessage: 'Máximo {{ limit }} caracteres.'),
                ],
            ])
            ->add('empresa', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Escriba el nombre de su empresa.'),
                    new Assert\Length(max: 200, maxMessage: 'Máximo {{ limit }} caracteres.'),
                ],
            ])
            ->add('guardar', SubmitType::class, ['label' => 'Guardar']);
    }
}
