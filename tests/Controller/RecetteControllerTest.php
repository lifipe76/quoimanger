<?php

namespace App\Tests\Controller;

use App\Entity\Ingredient;
use App\Entity\Recette;
use App\Entity\RecetteIngredient;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RecetteControllerTest extends WebTestCase
{
    private function clearDatabase(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\RecetteRealisation rr')->execute();
        $em->createQuery('DELETE FROM App\Entity\RecetteIngredient ri')->execute();
        $em->createQuery('DELETE FROM App\Entity\Recette r')->execute();
        $em->createQuery('DELETE FROM App\Entity\Ingredient i')->execute();
    }

    private function getOrCreateTestUser(EntityManagerInterface $em): User
    {
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if (!$user) {
            $user = new User();
            $user->setEmail('test@test.com');
            $user->setPassword('$2y$13$dummyhashedpasswordfortesting');
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }
        return $user;
    }

    public function testHomeTimelineLoads(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getOrCreateTestUser($em);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Timeline des Repas');
        $this->assertSelectorTextContains('.card-empty-cta', 'Qu\'avez-vous mangé aujourd\'hui ?');
    }

    public function testListRecettesPageLoads(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/recettes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes Recettes');
        $this->assertSelectorTextContains('a.btn-primary', '+ Ajouter une recette');
    }

    public function testListIngredientsPageLoads(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ingredients');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes Ingrédients');
        $this->assertSelectorTextContains('a.btn-primary', '+ Ajouter un ingrédient');
    }

    public function testCreateAndEditIngredient(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);

        // 1. Formulaire création
        $crawler = $client->request('GET', '/ingredient');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer l\'ingrédient')->form([
            'ingredient[designation]' => 'Chocolat noir',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/ingredients');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Chocolat noir');

        // 2. Édition
        $ingredient = $em->getRepository(Ingredient::class)->findOneBy(['designation' => 'Chocolat noir']);
        $this->assertNotNull($ingredient);

        $crawler = $client->request('GET', '/ingredient/' . $ingredient->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer les modifications')->form([
            'ingredient[designation]' => 'Chocolat noir 70%',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/ingredients');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Chocolat noir 70%');
    }

    public function testCreateRecetteWithIngredient(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);

        $ingredient = new Ingredient();
        $ingredient->setDesignation('Sucre');
        $em->persist($ingredient);
        $em->flush();

        $crawler = $client->request('GET', '/recette');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer la recette')->form([
            'recette[designation]' => 'Gâteau au sucre',
        ]);

        $values = $form->getPhpValues();
        $values['recette']['recetteIngredients'] = [
            [
                'ingredient' => (string) $ingredient->getId(),
                'quantite' => '150g',
            ]
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/recettes');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Gâteau au sucre');
        $this->assertSelectorTextContains('body', 'Sucre');
        $this->assertSelectorTextContains('body', '150g');

        $recette = $em->getRepository(Recette::class)->findOneBy(['designation' => 'Gâteau au sucre']);
        $this->assertNotNull($recette);
        $this->assertCount(1, $recette->getRecetteIngredients());
        $this->assertSame('150g', $recette->getRecetteIngredients()->first()->getQuantite());
    }

    public function testAddAndDeleteRealisation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);

        $user = $this->getOrCreateTestUser($em);
        $client->loginUser($user);

        // Créer une recette
        $recette = new Recette();
        $recette->setDesignation('Risotto aux truffes');
        $em->persist($recette);
        $em->flush();

        // 1. Ajouter une réalisation via POST /realisation/add
        $client->request('POST', '/realisation/add', [
            'recette_id' => $recette->getId(),
        ]);
        $this->assertResponseRedirects('/');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Risotto aux truffes');
        $this->assertSelectorTextContains('body', 'Dégusté le');

        // Vérifier en base
        $realisations = $em->getRepository(RecetteRealisation::class)->findBy(['recette' => $recette]);
        $this->assertCount(1, $realisations);
        $realisation = $realisations[0];

        // 2. Supprimer la réalisation via POST /realisation/{id}/delete
        $crawler = $client->request('GET', '/');
        $deleteForm = $crawler->filter('form[action="/realisation/' . $realisation->getId() . '/delete"]');
        $token = $deleteForm->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/realisation/' . $realisation->getId() . '/delete', [
            '_token' => $token,
        ]);
        $em->clear();
        $this->assertNull($em->getRepository(RecetteRealisation::class)->find($realisation->getId()));
    }

    public function testApiPlatformRecetteAndRealisationEndpoints(): void
    {
        $client = static::createClient();

        // 1. GET /api/recettes
        $client->request('GET', '/api/recettes', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();

        // 2. POST /api/ingredients
        $client->request('POST', '/api/ingredients', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode([
            'designation' => 'Beurre demi-sel',
        ]));
        $this->assertResponseStatusCodeSame(201);
        $ingredientData = json_decode($client->getResponse()->getContent(), true);
        $ingredientIri = $ingredientData['@id'];

        // 3. POST /api/recettes
        $client->request('POST', '/api/recettes', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode([
            'designation' => 'Kouign-amann de Douarnenez',
            'recetteIngredients' => [
                [
                    'ingredient' => $ingredientIri,
                    'quantite' => '300g',
                ]
            ],
        ]));
        $this->assertResponseStatusCodeSame(201);
        $recetteData = json_decode($client->getResponse()->getContent(), true);
        $recetteIri = $recetteData['@id'];

        // 4. POST /api/recette_realisations
        $client->request('POST', '/api/recette_realisations', server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode([
            'recette' => $recetteIri,
            'realiseAt' => '2026-09-16T12:30:00+02:00',
        ]));
        $this->assertResponseStatusCodeSame(201);
        $realisationData = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($realisationData['@id']);

        // 5. GET /api/recettes/{id}
        $client->request('GET', $recetteIri, server: [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testDeleteIngredient(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);

        $ingredient = new Ingredient();
        $ingredient->setDesignation('Sel de Guérande');
        $em->persist($ingredient);
        $em->flush();

        $crawler = $client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/ingredient/' . $ingredient->getId() . '/delete', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/ingredients');
        $client->followRedirect();

        $this->assertNull($em->getRepository(Ingredient::class)->find($ingredient->getId()));
    }

    public function testDeleteRecette(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);

        $recette = new Recette();
        $recette->setDesignation('Omelette nature');
        $em->persist($recette);
        $em->flush();

        $crawler = $client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/recette/' . $recette->getId() . '/delete', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/recettes');
        $client->followRedirect();

        $this->assertNull($em->getRepository(Recette::class)->find($recette->getId()));
    }
}
