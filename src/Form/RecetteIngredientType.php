<?php

namespace App\Form;

use App\Entity\Ingredient;
use App\Entity\RecetteIngredient;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecetteIngredientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ingredient', EntityType::class, [
                'class' => Ingredient::class,
                'choice_label' => 'designation',
                'label' => 'Ingrédient',
                'placeholder' => '-- Choisir un ingrédient --',
                'attr' => [
                    'class' => 'form-control form-select select-ingredient',
                ],
            ])
            ->add('quantite', TextType::class, [
                'label' => 'Quantité',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: 200g, 2 c. à soupe, 1 pincée...',
                    'class' => 'form-control input-quantite',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecetteIngredient::class,
        ]);
    }
}
