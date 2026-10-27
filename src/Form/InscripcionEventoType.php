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
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 200)],
            ])
            ->add('correo', EmailType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 200)],
            ])
            ->add('telefono', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
            ->add('empresa', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 200)],
            ])
            ->add('guardar', SubmitType::class, ['label' => 'Guardar']);
    }
}
