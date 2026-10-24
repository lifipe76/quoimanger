<?php

namespace App\Form;

use App\Entity\Action;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('designation', TextType::class, [
                'label' => 'Désignation de l\'action',
                'attr' => [
                    'placeholder' => 'ex: Tondre la pelouse, Nettoyer les vitres, Faire le tri...',
                    'class' => 'form-control',
                    'autofocus' => true,
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir une désignation'),
                ],
            ])
            ->add('isPeriodique', CheckboxType::class, [
                'label' => 'Action périodique (à répéter régulièrement)',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('frequenceValeur', IntegerType::class, [
                'label' => 'Fréquence',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'placeholder' => '1, 2, 3...',
                    'class' => 'form-control',
                ],
            ])
            ->add('frequenceUnite', ChoiceType::class, [
                'label' => 'Unité',
                'required' => false,
                'placeholder' => 'Choisir l\'unité...',
                'choices' => [
                    'Jour(s)' => 'jour',
                    'Semaine(s)' => 'semaine',
                    'Mois' => 'mois',
                    'Année(s)' => 'annee',
                ],
                'attr' => [
                    'class' => 'form-control form-select',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Action::class,
        ]);
    }
}
