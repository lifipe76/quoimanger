<?php

namespace App\Tests\Controller;

use App\Entity\Famille;
use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RecettesFeaturesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if (!$user) {
            $user = new User();
            $user->setEmail('test@test.com');
            $user->setFirstname('Test');
            $user->setLastname('User');
            $user->setPassword('password123');
            $this->em->persist($user);
            $this->em->flush();
        }

        $famille = $user->getFamille();
        if (!$famille) {
            $famille = new Famille();
            $famille->setNom('Famille Test');
            $famille->setCreateur($user);
            $user->setFamille($famille);
            $this->em->persist($famille);
            $this->em->flush();
        }

        $this->user = $user;
        $this->client->loginUser($user);
    }

    public function testRecettesListAndSortingToolbar(): void
    {
        // Créer 2 recettes
        $recette1 = new Recette();
        $recette1->setDesignation('Tarte aux pommes');
        $this->em->persist($recette1);

        $recette2 = new Recette();
        $recette2->setDesignation('Blanquette de veau');
        $this->em->persist($recette2);

        $this->em->flush();

        // Requête GET /recettes
        $crawler = $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // 1. En-tête et bouton + responsive
        $this->assertStringContainsString('recettes-header', $content);
        $this->assertStringContainsString('btn-add-recette', $content);
        $this->assertStringContainsString('btn-text-full', $content);
        $this->assertStringContainsString('btn-text-mobile', $content);
        $this->assertStringContainsString('+', $content);

        // 2. Barre de classement / tri
        $this->assertStringContainsString('recettes-sort-pills', $content);
        $this->assertStringContainsString('sort-order-btn', $content);
        $this->assertStringContainsString('Date', $content);
        $this->assertStringContainsString('Note', $content);
        $this->assertStringContainsString('Rang', $content);
        $this->assertStringContainsString('Nom', $content);

        // 3. Card recette : crayon présent, pas de poubelle ni "Ajoutée"
        $this->assertStringContainsString('card-btn-pencil', $content);
        $this->assertSelectorNotExists('.card-recette-footer');

        // 4. Test tri par date (décroissant par défaut ou explicite)
        $this->client->request('GET', '/recettes?sort=date&order=desc');
        $this->assertResponseIsSuccessful();

        // 5. Test tri par date (croissant)
        $this->client->request('GET', '/recettes?sort=date&order=asc');
        $this->assertResponseIsSuccessful();

        // Rétrocompatibilité : tests par recent et oldest
        $this->client->request('GET', '/recettes?sort=recent');
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/recettes?sort=oldest');
        $this->assertResponseIsSuccessful();

        // 6. Test tri par note
        $this->client->request('GET', '/recettes?sort=note');
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/recettes?sort=note&order=asc');
        $this->assertResponseIsSuccessful();

        // 7. Test tri par rang
        $this->client->request('GET', '/recettes?sort=rank');
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/recettes?sort=rank&order=desc');
        $this->assertResponseIsSuccessful();

        // 8. Test tri alphabétique
        $this->client->request('GET', '/recettes?sort=alpha');
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/recettes?sort=alpha&order=desc');
        $this->assertResponseIsSuccessful();
    }

    public function testRecetteEditPageHasMetaBannerAndDeletion(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Lasagnes maison');
        $this->em->persist($recette);
        $this->em->flush();

        // Page d'édition /recette/{id}
        $crawler = $this->client->request('GET', '/recette/' . $recette->getId());
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // Vérification de la présence de la bannière méta et classes spécifiques
        $this->assertStringContainsString('recette-form-container', $content);
        $this->assertStringContainsString('recette-form-card', $content);
        $this->assertStringContainsString('recette-meta-banner', $content);
        $this->assertStringContainsString('badge-realisations', $content);
        $this->assertStringContainsString('badge-created-at', $content);
        $this->assertStringContainsString('Ajoutée le', $content);
        $this->assertStringContainsString('recette-meta-stars', $content);

        // Vérification que le bouton ajouter un ingrédient est sous la liste
        $this->assertStringContainsString('add-ingredient-action-wrapper', $content);
        $this->assertStringContainsString('add-ingredient-btn', $content);

        // Vérification du bouton supprimer et confirmation
        $this->assertStringContainsString('delete-recette-form', $content);
        $this->assertStringContainsString('Supprimer la recette', $content);
        $this->assertStringContainsString('data-confirm', $content);
    }

    public function testPopupsHaveFourSortPillsAndHarmonizedDatePills(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // 1. Popup Ajout : 5 filtres de tri (dont Rang) et recherche extensible
        $this->assertStringContainsString('modal-sort-pills', $content);
        $this->assertStringContainsString('modal-search-expandable', $content);
        $this->assertStringContainsString('btn-search-toggle', $content);
        $this->assertStringContainsString('search-input-collapse', $content);
        $this->assertStringContainsString('modal-sort-order-btn', $content);
        $this->assertStringContainsString('Date', $content);
        $this->assertStringContainsString('Note', $content);
        $this->assertStringContainsString('Rang', $content);
        $this->assertStringContainsString('Nom', $content);

        // 2. Boutons date rapide présents
        $this->assertStringContainsString('btn-date-quick', $content);
        $this->assertStringContainsString("Aujourd'hui", $content);
        $this->assertStringContainsString('Hier', $content);

        // 3. Card recette : pas de "Ma note" ni de rating-row, la note est dans l'en-tête
        $this->assertSelectorNotExists('.card-recette-quick-rate');
        $this->assertSelectorNotExists('.card-recette-rating-row');
        $this->assertStringNotContainsString('Ma note :', $content);
    }
}
