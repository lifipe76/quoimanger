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

        // 3. User B se connecte et voit le badge d'alerte rouge sur l'icône Mon compte
        $this->client->loginUser($userB);
        $crawlerB = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // L'icône de compte dans le header possède un flag rond rouge avec '!'
        $this->assertSelectorExists('#avatarMenuImg .nav-alert-badge');
        $this->assertStringContainsString('!', $crawlerB->filter('#avatarMenuImg .nav-alert-badge')->text());

        // User B se rend sur son compte dans l'onglet Ma famille (qui a aussi le badge rouge '!')
        $crawlerBCompte = $this->client->request('GET', '/compte?tab=famille');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.tab-alert-badge');
        $this->assertStringContainsString('!', $crawlerBCompte->filter('.tab-alert-badge')->text());

        // Récupérer le token pour accepter l'invitation dans la boîte des invitations reçues
        $acceptForm = $crawlerBCompte->filter(sprintf('form[action$="/famille/invitation/%d/accepter"]', $invitation->getId()));
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

        // Le badge d'alerte ne s'affiche plus
        $this->client->request('GET', '/');
        $this->assertSelectorNotExists('#avatarMenuImg .nav-alert-badge');

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

    public function testFamilleAdminPermissionsAndModalRename(): void
    {
        // 1. Créer le chef et un membre dans une même famille
        $chef = new User();
        $chef->setEmail('chef_' . uniqid() . '@example.com');
        $chef->setFirstname('Jean');
        $chef->setLastname('Chef');
        $chef->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($chef);
        $this->em->flush();

        $famille = new Famille();
        $famille->setNom('Famille Dupont Test');
        $famille->setCreateur($chef);
        $famille->addMembre($chef);
        $this->em->persist($famille);

        $membre = new User();
        $membre->setEmail('membre_' . uniqid() . '@example.com');
        $membre->setFirstname('Paul');
        $membre->setLastname('Membre');
        $famille->addMembre($membre);
        $membre->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($membre);

        $this->em->flush();
        $this->em->clear();

        // Recharger le chef pour le login
        $chef = $this->em->find(User::class, $chef->getId());

        // 2. Vérifier la vue du Chef sur /compte?tab=famille
        $this->client->loginUser($chef);
        $crawlerChef = $this->client->request('GET', '/compte?tab=famille');
        $this->assertResponseIsSuccessful();

        // Pas d'icône peoples ni emoji dans les onglets ni dans l'en-tête de famille
        $this->assertSelectorNotExists('.account-tabs i.fa-users');
        $this->assertSelectorNotExists('.family-title-icon');
        $this->assertStringNotContainsString('👨‍👩‍👧‍👦', $crawlerChef->text());

        // Badge "Chef de famille" affiché
        $this->assertSelectorExists('.family-member-tag-chef');
        $this->assertStringContainsString('Chef de famille', $crawlerChef->filter('.family-member-tag-chef')->text());

        // Bouton crayon pour renommer et popup modal présents
        $this->assertSelectorExists('.family-edit-btn');
        $this->assertSelectorExists('#rename-family-modal');

        // Formulaire d'invitation présent pour le chef
        $this->assertSelectorExists('.family-invite-box');

        // Le chef renomme la famille
        $renameToken = $crawlerChef->filter('#rename-family-modal form input[name="_token"]')->attr('value');
        $this->client->request('POST', '/famille/renommer', [
            '_token' => $renameToken,
            'nom' => 'Nouvelle Famille Dupont',
        ]);
        $this->assertResponseRedirects('/compte?tab=famille');
        $this->client->followRedirect();

        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $freshFamille = $freshEm->find(Famille::class, $famille->getId());
        $this->assertEquals('Nouvelle Famille Dupont', $freshFamille->getNom());

        // 3. Vérifier la vue du membre non-chef sur /compte?tab=famille
        $membre = $freshEm->find(User::class, $membre->getId());
        $this->client->loginUser($membre);
        $crawlerMembre = $this->client->request('GET', '/compte?tab=famille');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorNotExists('.account-tabs i.fa-users');
        $this->assertSelectorNotExists('.family-title-icon');
        $this->assertStringNotContainsString('👨‍👩‍👧‍👦', $crawlerMembre->text());

        // Bouton crayon et modal absents pour le simple membre
        $this->assertSelectorNotExists('.family-edit-btn');
        $this->assertSelectorNotExists('#rename-family-modal');

        // Formulaire d'invitation absent pour le simple membre
        $this->assertSelectorNotExists('.family-invite-box');

        // Le membre simple ne peut pas inviter
        $inviteToken = $this->getCsrfToken('famille_inviter');
        $this->client->request('POST', '/famille/inviter', [
            '_token' => $inviteToken,
            'invite_email' => 'other_' . uniqid() . '@example.com',
        ]);
        $this->assertResponseRedirects('/compte?tab=famille');
        $crawlerAfterInvite = $this->client->followRedirect();
        $this->assertStringContainsString('Seul le chef de famille peut inviter de nouveaux membres.', $crawlerAfterInvite->text());

        // Le membre simple ne peut pas renommer
        $renameToken = $this->getCsrfToken('famille_renommer');
        $this->client->request('POST', '/famille/renommer', [
            '_token' => $renameToken,
            'nom' => 'Piratage Famille',
        ]);
        $this->assertResponseRedirects('/compte?tab=famille');
        $crawlerAfterRename = $this->client->followRedirect();
        $this->assertStringContainsString('Seul le chef de famille peut renommer la famille.', $crawlerAfterRename->text());

        // Bouton quitter la famille et popup modal sans alert présents pour le membre
        $this->assertSelectorExists('.family-leave-btn');
        $this->assertSelectorExists('#leave-family-modal');
        $this->assertSelectorExists('form.family-leave-form');
        // Vérifier l'absence d'attribut onsubmit avec confirm natif
        $this->assertSelectorNotExists('form.family-leave-form[onsubmit*="confirm"]');

        // Nettoyage
        $freshEm->createQuery('UPDATE App\Entity\User u SET u.famille = NULL WHERE u.famille = :famille')
            ->setParameter('famille', $freshFamille)
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Famille f WHERE f.id = :id')
            ->setParameter('id', $freshFamille->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\User u WHERE u.id IN (:ids)')
            ->setParameter('ids', [$chef->getId(), $membre->getId()])
            ->execute();
    }

    public function testTimelineMealFilteringForChefVsNonChef(): void
    {
        // 1. Créer une famille avec Chef et Membre
        $famille = new Famille();
        $famille->setNom('Famille Test Timeline');
        $this->em->persist($famille);

        $chef = new User();
        $chef->setEmail('chef_tl_' . uniqid() . '@example.com');
        $chef->setFirstname('ChefTL');
        $chef->setFamille($famille);
        $chef->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($chef);
        $famille->setCreateur($chef);

        $membre = new User();
        $membre->setEmail('membre_tl_' . uniqid() . '@example.com');
        $membre->setFirstname('MembreTL');
        $membre->setFamille($famille);
        $membre->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($membre);

        // Créer 3 recettes distinctes
        $recetteTous = new Recette();
        $recetteTous->setDesignation('Repas Pour Tous ' . uniqid());
        $this->em->persist($recetteTous);

        $recetteMembre = new Recette();
        $recetteMembre->setDesignation('Repas Pour Membre ' . uniqid());
        $this->em->persist($recetteMembre);

        $recetteChefSeul = new Recette();
        $recetteChefSeul->setDesignation('Repas Chef Solo ' . uniqid());
        $this->em->persist($recetteChefSeul);

        // Repas 1 : Pour tous (aucun participant spécifique)
        $r1 = new RecetteRealisation();
        $r1->setRecette($recetteTous);
        $r1->setRealiseAt(new \DateTimeImmutable());
        $r1->setMoment('soir');
        $this->em->persist($r1);

        // Repas 2 : Avec Membre spécifié
        $r2 = new RecetteRealisation();
        $r2->setRecette($recetteMembre);
        $r2->setRealiseAt(new \DateTimeImmutable());
        $r2->setMoment('soir');
        $r2->addParticipant($membre);
        $this->em->persist($r2);

        // Repas 3 : Avec Chef uniquement spécifié
        $r3 = new RecetteRealisation();
        $r3->setRecette($recetteChefSeul);
        $r3->setRealiseAt(new \DateTimeImmutable());
        $r3->setMoment('soir');
        $r3->addParticipant($chef);
        $this->em->persist($r3);

        $this->em->flush();

        // 2. Le membre se connecte et consulte la timeline
        $this->client->loginUser($membre);
        $crawlerMembre = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $timelineContentMembre = $crawlerMembre->filter('.timeline-wrapper .timeline-day-meals')->count() > 0
            ? $crawlerMembre->filter('.timeline-wrapper .timeline-day-meals')->text()
            : '';
        // Repas pour tous : présent
        $this->assertStringContainsString($recetteTous->getDesignation(), $timelineContentMembre);
        // Repas avec le membre comme participant : présent
        $this->assertStringContainsString($recetteMembre->getDesignation(), $timelineContentMembre);
        // Repas uniquement pour le chef : ABSENT pour le membre
        $this->assertStringNotContainsString($recetteChefSeul->getDesignation(), $timelineContentMembre);

        // 3. Le chef se connecte et consulte la timeline
        $this->client->loginUser($chef);
        $crawlerChef = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $timelineContentChef = $crawlerChef->filter('.timeline-wrapper .timeline-day-meals')->text();
        // Le chef voit TOUS les repas de la famille
        $this->assertStringContainsString($recetteTous->getDesignation(), $timelineContentChef);
        $this->assertStringContainsString($recetteMembre->getDesignation(), $timelineContentChef);
        $this->assertStringContainsString($recetteChefSeul->getDesignation(), $timelineContentChef);

        // Nettoyage
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $freshEm->createQuery('DELETE FROM App\Entity\RecetteRealisation r WHERE r.id IN (:ids)')
            ->setParameter('ids', [$r1->getId(), $r2->getId(), $r3->getId()])
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Recette r WHERE r.id IN (:ids)')
            ->setParameter('ids', [$recetteTous->getId(), $recetteMembre->getId(), $recetteChefSeul->getId()])
            ->execute();
        $freshEm->createQuery('UPDATE App\Entity\User u SET u.famille = NULL WHERE u.famille = :famille')
            ->setParameter('famille', $famille)
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Famille f WHERE f.id = :id')
            ->setParameter('id', $famille->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\User u WHERE u.id IN (:ids)')
            ->setParameter('ids', [$chef->getId(), $membre->getId()])
            ->execute();
    }

    public function testNonChefDoesNotSeePendingMembersAndInvitedUserCanAcceptOrRefuse(): void
    {
        $suffix = uniqid();
        $chef = new User();
        $chef->setEmail("chef_{$suffix}@test.com");
        $chef->setFirstname('Chef');
        $chef->setLastname('Test');
        $chef->setPassword('secret');

        $alice = new User();
        $alice->setEmail("alice_{$suffix}@test.com");
        $alice->setFirstname('Alice');
        $alice->setLastname('Test');
        $alice->setPassword('secret');

        $bob = new User();
        $bob->setEmail("bob_{$suffix}@test.com");
        $bob->setFirstname('Bob');
        $bob->setLastname('Test');
        $bob->setPassword('secret');

        $famille = new Famille();
        $famille->setNom("Famille Visibilite {$suffix}");
        $famille->setCreateur($chef);
        $famille->addMembre($chef);
        $famille->addMembre($alice);

        $this->em->persist($chef);
        $this->em->persist($alice);
        $this->em->persist($bob);
        $this->em->persist($famille);

        // Invitation en attente pour Bob
        $invitationBob = new FamilleInvitation();
        $invitationBob->setFamille($famille);
        $invitationBob->setDemandeur($chef);
        $invitationBob->setInviteEmail($bob->getEmail());
        $invitationBob->setInviteUser($bob);
        $invitationBob->setStatut(FamilleInvitation::STATUT_EN_ATTENTE);
        $this->em->persist($invitationBob);

        // Invitation en attente pour une tierce personne non inscrite (charlie)
        $invitationCharlie = new FamilleInvitation();
        $invitationCharlie->setFamille($famille);
        $invitationCharlie->setDemandeur($chef);
        $invitationCharlie->setInviteEmail("charlie_{$suffix}@test.com");
        $invitationCharlie->setStatut(FamilleInvitation::STATUT_EN_ATTENTE);
        $this->em->persist($invitationCharlie);

        $this->em->flush();

        // 1. Chef se connecte et va sur l'onglet Famille
        $this->client->loginUser($chef);
        $crawlerChef = $this->client->request('GET', '/compte?tab=famille');
        $this->assertResponseIsSuccessful();

        // Le chef voit 4 lignes au total (Chef + Alice confirmée + Bob en attente + Charlie en attente)
        $this->assertCount(4, $crawlerChef->filter('.family-member-item'));
        // Pas de doublon en bas de page sous le formulaire
        $this->assertSelectorNotExists('.family-pending-invitations');

        // Le chef voit Alice, et voit les membres en attente (Bob et Charlie)
        $this->assertStringContainsString('Alice Test', $crawlerChef->text());
        $this->assertStringContainsString("bob_{$suffix}@test.com", $crawlerChef->text());
        $this->assertStringContainsString("charlie_{$suffix}@test.com", $crawlerChef->text());
        $this->assertSelectorExists('.family-member-tag-pending');

        // 2. Alice (membre confirmé non-chef) se connecte
        $this->client->loginUser($alice);
        $crawlerAlice = $this->client->request('GET', '/compte?tab=famille');
        $this->assertResponseIsSuccessful();

        // Alice voit Chef et Alice
        $this->assertStringContainsString('Chef Test', $crawlerAlice->text());
        $this->assertStringContainsString('Alice Test', $crawlerAlice->text());

        // Alice ne doit PAS voir Bob ni Charlie qui n'ont pas encore accepté !
        $this->assertStringNotContainsString("bob_{$suffix}@test.com", $crawlerAlice->text());
        $this->assertStringNotContainsString("charlie_{$suffix}@test.com", $crawlerAlice->text());
        $this->assertSelectorNotExists('.family-member-tag-pending');

        // 3. Bob (l'utilisateur invité qui n'a pas encore répondu) se connecte
        $this->client->loginUser($bob);
        $crawlerBob = $this->client->request('GET', '/compte?tab=famille');
        $this->assertResponseIsSuccessful();

        // Bob ne doit PAS voir Charlie (autre membre en attente)
        $this->assertStringNotContainsString("charlie_{$suffix}@test.com", $crawlerBob->text());

        // Bob ne doit pas voir le bouton "Quitter la famille" car il n'a pas encore accepté
        $this->assertSelectorNotExists('.family-leave-btn');

        // Bob ne doit pas voir le formulaire d'invitation ni le bouton renommer
        $this->assertSelectorNotExists('.family-edit-btn');
        $this->assertSelectorNotExists('.family-invite-box');

        // Bob voit sa propre ligne dans la liste des membres avec "En attente d'acceptation" (sans boutons dupliqués)
        $this->assertSelectorExists('.family-member-item.family-member-pending');
        $this->assertStringContainsString('En attente d\'acceptation', $crawlerBob->filter('.family-member-item.family-member-pending')->text());
        $this->assertSelectorNotExists('.family-member-item.family-member-pending form');

        // Comme c'est Bob ("si c'est vous"), Bob voit aussi la boîte d'invitation avec les boutons Accepter et Refuser
        $this->assertSelectorExists('.family-received-invitations-box');
        $this->assertStringContainsString("Famille « Famille Visibilite {$suffix} »", $crawlerBob->filter('.family-received-invitations-box')->text());
        $this->assertSelectorExists(sprintf('form[action$="/famille/invitation/%d/accepter"]', $invitationBob->getId()));
        $this->assertSelectorExists(sprintf('form[action$="/famille/invitation/%d/refuser"]', $invitationBob->getId()));

        // 4. Bob accepte l'invitation
        $acceptForm = $crawlerBob->filter(sprintf('form[action$="/famille/invitation/%d/accepter"]', $invitationBob->getId()));
        $acceptToken = $acceptForm->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', sprintf('/famille/invitation/%d/accepter', $invitationBob->getId()), [
            '_token' => $acceptToken,
        ]);
        $this->assertResponseRedirects();
        $crawlerBobAccepted = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Maintenant Bob fait partie de la famille
        $this->assertStringContainsString('Bob Test', $crawlerBobAccepted->text());
        // Maintenant qu'il a accepté, Bob a le bouton "Quitter la famille"
        $this->assertSelectorExists('.family-leave-btn');
        // Mais Bob n'est pas chef, donc il ne voit toujours pas Charlie (en attente) !
        $this->assertStringNotContainsString("charlie_{$suffix}@test.com", $crawlerBobAccepted->text());

        // Nettoyage
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $freshEm->createQuery('DELETE FROM App\Entity\FamilleInvitation fi WHERE fi.famille = :famille')
            ->setParameter('famille', $famille)
            ->execute();
        $freshEm->createQuery('UPDATE App\Entity\User u SET u.famille = NULL WHERE u.id IN (:ids)')
            ->setParameter('ids', [$chef->getId(), $alice->getId(), $bob->getId()])
            ->execute();
        $freshEm->createQuery('UPDATE App\Entity\Famille f SET f.createur = NULL WHERE f.id = :id')
            ->setParameter('id', $famille->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Famille f WHERE f.id = :id')
            ->setParameter('id', $famille->getId())
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\User u WHERE u.id IN (:ids)')
            ->setParameter('ids', [$chef->getId(), $alice->getId(), $bob->getId()])
            ->execute();
    }

    public function testEmailEnvoiFallbackAndOverride(): void
    {
        $user = new User();
        $user->setEmail('principal@example.com');

        // Sans emailEnvoi renseigné, l'email d'envoi est l'adresse principale
        $this->assertNull($user->getEmailEnvoi());
        $this->assertSame('principal@example.com', $user->getEmailForMailer());

        // Avec emailEnvoi vide ou avec espaces, toujours l'adresse principale
        $user->setEmailEnvoi('   ');
        $this->assertNull($user->getEmailEnvoi());
        $this->assertSame('principal@example.com', $user->getEmailForMailer());

        // Avec emailEnvoi renseigné, c'est cette adresse qui est utilisée
        $user->setEmailEnvoi('autre_destination@example.com');
        $this->assertSame('autre_destination@example.com', $user->getEmailEnvoi());
        $this->assertSame('autre_destination@example.com', $user->getEmailForMailer());
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
}
