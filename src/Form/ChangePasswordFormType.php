<?php

namespace App\Form;

use App\Validator\Password;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangePasswordFormType extends AbstractType
{

    public function __construct(
        private TranslatorInterface $translator
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'attr' => [
                    'style' => 'display: flex; flex-direction: column; gap: 1.5rem;',
                ],
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'data-controller' => 'toggle-password',
                    ],
                    'row_attr' => [
                        'class' => 'champ'
                    ],
                ],
                'first_options' => [
                    'constraints' => Password::constraints(),
                    'label' => "reset_password.reset.label.mdp",
                ],
                'second_options' => [
                    'label' => "reset_password.reset.label.confirme",
                ],
                'invalid_message' => $this->translator->trans("reset_password.reset.erreurs.identique"),
                'mapped' => false,

            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
