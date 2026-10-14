<?php

namespace App\Tests\Controller;

use App\Entity\Frigo;
use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use App\Repository\FrigoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FrigoTest extends WebTestCase
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
            $user->setEmail('frigo_test_' . uniqid() . '@example.com');
            $user->setFirstname('Test');
            $user->setLastname('Frigo');
            $user->setPassword('hash');
            $user->setRoles(['ROLE_USER']);
            $this->em->persist($user);
            $this->em->flush();
        }
        $this->user = $user;
        $this->client->loginUser($this->user);
    }

    private function getOrCreateRecette(string $name = 'Quiche Test Frigo'): Recette
    {
        $recette = $this->em->getRepository(Recette::class)->findOneBy(['designation' => $name]);
        if (!$recette) {
            $recette = new Recette();
            $recette->setDesignation($name);
            $this->em->persist($recette);
            $this->em->flush();
        }
        return $recette;
    }

    public function testTimelinePageContainsMonFrigoButton(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Mon frigo', $html);
        $this->assertStringContainsString('openFrigoModal()', $html);
        $this->assertStringContainsString('repas-frigo-modal', $html);
    }

    public function testAddToFrigoAndRemoveFromFrigo(): void
    {
        $recette = $this->getOrCreateRecette('Tarte aux pommes ' . uniqid());
        $this->client->request('GET', '/');
        $csrfToken = $this->getCsrfToken('frigo_add');

        // 1. Add to fridge
        $this->client->request('POST', '/frigo/add', [
            'recette_id' => $recette->getId(),
            '_token' => $csrfToken,
        ]);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        $frigoRepo = $this->em->getRepository(Frigo::class);
        $frigoItem = $frigoRepo->findOneByRecetteAndUserOrFamille($recette, $this->user, $this->user->getFamille());
        $this->assertNotNull($frigoItem, 'La recette doit être dans le frigo.');

        // 2. Remove from fridge
        $removeToken = $this->getCsrfToken('frigo_remove');
        $this->client->request('POST', '/frigo/remove', [
            'recette_id' => $recette->getId(),
            '_token' => $removeToken,
        ]);
        $this->assertResponseRedirects('/');

        $this->em->clear();
        $frigoItemAfter = $frigoRepo->findOneByRecetteAndUserOrFamille($recette, $this->user, $this->user->getFamille());
        $this->assertNull($frigoItemAfter, 'La recette doit être retirée du frigo.');
    }

    public function testQuavezVousMangeProposesFridgeItemsAndMealAddRemovesIt(): void
    {
        $recette = $this->getOrCreateRecette('Salade Frigo ' . uniqid());

        // Put in fridge
        $frigo = new Frigo();
        $frigo->setRecette($recette);
        $frigo->setUser($this->user);
        $frigo->setFamille($this->user->getFamille());
        $this->em->persist($frigo);
        $this->em->flush();

        // Check home page displays it in tab Mon frigo
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('tab-btn-ajout-frigo', $html);
        $this->assertStringContainsString($recette->getDesignation(), $html);

        // Add realisation for this recipe -> must remove from fridge
        $addRealisationToken = $this->getCsrfToken('add_realisation');
        $this->client->request('POST', '/realisation/add', [
            'recette_id' => $recette->getId(),
            'moment' => 'soir',
            'date' => date('Y-m-d'),
            '_token' => $addRealisationToken,
        ]);
        $this->assertResponseRedirects('/');

        $this->em->clear();
        $frigoRepo = $this->em->getRepository(Frigo::class);
        $frigoItem = $frigoRepo->findOneByRecetteAndUserOrFamille($recette, $this->user, $this->user->getFamille());
        $this->assertNull($frigoItem, 'La recette enregistrée comme repas doit être automatiquement retirée du frigo.');
    }

    private function getCsrfToken(string $tokenId): string
    {
        $request = $this->client->getRequest();
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);
        try {
            return static::getContainer()->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
        }
    }

    public function testRecettePageMonFrigoFilter(): void
    {
        $recette1 = $this->getOrCreateRecette('Poulet Rôti ' . uniqid());
        $recette2 = $this->getOrCreateRecette('Soupe Poireaux ' . uniqid());

        // Put only recette1 in fridge
        $frigo = new Frigo();
        $frigo->setRecette($recette1);
        $frigo->setUser($this->user);
        $frigo->setFamille($this->user->getFamille());
        $this->em->persist($frigo);
        $this->em->flush();

        // 1. Normal list
        $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();
        $htmlAll = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Mon frigo', $htmlAll);
        $this->assertStringContainsString('Dans le frigo', $htmlAll);

        // 2. Filtered list by Mon frigo
        $this->client->request('GET', '/recettes?sort=frigo');
        $this->assertResponseIsSuccessful();
        $htmlFrigo = $this->client->getResponse()->getContent();
        $this->assertStringContainsString($recette1->getDesignation(), $htmlFrigo);
        $this->assertStringNotContainsString($recette2->getDesignation(), $htmlFrigo);
    }

    public function testTabsPropositionAndMonFrigoInPopups(): void
    {
        // Vider le frigo pour tester l'état vide
        $this->em->createQuery('DELETE FROM App\Entity\Frigo f WHERE f.user = :u')
            ->setParameter('u', $this->user)
            ->execute();

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();

        // Check tabs exist in both popups
        $this->assertStringContainsString('tab-btn-ajout-toutes', $html);
        $this->assertStringContainsString('tab-btn-ajout-frigo', $html);
        $this->assertStringContainsString('tab-btn-prop-toutes', $html);
        $this->assertStringContainsString('tab-btn-prop-frigo', $html);

        // Check "Mon frigo est vide" message appears when fridge is empty
        $this->assertStringContainsString('Mon frigo est vide', $html);

        // Check buttons "+ Créer une nouvelle recette" are present in modal-footers
        $this->assertStringContainsString('/recette?destination=repas', $html);
        $this->assertStringContainsString('/recette?destination=proposition', $html);
        $this->assertStringContainsString('/recette?destination=frigo', $html);
    }

    public function testCreateRecipeWithDestinationFrigoPlacesDirectlyInFridge(): void
    {
        $crawler = $this->client->request('GET', '/recette?destination=frigo');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer la recette')->form();
        $newRecipeName = 'Quiche Lorraine Frigo ' . uniqid();
        $form['recette[designation]'] = $newRecipeName;

        $this->client->submit($form);
        $this->assertResponseRedirects('/?open_frigo=1');

        $this->em->clear();
        $recette = $this->em->getRepository(Recette::class)->findOneBy(['designation' => $newRecipeName]);
        $this->assertNotNull($recette);

        $frigoRepo = $this->em->getRepository(Frigo::class);
        $frigoItem = $frigoRepo->findOneByRecetteAndUserOrFamille($recette, $this->user, $this->user->getFamille());
        $this->assertNotNull($frigoItem, 'La nouvelle recette avec destination=frigo doit être automatiquement ajoutée au frigo.');
    }

    public function testNoPhotoSectionInAjoutModal(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Le popup #repas-modal ne doit plus contenir de section photo
        $this->assertSelectorNotExists('#repas-modal #repas-photo-section');
        $this->assertSelectorNotExists('#repas-modal input[type="file"]');
    }

    public function testPropositionsProbabilitySorting(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();

        // La pilule Probabilité doit être active par défaut dans les modales
        $this->assertStringContainsString('data-sort="probability"', $html);
        $this->assertStringContainsString('data-probability=', $html);
    }

    public function testAddRecetteButtonStyleMatchesIngredients(): void
    {
        $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();

        // Vérifier la présence du bouton avec le composant standard et classe btn-primary
        $this->assertSelectorExists('a.btn-primary');
        $this->assertSelectorTextContains('a.btn-primary', '+ Ajouter une recette');
        // Ne doit plus utiliser l'ancien format brut avec .btn-text-full / .btn-text-mobile
        $this->assertSelectorNotExists('.btn-add-recette');
    }
}
