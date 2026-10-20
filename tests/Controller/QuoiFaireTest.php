<?php

namespace App\Tests\Controller;

use App\Entity\Action;
use App\Entity\ActionRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class QuoiFaireTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy([]);
        if ($user) {
            $this->client->loginUser($user);
        }
    }

    public function testQuoiFaireTimelinePageIsSuccessful(): void
    {
        $crawler = $this->client->request('GET', '/quoifaire');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.card-empty-cta');
        $this->assertSelectorExists('#action-modal');

        // Vérification du mode QuoiFaire actif dans le header
        $this->assertSelectorTextContains('.nav-links-main', 'Actions');
        $this->assertSelectorTextContains('.nav-brand-logo', '🏠');
    }

    public function testActionsListPageIsSuccessfulAndHasSortAndSearch(): void
    {
        $crawler = $this->client->request('GET', '/actions');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.sort-toolbar');
        $this->assertSelectorExists('#actions-search-input');
        $this->assertSelectorExists('.page-header');
    }

    public function testActionCreationAndLifecycle(): void
    {
        $crawler = $this->client->request('GET', '/action');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer l\'action')->form([
            'action[designation]' => 'Nettoyer le garage test ' . uniqid(),
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects('/actions');
        $this->client->followRedirect();

        $action = $this->em->getRepository(Action::class)->findOneBy([], ['id' => 'DESC']);
        $this->assertNotNull($action);

        // Ajout d'une réalisation
        $crawlerHome = $this->client->request('GET', '/quoifaire');
        $csrfToken = $crawlerHome->filter('form.modal-action-form input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/action-realisation/add', [
            '_token' => $csrfToken,
            'action_id' => $action->getId(),
            'date' => (new \DateTimeImmutable())->format('Y-m-d'),
            'moment' => 'apres-midi',
            'complement' => 'Test complété avec succès',
        ]);
        $this->assertResponseRedirects('/quoifaire');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $realisation = $this->em->getRepository(ActionRealisation::class)->findOneBy(['action' => $action]);
        $this->assertNotNull($realisation);
        $this->assertSame('Test complété avec succès', $realisation->getComplement());

        // Suppression de la réalisation
        $this->client->request('GET', '/quoifaire');
        $deleteToken = $this->getCsrfToken('delete_action_realisation_' . $realisation->getId());
        $this->client->request('POST', '/action-realisation/' . $realisation->getId() . '/delete', [
            '_token' => $deleteToken,
        ]);
        $this->assertResponseRedirects('/quoifaire');

        // Nettoyage de l'action
        $actionToRemove = $this->em->find(Action::class, $action->getId());
        if ($actionToRemove) {
            $this->em->remove($actionToRemove);
            $this->em->flush();
        }
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

    public function testBrandSwitcherInHeaderAndBottomNav(): void
    {
        // Sur QuoiManger (/)
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-brand-switcher');
        $this->assertSelectorExists('.nav-brand-popup-down');
        $this->assertSelectorExists('.bottom-brand-popup-up');
        $this->assertSelectorTextContains('.nav-links-main', 'Recettes');
        $this->assertSelectorTextContains('.nav-brand-logo', '🍽️');

        // Sur QuoiFaire (/quoifaire)
        $crawler = $this->client->request('GET', '/quoifaire');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-brand-switcher');
        $this->assertSelectorExists('.nav-brand-popup-down');
        $this->assertSelectorExists('.bottom-brand-popup-up');
        $this->assertSelectorTextContains('.nav-links-main', 'Actions');
        $this->assertSelectorTextContains('.nav-brand-logo', '🏠');
    }
}
