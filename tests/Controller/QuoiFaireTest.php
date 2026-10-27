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

    public function testPeriodicActionLifecycleAndLabels(): void
    {
        $crawler = $this->client->request('GET', '/action');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#action_isPeriodique');

        $actionName = 'Arroser les plantes ' . uniqid();
        $form = $crawler->selectButton('Créer l\'action')->form([
            'action[designation]' => $actionName,
            'action[isPeriodique]' => '1',
            'action[frequenceValeur]' => 7,
            'action[frequenceUnite]' => 'jour',
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects('/actions');
        $crawlerActions = $this->client->followRedirect();

        $action = $this->em->getRepository(Action::class)->findOneBy(['designation' => $actionName]);
        $this->assertNotNull($action);
        $this->assertTrue($action->isPeriodique());
        $this->assertSame(7, $action->getFrequenceValeur());
        $this->assertSame('jour', $action->getFrequenceUnite());
        $this->assertSame('Tous les 7 jours', $action->getFrequenceTexte());

        // L'action n'a pas encore de réalisation -> "À faire"
        $this->assertSelectorTextContains('.cards-grid', 'À faire');
        $this->assertSelectorTextContains('.cards-grid', 'Tous les 7 jours');

        // Ajout d'une réalisation aujourd'hui
        $crawlerHome = $this->client->request('GET', '/quoifaire');
        $csrfToken = $crawlerHome->filter('form.modal-action-form input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/action-realisation/add', [
            '_token' => $csrfToken,
            'action_id' => $action->getId(),
            'date' => (new \DateTimeImmutable())->format('Y-m-d'),
            'moment' => 'matin',
        ]);
        $this->assertResponseRedirects('/quoifaire');
        $this->client->followRedirect();

        // Sur /actions, vérifier le temps écoulé et l'échéance future
        $crawlerActions = $this->client->request('GET', '/actions');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.cards-grid', 'Fait aujourd\'hui');
        $this->assertSelectorTextContains('.cards-grid', 'À refaire dans 1 semaine');

        // Vérification des libellés dans la popup de modification et sur la carte réalisation sur /quoifaire
        $crawlerHome = $this->client->request('GET', '/quoifaire');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#edit-action-modal', 'Qui a fait l\'action ?');
        $this->assertSelectorTextContains('#edit-action-modal', 'toute la famille a fait l\'action');
        $this->assertSelectorTextContains('.timeline-meal-wrapper', 'Fait aujourd\'hui');
        $prochaineDate = (new \DateTimeImmutable('today'))->modify('+7 days')->format('d/m/Y');
        $this->assertSelectorTextContains('.timeline-meal-wrapper', 'À refaire dans le ' . $prochaineDate);

        // Test avec une réalisation faite il y a 5 jours
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $realisationExistante = $em->getRepository(ActionRealisation::class)->findOneBy(['action' => $action]);
        $realisationExistante->setRealiseAt(new \DateTimeImmutable('-5 days'));
        $em->flush();
        $em->clear();

        $this->assertSame('Fait depuis 5 jours', $realisationExistante->getTempsDepuis());
        $echeance5j = $realisationExistante->getEcheanceInfo();
        $this->assertNotNull($echeance5j);
        $expectedNext = (new \DateTimeImmutable('-5 days'))->modify('+7 days')->format('d/m/Y');
        $this->assertSame('À refaire dans le ' . $expectedNext, $echeance5j['label']);

        $crawlerHomePast = $this->client->request('GET', '/quoifaire');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Fait depuis 5 jours', $crawlerHomePast->filter('.timeline-page')->text());
        $this->assertStringContainsString('À refaire dans le ' . $expectedNext, $crawlerHomePast->filter('.timeline-page')->text());

        // Nettoyage
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
