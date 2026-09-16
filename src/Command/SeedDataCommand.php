<?php

namespace App\Command;

use App\Entity\Ingredient;
use App\Entity\Recette;
use App\Entity\RecetteIngredient;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use App\Enum\Civility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-data',
    description: 'Seeds the database with test user, ingredients and recipes',
)]
class SeedDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. User test@test.com
        $userRepo = $this->em->getRepository(User::class);
        $user = $userRepo->findOneBy(['email' => 'test@test.com']);
        if (!$user) {
            $user = new User();
            $user->setEmail('test@test.com');
            $user->setFirstname('Test');
            $user->setLastname('User');
            $user->setCivility(Civility::MONSIEUR);
            $user->setRoles(['ROLE_USER']);
        }

        $hashedPassword = $this->hasher->hashPassword($user, 'Testtest1');
        $user->setPassword($hashedPassword);
        $this->em->persist($user);
        $io->info('Utilisateur test@test.com créé / mis à jour avec le mot de passe Testtest1.');

        // 2. Ingrédients
        $ingredientsList = [
            'Farine',
            'Œufs',
            'Lait',
            'Beurre',
            'Sucre',
            'Chocolat noir',
            'Sel',
            'Pâtes',
            'Sauce tomate',
            'Mozzarella',
            'Basilic',
            'Blanc de poulet',
            'Crème fraîche',
            'Champignons de Paris',
            'Ail',
            'Huile d\'olive',
        ];

        $ingredientEntities = [];
        $ingredientRepo = $this->em->getRepository(Ingredient::class);

        foreach ($ingredientsList as $nom) {
            $ingredient = $ingredientRepo->findOneBy(['designation' => $nom]);
            if (!$ingredient) {
                $ingredient = new Ingredient();
                $ingredient->setDesignation($nom);
                $this->em->persist($ingredient);
            }
            $ingredientEntities[$nom] = $ingredient;
        }

        $this->em->flush();
        $io->info(sprintf('%d ingrédients prêts en base.', count($ingredientEntities)));

        // 3. Recettes
        $recettesData = [
            [
                'designation' => 'Crêpes bretonnes maison',
                'realiseAt' => new \DateTimeImmutable('-2 days 16:30'),
                'ingredients' => [
                    ['nom' => 'Farine', 'quantite' => '250g'],
                    ['nom' => 'Œufs', 'quantite' => '4 pièces'],
                    ['nom' => 'Lait', 'quantite' => '500ml'],
                    ['nom' => 'Beurre', 'quantite' => '50g'],
                    ['nom' => 'Sucre', 'quantite' => '2 cuillères à soupe'],
                    ['nom' => 'Sel', 'quantite' => '1 pincée'],
                ],
            ],
            [
                'designation' => 'Pâtes à la Tomate, Mozzarella et Basilic',
                'realiseAt' => new \DateTimeImmutable('-1 day 19:45'),
                'ingredients' => [
                    ['nom' => 'Pâtes', 'quantite' => '400g'],
                    ['nom' => 'Sauce tomate', 'quantite' => '200g'],
                    ['nom' => 'Mozzarella', 'quantite' => '1 boule (125g)'],
                    ['nom' => 'Huile d\'olive', 'quantite' => '2 cuillères à soupe'],
                    ['nom' => 'Basilic', 'quantite' => 'Quelques feuilles fraîches'],
                ],
            ],
            [
                'designation' => 'Poêlée de Poulet aux Champignons et Crème',
                'realiseAt' => new \DateTimeImmutable('now'),
                'ingredients' => [
                    ['nom' => 'Blanc de poulet', 'quantite' => '500g'],
                    ['nom' => 'Champignons de Paris', 'quantite' => '250g'],
                    ['nom' => 'Crème fraîche', 'quantite' => '20cl'],
                    ['nom' => 'Ail', 'quantite' => '2 gousses'],
                    ['nom' => 'Beurre', 'quantite' => '25g'],
                    ['nom' => 'Sel', 'quantite' => '1 pincée'],
                ],
            ],
            [
                'designation' => 'Mousse au Chocolat onctueuse',
                'realiseAt' => null,
                'ingredients' => [
                    ['nom' => 'Chocolat noir', 'quantite' => '200g'],
                    ['nom' => 'Œufs', 'quantite' => '6 pièces'],
                    ['nom' => 'Sucre', 'quantite' => '30g'],
                    ['nom' => 'Sel', 'quantite' => '1 pincée'],
                ],
            ],
            [
                'designation' => 'Omelette baveuse aux herbes',
                'realiseAt' => null,
                'ingredients' => [
                    ['nom' => 'Œufs', 'quantite' => '3 pièces'],
                    ['nom' => 'Beurre', 'quantite' => '15g'],
                    ['nom' => 'Sel', 'quantite' => '1 pincée'],
                ],
            ],
        ];

        $recetteRepo = $this->em->getRepository(Recette::class);

        foreach ($recettesData as $data) {
            $recette = $recetteRepo->findOneBy(['designation' => $data['designation']]);
            if (!$recette) {
                $recette = new Recette();
                $recette->setDesignation($data['designation']);
                $this->em->persist($recette);

                if (!empty($data['realiseAt'])) {
                    $dates = is_array($data['realiseAt']) ? $data['realiseAt'] : [$data['realiseAt']];
                    foreach ($dates as $date) {
                        $realisation = new RecetteRealisation();
                        $realisation->setRealiseAt($date);
                        $recette->addRealisation($realisation);
                    }
                }

                foreach ($data['ingredients'] as $item) {
                    $ingredientEntity = $ingredientEntities[$item['nom']] ?? null;
                    if ($ingredientEntity) {
                        $recetteIngredient = new RecetteIngredient();
                        $recetteIngredient->setIngredient($ingredientEntity);
                        $recetteIngredient->setQuantite($item['quantite']);
                        $recette->addRecetteIngredient($recetteIngredient);
                    }
                }
            }
        }

        $this->em->flush();
        $io->success('Données initiales (utilisateur, ingrédients, recettes avec quantités) insérées avec succès !');

        return Command::SUCCESS;
    }
}
