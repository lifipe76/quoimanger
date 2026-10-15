<?php

namespace App\Tests\Controller;

use App\Entity\Famille;
use App\Entity\RealisationNote;
use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RepasNotationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTimelineMealCardOpensRatingModalAndSavesRating(): void
    {
        // 1. Créer une famille, un utilisateur connecté, une recette et une réalisation de repas
        $famille = new Famille();
        $famille->setNom('Famille Test Notation Modal');
        $this->em->persist($famille);

        $user = new User();
        $user->setEmail('test_notation_' . uniqid() . '@example.com');
        $user->setFirstname('TestUser');
        $user->setFamille($famille);
        $user->setPassword('$2y$13$PPS2M4TsiFpSQlGXz/1kSeKllOnB2YFhJ6v6.pB9udTE.EHDyaiWy');
        $this->em->persist($user);

        $recette = new Recette();
        $recette->setDesignation('Risotto aux champignons ' . uniqid());
        $this->em->persist($recette);

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable());
        $realisation->setMoment('soir');
        $this->em->persist($realisation);

        $this->em->flush();

        // 2. Connexion et affichage de la timeline
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // 3. Vérifier la présence de la modale de notation sur la page
        $this->assertSelectorExists('#rate-meal-modal');
        $this->assertSelectorExists('#rate-meal-form');
        $this->assertSelectorExists('.rate-meal-stars-picker');

        // 4. Vérifier que la card du repas a l'étoile cliquable avec openRateMealModal
        $card = $crawler->filterXPath(sprintf('//div[contains(@class, "card-recette") and .//*[contains(text(), "%s")]]', $recette->getDesignation()));
        $this->assertGreaterThan(0, $card->count());

        $starsGroup = $card->filter('.card-recette-stars-group.is-clickable');
        $this->assertGreaterThan(0, $starsGroup->count());
        $this->assertStringContainsString('openRateMealModal', $starsGroup->attr('onclick'));

        $csrfToken = $starsGroup->attr('data-csrf');
        $this->assertNotEmpty($csrfToken);

        // 5. Soumission de la notation via le formulaire de la modale (note = 4)
        $this->client->request('POST', sprintf('/realisation/%d/rate', $realisation->getId()), [
            '_token' => $csrfToken,
            'note' => 4,
        ]);

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        // 6. Vérifier en base que la note a bien été enregistrée pour cet utilisateur
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $savedNote = $freshEm->getRepository(RealisationNote::class)->findOneBy([
            'realisation' => $realisation,
            'user' => $user,
        ]);
        $this->assertNotNull($savedNote);
        $this->assertSame(4, $savedNote->getNote());

        // 7. Vérifier que la timeline affiche maintenant la note mise à jour
        $crawlerAfter = $this->client->request('GET', '/');
        $cardAfter = $crawlerAfter->filterXPath(sprintf('//div[contains(@class, "card-recette") and .//*[contains(text(), "%s")]]', $recette->getDesignation()));
        $starsGroupAfter = $cardAfter->filter('.card-recette-stars-group.is-clickable');
        $this->assertStringContainsString('votre note : 4/5', $starsGroupAfter->attr('title'));

        // 8. Test de modification de la note à 5
        $newCsrf = $starsGroupAfter->attr('data-csrf');
        $this->client->request('POST', sprintf('/realisation/%d/rate', $realisation->getId()), [
            '_token' => $newCsrf,
            'note' => 5,
        ]);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        $freshEm->clear();
        $updatedNote = $freshEm->getRepository(RealisationNote::class)->findOneBy([
            'realisation' => $realisation,
            'user' => $user,
        ]);
        $this->assertNotNull($updatedNote);
        $this->assertSame(5, $updatedNote->getNote());

        // Nettoyage
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
        $freshEm->createQuery('DELETE FROM App\Entity\User u WHERE u.id = :id')
            ->setParameter('id', $user->getId())
            ->execute();
    }
}
