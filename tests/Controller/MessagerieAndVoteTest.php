<?php

namespace App\Tests\Controller;

use App\Entity\Famille;
use App\Entity\Recette;
use App\Entity\RepasProposition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MessagerieAndVoteTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if (!$user) {
            $user = new User();
            $user->setEmail('test@test.com');
            $user->setFirstname('Test');
            $user->setLastname('User');
            $user->setPassword($hasher->hashPassword($user, 'Testtest1'));
            $em->persist($user);
            $em->flush();
        }

        $famille = $user->getFamille();
        if (!$famille) {
            $famille = new Famille();
            $famille->setNom('Famille Test');
            $famille->setCreateur($user);
            $user->setFamille($famille);
            $em->persist($famille);
            $em->flush();
        }
    }

    public function testHeaderHasMessagerieIconWhenAuthenticated(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-messagerie-link');
        $this->assertSelectorExists('.nav-messagerie-link[href="/messagerie"]');
    }

    public function testHomePageHasJeProposeButton(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.cta-buttons', 'Je propose');
        $this->assertSelectorExists('#repas-proposition-modal');
    }

    public function testMessageriePageDisplaysFamilyChat(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/messagerie');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.chat-wrapper');
        $this->assertSelectorTextContains('.page-title', 'Famille Test');
        $this->assertStringContainsString('.messagerie-page-container', $this->client->getResponse()->getContent());
        $this->assertSelectorExists('#chatInputForm');
        $this->assertSelectorExists('#chatMessagesContainer');
    }

    public function testSendMessageInConversation(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $this->client->request('POST', '/messagerie/send', [
            'content' => 'Bonjour tout le monde !',
        ]);

        $this->assertResponseRedirects('/messagerie');
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.chat-body', 'Bonjour tout le monde !');
    }

    public function testProposeMealAndVoteAndValidateToTimeline(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        // Assurer qu'au moins une recette existe
        $recette = $em->getRepository(Recette::class)->findOneBy([]);
        if (!$recette) {
            $recette = new Recette();
            $recette->setDesignation('Pâtes Carbonara');
            $em->persist($recette);
            $em->flush();
        }

        $this->client->loginUser($user);

        // Obtenir jeton CSRF via home
        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('form.modal-prop-recipe-form input[name="_token"]')->first()->attr('value');

        // 1. Proposer un repas
        $this->client->request('POST', '/messagerie/proposer', [
            '_token' => $csrfToken,
            'recette_id' => $recette->getId(),
            'moment' => 'soir',
            'date' => date('Y-m-d'),
        ]);

        $this->assertResponseRedirects('/messagerie');
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.proposal-recipe-title', $recette->getDesignation());
        $this->assertSelectorExists('.proposal-votes-box');

        // Trouver la proposition en BDD
        $prop = $em->getRepository(RepasProposition::class)->findOneBy(
            ['recette' => $recette, 'famille' => $user->getFamille()],
            ['id' => 'DESC']
        );
        $this->assertNotNull($prop);
        $this->assertSame(1, count($prop->getVotesPour()));

        // 2. Tester l'endpoint API de messages
        $this->client->request('GET', '/messagerie/api/messages');
        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('messages', $json);
        $this->assertNotEmpty($json['messages']);

        // 3. Valider la proposition pour la transformer en repas du fil
        $propId = $prop->getId();
        $prop->setStatus(RepasProposition::STATUS_VALIDEE);
        $em->flush();

        $crawler = $this->client->request('GET', '/messagerie');
        $validerCsrf = $crawler->filter('form[action*="/messagerie/valider-au-fil/"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/messagerie/valider-au-fil/' . $propId, [
            '_token' => $validerCsrf,
        ]);

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Vérifier que la réalisation a bien été créée
        $em->refresh($prop);
        $this->assertSame(RepasProposition::STATUS_AJOUTEE_AU_FIL, $prop->getStatus());
        $this->assertNotNull($prop->getRealisation());
    }
}
