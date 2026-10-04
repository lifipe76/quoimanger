<?php

namespace App\Tests\Controller;

use App\Entity\Famille;
use App\Entity\FamilleInvitation;
use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FamilleAndUserTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testUserRegistration(): void
    {
        $email = 'nouveau_membre_' . uniqid() . '@example.com';

        $crawler = $this->client->request('GET', '/inscription');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="user_registration"]');

        $form = $crawler->selectButton('Créer mon compte')->form([
            'user_registration[firstname]' => 'Alice',
            'user_registration[lastname]' => 'Martin',
            'user_registration[email]' => $email,
            'user_registration[plainPassword][first]' => 'SuperSecret123',
            'user_registration[plainPassword][second]' => 'SuperSecret123',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects();
        $this->client->followRedirect();

        // Vérifier l'utilisateur en base
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertEquals('Alice', $user->getFirstname());
        $this->assertEquals('Martin', $user->getLastname());
        $this->assertEquals('Alice Martin', $user->getDisplayName());

        // Nettoyage
        $this->em->remove($user);
        $this->em->flush();
    }

    public function testFamilyCreationInvitationAndBannerWorkflow(): void
    {
        // 1. Créer deux utilisateurs
        $userA = new User();
        $userA->setEmail('user_a_' . uniqid() . '@example.com');
        $userA->setFirstname('Marc');
        $userA->setLastname('Durand');
        $userA->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($userA);

        $userB = new User();
        $userB->setEmail('user_b_' . uniqid() . '@example.com');
        $userB->setFirstname('Sophie');
        $userB->setLastname('Durand');
        $userB->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($userB);

        $this->em->flush();

        // 2. User A se connecte et visite /compte
        $this->client->loginUser($userA);
        $crawler = $this->client->request('GET', '/compte');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Ma Famille', $this->client->getResponse()->getContent());

        // Récupérer le token CSRF pour inviter
        $token = $crawler->filter('form[action$="/famille/inviter"] input[name="_token"]')->attr('value');

        // User A invite User B
        $this->client->request('POST', '/famille/inviter', [
            '_token' => $token,
            'invite_email' => $userB->getEmail(),
        ]);
        $this->assertResponseRedirects('/compte?tab=famille');
        $this->client->followRedirect();

        // Vérifier que la famille de A a été créée et l'invitation enregistrée
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $userARefreshed = $freshEm->find(User::class, $userA->getId());
        $famille = $userARefreshed->getFamille();
        $this->assertNotNull($famille);

        $invitation = $freshEm->getRepository(FamilleInvitation::class)->findOneBy([
            'famille' => $famille,
            'inviteEmail' => strtolower($userB->getEmail()),
        ]);
        $this->assertNotNull($invitation);
        $this->assertEquals(FamilleInvitation::STATUT_EN_ATTENTE, $invitation->getStatut());

        // 3. User B se connecte et voit le bandeau d'invitation en haut
        $this->client->loginUser($userB);
        $crawlerB = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('.family-invitation-banner');
        $this->assertStringContainsString('Marc Durand vous invite à rejoindre sa famille', $crawlerB->filter('.family-invitation-banner')->text());

        // Récupérer le token pour accepter l'invitation
        $acceptForm = $crawlerB->filter(sprintf('form[action$="/famille/invitation/%d/accepter"]', $invitation->getId()));
        $acceptToken = $acceptForm->filter('input[name="_token"]')->attr('value');

        // User B accepte l'invitation
        $this->client->request('POST', sprintf('/famille/invitation/%d/accepter', $invitation->getId()), [
            '_token' => $acceptToken,
        ]);
        $this->assertResponseRedirects();
        $this->client->followRedirect();

        // Vérifier que User B a bien rejoint la famille
        $userBRefreshed = $freshEm->find(User::class, $userB->getId());
        $this->assertNotNull($userBRefreshed->getFamille());
        $this->assertEquals($famille->getId(), $userBRefreshed->getFamille()->getId());

        // Le bandeau ne s'affiche plus
        $this->client->request('GET', '/');
        $this->assertSelectorNotExists('.family-invitation-banner');

        // Nettoyage
        $freshEm->createQuery('DELETE FROM App\Entity\FamilleInvitation i WHERE i.famille = :famille')
            ->setParameter('famille', $famille)
            ->execute();
        $freshEm->createQuery('UPDATE App\Entity\User u SET u.famille = NULL WHERE u.famille = :famille')
            ->setParameter('famille', $famille)
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Famille f WHERE f.id = :id')
            ->setParameter('id', $famille->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\User u WHERE u.id IN (:ids)')
            ->setParameter('ids', [$userA->getId(), $userB->getId()])
            ->execute();
    }

    public function testMealParticipantsUpdatingAndDisplay(): void
    {
        // 1. Créer une famille avec 2 membres
        $famille = new Famille();
        $famille->setNom('Famille Test Repas');
        $this->em->persist($famille);

        $parent = new User();
        $parent->setEmail('parent_' . uniqid() . '@example.com');
        $parent->setFirstname('Papa');
        $parent->setFamille($famille);
        $parent->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($parent);

        $enfant = new User();
        $enfant->setEmail('enfant_' . uniqid() . '@example.com');
        $enfant->setFirstname('Enfant');
        $enfant->setFamille($famille);
        $enfant->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($enfant);

        $recette = new Recette();
        $recette->setDesignation('Pâtes carbonara famille test');
        $this->em->persist($recette);

        // Réalisation 1 : par défaut, aucun participant spécifié => tout le monde
        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable());
        $realisation->setMoment('soir');
        $this->em->persist($realisation);

        $this->em->flush();

        $this->assertTrue($realisation->isPourTous());
        $this->assertTrue($realisation->hasParticipant($parent));
        $this->assertTrue($realisation->hasParticipant($enfant));

        // 2. Vérifier l'affichage sur la timeline : pas de badge restrictif par défaut sur cette carte
        $this->client->loginUser($parent);
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $card = $crawler->filterXPath('//div[contains(@class, "card-recette") and .//*[contains(text(), "Pâtes carbonara famille test")]]');
        $this->assertEquals(0, $card->filter('.card-recette-participants-bubble')->count());

        // 3. Modifier le repas pour restreindre à "Papa" uniquement
        $btn = $crawler->filter(sprintf('.card-btn-pencil[onclick*="/realisation/%d/update-date"]', $realisation->getId()));
        $this->assertGreaterThan(0, $btn->count());
        $onclick = $btn->attr('onclick');
        preg_match_all("/'([^']*)'/", $onclick, $tokenMatches);
        $token = $tokenMatches[1][4] ?? '';

        $this->client->request('POST', sprintf('/realisation/%d/update-date', $realisation->getId()), [
            '_token' => $token,
            'realise_at' => $realisation->getRealiseAt()->format('Y-m-d'),
            'moment' => 'soir',
            'has_participants_field' => '1',
            'participants' => [$parent->getId()],
        ]);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        // 4. Vérifier en base et à l'écran que Papa apparaît dans la bulle
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $updatedRealisation = $freshEm->find(RecetteRealisation::class, $realisation->getId());
        $this->assertFalse($updatedRealisation->isPourTous());
        $this->assertCount(1, $updatedRealisation->getParticipants());
        $this->assertEquals($parent->getId(), $updatedRealisation->getParticipants()->first()->getId());

        $crawlerAfter = $this->client->request('GET', '/');
        $cardAfter = $crawlerAfter->filterXPath('//div[contains(@class, "card-recette") and .//*[contains(text(), "Pâtes carbonara famille test")]]');
        $this->assertGreaterThan(0, $cardAfter->filter('.card-recette-participants-bubble')->count());
        $this->assertStringContainsString('Papa', $cardAfter->filter('.card-recette-participants-bubble')->text());

        // Nettoyage
        $freshEm->createQuery('DELETE FROM App\Entity\RecetteRealisation r WHERE r.id = :id')
            ->setParameter('id', $realisation->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Recette r WHERE r.id = :id')
            ->setParameter('id', $recette->getId())
            ->execute();
        $freshEm->createQuery('UPDATE App\Entity\User u SET u.famille = NULL WHERE u.famille = :famille')
            ->setParameter('famille', $famille)
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Famille f WHERE f.id = :id')
            ->setParameter('id', $famille->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\User u WHERE u.id IN (:ids)')
            ->setParameter('ids', [$parent->getId(), $enfant->getId()])
            ->execute();
    }

    public function testFamilyMembersMealRatingAndAverageDisplay(): void
    {
        // 1. Créer une famille avec 2 membres
        $famille = new \App\Entity\Famille();
        $famille->setNom('Famille Test Notation');
        $this->em->persist($famille);

        $parent = new \App\Entity\User();
        $parent->setEmail('parent_note_' . uniqid() . '@example.com');
        $parent->setFirstname('PapaNote');
        $parent->setFamille($famille);
        $parent->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($parent);

        $maman = new \App\Entity\User();
        $maman->setEmail('maman_note_' . uniqid() . '@example.com');
        $maman->setFirstname('MamanNote');
        $maman->setFamille($famille);
        $maman->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($maman);

        $recette = new \App\Entity\Recette();
        $recette->setDesignation('Tarte aux pommes test ' . uniqid());
        $this->em->persist($recette);

        $realisation = new \App\Entity\RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable());
        $realisation->setMoment('soir');
        $this->em->persist($realisation);

        $this->em->flush();

        // 2. Papa se connecte et note 4 étoiles ce repas
        $this->client->loginUser($parent);
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Trouver le token de notation sur la carte
        $card = $crawler->filterXPath(sprintf('//div[contains(@class, "card-recette") and .//*[contains(text(), "%s")]]', $recette->getDesignation()));
        $this->assertGreaterThan(0, $card->count());

        $token = $card->filter('.quick-stars-wrapper')->attr('data-csrf');

        $this->client->request('POST', sprintf('/realisation/%d/rate', $realisation->getId()), [
            '_token' => $token,
            'note' => 4,
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(4.0, $data['recipeAverage']);
        $this->assertEquals(1, $data['recipeCount']);

        // 3. Maman se connecte et note 5 étoiles le même repas
        $this->client->loginUser($maman);
        $crawlerMaman = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $cardMaman = $crawlerMaman->filterXPath(sprintf('//div[contains(@class, "card-recette") and .//*[contains(text(), "%s")]]', $recette->getDesignation()));
        $tokenMaman = $cardMaman->filter('.quick-stars-wrapper')->attr('data-csrf');

        $this->client->request('POST', sprintf('/realisation/%d/rate', $realisation->getId()), [
            '_token' => $tokenMaman,
            'note' => 5,
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertResponseIsSuccessful();
        $data2 = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data2['success']);
        $this->assertEquals(4.5, $data2['recipeAverage']);
        $this->assertEquals(2, $data2['recipeCount']);

        // 4. Vérifier l'affichage sur la timeline
        $crawlerTimeline = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $cardTimeline = $crawlerTimeline->filterXPath(sprintf('//div[contains(@class, "card-recette") and .//*[contains(text(), "%s")]]', $recette->getDesignation()));
        $this->assertGreaterThan(0, $cardTimeline->count());
        $this->assertStringContainsString('(2)', $cardTimeline->filter('.rating-count')->text());
        $this->assertStringContainsString('4.5', $cardTimeline->filter('.rating-avg-value')->text());

        // 5. Vérifier l'affichage sur la liste des recettes
        $crawlerRecettes = $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();
        $cardRecettes = $crawlerRecettes->filterXPath(sprintf('//div[contains(@class, "card-recette") and .//*[contains(text(), "%s")]]', $recette->getDesignation()));
        $this->assertGreaterThan(0, $cardRecettes->count());
        $this->assertStringContainsString('(2)', $cardRecettes->filter('.rating-count')->text());

        // Nettoyage
        $freshEm = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $freshEm->createQuery('DELETE FROM App\Entity\RealisationNote rn WHERE rn.realisation = :realisation')
            ->setParameter('realisation', $realisation)
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\RecetteRealisation r WHERE r.id = :id')
            ->setParameter('id', $realisation->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Recette r WHERE r.id = :id')
            ->setParameter('id', $recette->getId())
            ->execute();
        $freshEm->createQuery('UPDATE App\Entity\User u SET u.famille = NULL WHERE u.famille = :famille')
            ->setParameter('famille', $famille)
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Famille f WHERE f.id = :id')
            ->setParameter('id', $famille->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\User u WHERE u.id IN (:ids)')
            ->setParameter('ids', [$parent->getId(), $maman->getId()])
            ->execute();
    }
}
