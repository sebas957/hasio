<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nuevo caso de soporte. Los largos maximos son los de las columnas de caso en
 * Carbono; los modulos llegan de su API en la opcion "modulos" (nombre => id).
 */
class CasoSoporteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contacto', TextType::class, [
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
            ->add('cliente', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Escriba el nombre de su empresa.'),
                    new Assert\Length(max: 200, maxMessage: 'Máximo {{ limit }} caracteres.'),
                ],
            ])
            ->add('modulo', ChoiceType::class, [
                'choices' => $options['modulos'],
                'placeholder' => 'Seleccione un módulo',
                'constraints' => [
                    new Assert\NotBlank(message: 'Seleccione el módulo relacionado.'),
                ],
            ])
            ->add('descripcion', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Describa su solicitud.'),
                    new Assert\Length(max: 5000, maxMessage: 'Máximo {{ limit }} caracteres.'),
                ],
            ])
            ->add('archivos', FileType::class, [
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new Assert\Count(max: 5, maxMessage: 'Adjunte máximo {{ limit }} archivos.'),
                    new Assert\All([
                        new Assert\File(maxSize: '10M', maxSizeMessage: 'Cada archivo debe pesar máximo {{ limit }} {{ suffix }}.'),
                    ]),
                ],
            ])
            ->add('guardar', SubmitType::class, ['label' => 'Enviar solicitud']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('modulos');
        $resolver->setAllowedTypes('modulos', 'array');
    }
}
