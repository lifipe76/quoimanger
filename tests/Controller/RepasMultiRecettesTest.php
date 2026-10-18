<?php

namespace App\Tests\Controller;

use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RepasMultiRecettesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if ($user) {
            $this->client->loginUser($user);
        }
    }

    private function getCsrfToken(string $tokenId): string
    {
        $request = $this->client->getRequest();
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
            if ($request && $request->hasSession()) {
                $request->getSession()->save();
            }
            return $token;
        } finally {
            $requestStack->pop();
        }
    }

    public function testMergeRecipeTitlesAlgorithm(): void
    {
        // 1. Deux recettes distinctes sans terme commun
        $this->assertSame('Steak, frites', RecetteRealisation::mergeRecipeTitles(['Steak', 'Frites']));

        // 2. Terme commun / subsumé : "pizza" absorbé par "pizza jambon"
        $this->assertSame('Pizza jambon', RecetteRealisation::mergeRecipeTitles(['Pizza', 'Pizza jambon']));
        $this->assertSame('Pizza jambon', RecetteRealisation::mergeRecipeTitles(['Pizza jambon', 'Pizza']));

        // 3. Salade César et Salade
        $this->assertSame('Salade César', RecetteRealisation::mergeRecipeTitles(['Salade', 'Salade César']));

        // 4. Casse et espaces nettoyés
        $this->assertSame('Steak, frites', RecetteRealisation::mergeRecipeTitles(['  steak  ', 'FRITES']));

        // 5. Doublons stricts
        $this->assertSame('Burger', RecetteRealisation::mergeRecipeTitles(['Burger', 'burger']));

        // 6. Trois recettes avec un terme subsumé
        $this->assertSame('Frites, pizza jambon', RecetteRealisation::mergeRecipeTitles(['Frites', 'Pizza jambon', 'Pizza']));

        // 7. Trois recettes distinctes
        $this->assertSame('Steak, frites, salade', RecetteRealisation::mergeRecipeTitles(['Steak', 'Frites', 'Salade']));

        // 8. Titre unique ou vide
        $this->assertSame('Tarte aux pommes', RecetteRealisation::mergeRecipeTitles(['Tarte aux pommes']));
        $this->assertSame('', RecetteRealisation::mergeRecipeTitles([]));
    }

    public function testAddRealisationWithMultipleRecipes(): void
    {
        $recetteA = new Recette();
        $recetteA->setDesignation('Steak grillé ' . uniqid());
        $this->em->persist($recetteA);

        $recetteB = new Recette();
        $recetteB->setDesignation('Frites fraîches ' . uniqid());
        $this->em->persist($recetteB);

        $this->em->flush();

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $token = $this->getCsrfToken('add_realisation');

        $this->client->request('POST', '/realisation/add', [
            '_token' => $token,
            'recette_ids' => [$recetteA->getId(), $recetteB->getId()],
            'moment' => 'soir',
            'date' => '2026-10-18',
        ]);

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $freshEm->clear();

        // Récupérer la dernière réalisation créée
        $realisation = $freshEm->getRepository(RecetteRealisation::class)->findOneBy(
            ['moment' => 'soir'],
            ['id' => 'DESC']
        );

        $this->assertNotNull($realisation);
        $this->assertCount(2, $realisation->getRecettes());

        $recetteIdsInRealisation = array_map(fn($r) => $r->getId(), $realisation->getRecettes()->toArray());
        $this->assertContains($recetteA->getId(), $recetteIdsInRealisation);
        $this->assertContains($recetteB->getId(), $recetteIdsInRealisation);

        $this->assertSame(
            RecetteRealisation::mergeRecipeTitles([$recetteA->getDesignation(), $recetteB->getDesignation()]),
            $realisation->getDisplayTitle()
        );
    }

    public function testUpdateRealisationAddSecondRecipe(): void
    {
        $recette1 = new Recette();
        $recette1->setDesignation('Pizza ' . uniqid());
        $this->em->persist($recette1);

        $recette2 = new Recette();
        $recette2->setDesignation($recette1->getDesignation() . ' jambon');
        $this->em->persist($recette2);

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette1);
        $realisation->addRecette($recette1);
        $realisation->setRealiseAt(new \DateTimeImmutable('today'));
        $realisation->setMoment('soir');
        $realisation->setComplement('Sans oignon');
        $this->em->persist($realisation);
        $this->em->flush();

        $this->assertSame($recette1->getDesignation(), $realisation->getDisplayTitle());

        // Charger la page d'accueil pour que la session et le token CSRF du repas existent
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Mettre à jour avec les 2 recettes via updateRealisationDate
        $token = $this->getCsrfToken('update_realisation_date_' . $realisation->getId());

        $this->client->request('POST', '/realisation/' . $realisation->getId() . '/update-date', [
            '_token' => $token,
            'has_recettes_field' => '1',
            'recette_ids' => [$recette1->getId(), $recette2->getId()],
            'realise_at' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'moment' => 'midi',
            'complement' => 'Sans oignon, bien cuit',
        ]);

        $this->assertResponseRedirects('/');

        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $freshEm->clear();
        $updated = $freshEm->getRepository(RecetteRealisation::class)->find($realisation->getId());

        $this->assertNotNull($updated);
        $this->assertCount(2, $updated->getRecettes());
        // Selon la règle pizza + pizza jambon, le mot commun n'est pas répété
        $this->assertSame($recette2->getDesignation(), $updated->getDisplayTitle());
        // Les invariants du repas restent uniques
        $this->assertSame('midi', $updated->getMoment());
        $this->assertSame('Sans oignon, bien cuit', $updated->getComplement());
    }

    public function testMealRatingAppliesToMealNotIndividualRecipes(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $recetteA = new Recette();
        $recetteA->setDesignation('Burger ' . uniqid());
        $this->em->persist($recetteA);

        $recetteB = new Recette();
        $recetteB->setDesignation('Frites ' . uniqid());
        $this->em->persist($recetteB);

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recetteA);
        $realisation->addRecette($recetteA);
        $realisation->addRecette($recetteB);
        $realisation->setRealiseAt(new \DateTimeImmutable('today'));
        $realisation->setMoment('soir');
        $this->em->persist($realisation);
        $this->em->flush();

        // Charger la page d'accueil pour initialiser le token CSRF
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $rateToken = $this->getCsrfToken('rate_realisation_' . $realisation->getId());

        // Noter le repas à 5 étoiles
        $this->client->request('POST', '/realisation/' . $realisation->getId() . '/rate', [
            '_token' => $rateToken,
            'note' => 5,
        ], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(5.0, (float) $data['mealAverage']);
        $this->assertSame(1, (int) $data['mealCount']);

        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $freshEm->clear();
        $reloaded = $freshEm->getRepository(RecetteRealisation::class)->find($realisation->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(5.0, (float) $reloaded->getAverageNote());
        $this->assertSame(1, $reloaded->getNotesCount());
    }

    public function testUpdateRealisationRemoveSecondRecipe(): void
    {
        $recette1 = new Recette();
        $recette1->setDesignation('Steak ' . uniqid());
        $this->em->persist($recette1);

        $recette2 = new Recette();
        $recette2->setDesignation('Frites ' . uniqid());
        $this->em->persist($recette2);

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette1);
        $realisation->addRecette($recette1);
        $realisation->addRecette($recette2);
        $realisation->setRealiseAt(new \DateTimeImmutable('today'));
        $realisation->setMoment('midi');
        $this->em->persist($realisation);
        $this->em->flush();

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $token = $this->getCsrfToken('update_realisation_date_' . $realisation->getId());

        // Retirer recette1 et ne garder que recette2
        $this->client->request('POST', '/realisation/' . $realisation->getId() . '/update-date', [
            '_token' => $token,
            'has_recettes_field' => '1',
            'recette_ids' => [$recette2->getId()],
            'realise_at' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'moment' => 'midi',
        ]);

        $this->assertResponseRedirects('/');

        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $freshEm->clear();
        $updated = $freshEm->getRepository(RecetteRealisation::class)->find($realisation->getId());

        $this->assertNotNull($updated);
        $this->assertCount(1, $updated->getRecettes());
        $this->assertSame($recette2->getId(), $updated->getRecettes()->first()->getId());
        $this->assertSame($recette2->getDesignation(), $updated->getDisplayTitle());
        $this->assertSame($recette2->getId(), $updated->getRecette()->getId());
    }

    public function testEditMealModalContainsRecipeChipsAndAddSelector(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Vérifier que la modale d'édition contient les éléments multi-recettes
        $this->assertSelectorExists('#edit-meal-modal #edit-meal-recipes-chips');
        $this->assertSelectorExists('#edit-meal-modal #edit-meal-recipes-inputs');
        $this->assertSelectorExists('#edit-meal-modal select#edit-meal-recipe-select');
        $this->assertSelectorExists('#edit-meal-modal .btn-add-recipe-chip');
    }
}
