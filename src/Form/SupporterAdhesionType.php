<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

class SupporterAdhesionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(message: 'Merci de renseigner votre email.'),
                    new Email(message: 'Merci de renseigner une adresse email valide.'),
                ],
            ])
            ->add('agreesToCharter', CheckboxType::class, [
                'label' => 'J’adhère aux grandes orientations de la charte d’Union Citoyenne.',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez confirmer votre adhésion à la charte.'),
                ],
            ])
            ->add('acceptsFutureContact', CheckboxType::class, [
                'label' => 'J’accepte d’être recontacté ultérieurement au sujet du projet.',
                'mapped' => false,
                'required' => false,
            ])
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'tabindex' => '-1',
                    'class' => 'hp-field',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
