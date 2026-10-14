<?php

namespace App\Form;

use App\Entity\Boisson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class BoissonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la boisson',
                'attr' => [
                    'placeholder' => 'ex: Domaine des Terres Blanches, Bière IPA Citra, Cidre fermier...',
                    'class' => 'form-control',
                    'autofocus' => true,
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le nom de la boisson'),
                ],
            ])
            ->add('lieuAchat', TextType::class, [
                'label' => 'Lieu d\'achat',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Cave des Saveurs, Supermarché, Marché local...',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description ou notes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Cépage, millésime, notes de dégustation, température idéale...',
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Boisson::class,
        ]);
    }
}
