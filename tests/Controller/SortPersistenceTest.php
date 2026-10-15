<?php

namespace App\Tests\Controller;

use App\Entity\Recette;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SortPersistenceTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy([]);
        if (!$user) {
            $user = new User();
            $user->setEmail('sort_test_' . uniqid() . '@example.com');
            $user->setFirstname('Sort');
            $user->setLastname('Tester');
            $user->setPassword('hash');
            $user->setRoles(['ROLE_USER']);
            $this->em->persist($user);
            $this->em->flush();
        }
        $this->user = $user;
        $this->client->loginUser($this->user);
    }

    public function testRecettesListSortIsPreservedInSessionAndRestored(): void
    {
        // 1. Accéder à /recettes en spécifiant un tri par nom (alpha) ascendant
        $crawler = $this->client->request('GET', '/recettes?sort=alpha&order=asc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=alpha"].active');

        // 2. Accéder à /recettes sans aucun paramètre d'URL : le tri par nom doit être restauré
        $crawlerAfter = $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=alpha"].active');

        // 3. Modifier le tri vers 'note'
        $this->client->request('GET', '/recettes?sort=note&order=asc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=note"].active');

        // 4. Revenir sans paramètre : 'note' doit être actif
        $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=note"].active');
    }

    public function testRecettesSortIsPreservedAfterCreatingRecipe(): void
    {
        // 1. Définir un tri actif sur la liste des recettes (ex: rank, asc)
        $this->client->request('GET', '/recettes?sort=rank&order=asc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=rank"].active');

        // 2. Accéder au formulaire de création d'une recette
        $crawlerForm = $this->client->request('GET', '/recette');
        $this->assertResponseIsSuccessful();

        $form = $crawlerForm->selectButton('Créer la recette')->form();
        $recipeName = 'Recette Test Tri ' . uniqid();
        $form['recette[designation]'] = $recipeName;

        // 3. Soumettre le formulaire
        $this->client->submit($form);

        // 4. Doit rediriger vers la liste des recettes en conservant le tri
        $this->assertResponseRedirects();
        $targetUrl = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('sort=rank', $targetUrl);
        $this->assertStringContainsString('order=asc', $targetUrl);

        $crawlerRedirected = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=rank"].active');

        // Nettoyage de la recette créée
        $created = $this->em->getRepository(Recette::class)->findOneBy(['designation' => $recipeName]);
        if ($created) {
            $this->em->remove($created);
            $this->em->flush();
        }
    }

    public function testIngredientsListSortIsPreservedInSessionAndRestored(): void
    {
        // 1. Accéder à /ingredients avec tri par rang ascendant
        $this->client->request('GET', '/ingredients?sort=rank&order=asc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=rank"].active');

        // 2. Accéder à /ingredients sans paramètre : le tri par rang doit être conservé
        $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=rank"].active');

        // 3. Modifier le tri vers 'recettes' (nb de recettes)
        $this->client->request('GET', '/ingredients?sort=recettes&order=desc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=recettes"].active');

        // 4. Revenir sans paramètre : 'recettes' doit être conservé
        $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=recettes"].active');
    }

    public function testIngredientsSortIsPreservedAfterCreatingIngredient(): void
    {
        // 1. Définir un tri actif sur la liste des ingrédients (ex: recettes, desc)
        $this->client->request('GET', '/ingredients?sort=recettes&order=desc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=recettes"].active');

        // 2. Accéder au formulaire de création d'un ingrédient
        $crawlerForm = $this->client->request('GET', '/ingredient');
        $this->assertResponseIsSuccessful();

        $form = $crawlerForm->selectButton('Créer l\'ingrédient')->form();
        $ingredientName = 'Ingrédient Test Tri ' . uniqid();
        $form['ingredient[designation]'] = $ingredientName;

        // 3. Soumettre le formulaire
        $this->client->submit($form);

        // 4. Doit rediriger vers la liste des ingrédients en conservant le tri
        $this->assertResponseRedirects();
        $targetUrl = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('sort=recettes', $targetUrl);
        $this->assertStringContainsString('order=desc', $targetUrl);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=recettes"].active');

        // Nettoyage de l'ingrédient créé
        $created = $this->em->getRepository(\App\Entity\Ingredient::class)->findOneBy(['designation' => $ingredientName]);
        if ($created) {
            $this->em->remove($created);
            $this->em->flush();
        }
    }

    public function testIngredientSortingOrders(): void
    {
        // 1. Tri alpha asc
        $crawlerAlphaAsc = $this->client->request('GET', '/ingredients?sort=alpha&order=asc');
        $this->assertResponseIsSuccessful();
        $titlesAsc = $crawlerAlphaAsc->filter('.ingredient-title-link')->each(fn($node) => trim($node->text()));
        if (count($titlesAsc) > 1) {
            $sorted = $titlesAsc;
            natcasesort($sorted);
            $this->assertSame(array_values($sorted), array_values($titlesAsc));
        }

        // 2. Tri alpha desc
        $crawlerAlphaDesc = $this->client->request('GET', '/ingredients?sort=alpha&order=desc');
        $this->assertResponseIsSuccessful();
        $titlesDesc = $crawlerAlphaDesc->filter('.ingredient-title-link')->each(fn($node) => trim($node->text()));
        if (count($titlesDesc) > 1) {
            $sortedDesc = $titlesDesc;
            natcasesort($sortedDesc);
            $sortedDesc = array_reverse(array_values($sortedDesc));
            $this->assertSame($sortedDesc, array_values($titlesDesc));
        }

        // 3. Tri recettes desc
        $crawlerRecettes = $this->client->request('GET', '/ingredients?sort=recettes&order=desc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=recettes"].active');

        // 4. Tri rang asc
        $crawlerRank = $this->client->request('GET', '/ingredients?sort=rank&order=asc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-pill[href*="sort=rank"].active');
    }

    public function testTimelineModalsIncludeSortPersistenceLogic(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent();
        // Vérifier les fonctions et sélecteurs de tri dans le DOM et les scripts
        $this->assertStringContainsString('restoreModalSort', $html);
        $this->assertStringContainsString('modal-recipes-container', $html);
        $this->assertStringContainsString('modal-proposition-recipes-container', $html);
        $this->assertStringContainsString('modal-frigo-recipes-container', $html);
    }
}
