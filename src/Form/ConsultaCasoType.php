<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Consulta del estado de un caso. Se pide el correo con el que se creo para
 * que nadie pueda leer casos ajenos recorriendo los numeros.
 */
class ConsultaCasoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('codigo', IntegerType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Escriba el número del caso.'),
                    new Assert\Positive(message: 'El número del caso no es válido.'),
                ],
            ])
            ->add('correo', EmailType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Escriba el correo con el que creó el caso.'),
                    new Assert\Email(message: 'El correo electrónico no es válido.'),
                ],
            ])
            ->add('consultar', SubmitType::class, ['label' => 'Consultar']);
    }

    public function getBlockPrefix(): string
    {
        return 'consulta';
    }
}
