<?php

namespace App\Tests\Controller;

use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TimelineAndModalTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if ($user) {
            $this->client->loginUser($user);
        }
    }

    public function testTimelineCardDoesNotDisplayDegusteLeAndHidesIngredients(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // 1. Page title "Timeline des Repas" should be removed
        $this->assertSelectorNotExists('.page-title');
        $this->assertSelectorNotExists('.page-subtitle');

        // 1b. CTA marker has cutlery icon and not AI star; empty-cta-icon is removed
        $this->assertSelectorExists('.timeline-marker.cta-marker .marker-icon');
        $ctaIcon = $crawler->filter('.timeline-marker.cta-marker .marker-icon')->text();
        $this->assertStringContainsString('🍴', $ctaIcon);
        $this->assertStringNotContainsString('✨', $ctaIcon);
        $this->assertSelectorNotExists('.card-empty-cta .empty-cta-icon');

        // 2. "Dégusté le" should NOT be present inside timeline cards
        $this->assertStringNotContainsString('Dégusté le', $this->client->getResponse()->getContent());

        // 3. Ingredients chips should NOT be in timeline cards
        $this->assertSelectorNotExists('.timeline-wrapper .card-recette-chips');
        $this->assertSelectorNotExists('.timeline-wrapper .card-recette-ingredients-title');

        // 4. Interval bubble should exist and NOT have AI icons (✨ or ⏱️)
        $this->assertSelectorExists('.card-recette-status.realisee-bubble');
        $bubbleText = $crawler->filter('.card-recette-status.realisee-bubble')->first()->text();
        $this->assertStringNotContainsString('✨', $bubbleText);
        $this->assertStringNotContainsString('⏱️', $bubbleText);

        // 5. Moment badge (matin / midi / soir) should exist on card
        $this->assertSelectorExists('.card-recette-moment');

        // 6. Pencil button exists on card to edit meal
        $this->assertSelectorExists('.card-btn-pencil');

        // 7. Footer (Ajoutée, Date, Modifier, ✕) should NOT be on timeline cards
        $this->assertSelectorNotExists('.timeline-day-meals .card-recette-footer');
        $this->assertSelectorNotExists('.timeline-day-meals .card-btn-date');
        $this->assertSelectorNotExists('.timeline-day-meals .card-btn-edit');

        // 8. Pencil should NOT be in the timeline day/date badge
        $this->assertSelectorNotExists('.badge-edit-pencil');

        // 9. Edit meal modal exists with date input of type date (no hour), bold recipe name in title, and moment selector
        $this->assertSelectorExists('#edit-meal-modal');
        $this->assertSelectorExists('#edit-meal-modal input#edit-meal-date-input[type="date"]');
        $this->assertSelectorExists('#edit-meal-modal input#edit-meal-complement-input');
        $this->assertSelectorExists('#edit-meal-modal #edit-meal-recipe-name');
        $this->assertSelectorExists('#edit-meal-modal .btn-edit-moment-toggle');
        $this->assertSelectorExists('#edit-meal-modal .btn-delete-meal');

        // 9b. Dedicated Complement modal exists
        $this->assertSelectorExists('#complement-modal');
        $this->assertSelectorExists('#complement-modal #complement-input');
        $this->assertSelectorExists('#complement-modal #complement-modal-recipe-name');

        // 10. Popup "Je rentre mon repas" has no title, no date input, unified search + moment top-bar
        $this->assertSelectorExists('#repas-modal');
        $this->assertSelectorNotExists('#repas-modal .modal-title');
        $this->assertSelectorNotExists('#repas-modal .modal-subtitle');
        $this->assertSelectorNotExists('#repas-modal input#modal-repas-date');
        $this->assertSelectorExists('#repas-modal .modal-top-bar');
        $this->assertSelectorExists('#repas-modal .btn-moment-toggle');
        $this->assertSelectorExists('.badge-rank');
        $this->assertSelectorExists('.badge-eaten-count');

        // 11. Day marker circles are removed, and timeline day badge contains day name (Lundi, Mardi...)
        $this->assertSelectorNotExists('.timeline-day-header .day-marker');
        $this->assertSelectorExists('.timeline-day-badge');
        $dayBadge = $crawler->filter('.timeline-day-badge')->text();
        $this->assertMatchesRegularExpression('/(Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche)/', $dayBadge);
    }

    public function testRecipeCardStatusBadgeInCatalogue(): void
    {
        // Créer une recette avec 2 réalisations
        $recette = new Recette();
        $recette->setDesignation('Tarte aux pommes catalogue test');
        $this->em->persist($recette);

        $r1 = new RecetteRealisation();
        $r1->setRecette($recette);
        $r1->setRealiseAt(new \DateTimeImmutable('-5 days'));
        $recette->addRealisation($r1);
        $this->em->persist($r1);

        $r2 = new RecetteRealisation();
        $r2->setRecette($recette);
        $r2->setRealiseAt(new \DateTimeImmutable('-2 days'));
        $recette->addRealisation($r2);
        $this->em->persist($r2);

        $this->em->flush();

        $crawler = $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        // Vérifier qu'on affiche "Réalisée 2 fois" et non "(2x)"
        $this->assertStringContainsString('Réalisée 2 fois', $content);
        $this->assertStringNotContainsString('(2x)', $content);
        // Vérifier l'indicateur "Il y a 2 jours"
        $this->assertStringContainsString('Il y a 2 jours', $content);

        // Nettoyage
        $this->em->remove($r2);
        $this->em->remove($r1);
        $this->em->remove($recette);
        $this->em->flush();
    }

    public function testUpdateRealisationDateAndMomentEndpoint(): void
    {
        $recette = $this->em->getRepository(Recette::class)->findOneBy([]);
        if (!$recette) {
            $recette = new Recette();
            $recette->setDesignation('Plat de test');
            $this->em->persist($recette);
            $this->em->flush();
        }

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable('2026-10-01'));
        $realisation->setMoment('midi');
        $this->em->persist($realisation);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $button = $crawler->filter(sprintf('.card-btn-pencil[onclick*="/realisation/%d/update-date"]', $realisation->getId()));
        $this->assertGreaterThan(0, $button->count(), 'Pencil button for realization should exist');
        $onclick = $button->attr('onclick');
        preg_match_all("/'([^']*)'/", $onclick, $tokenMatches);
        $token = $tokenMatches[1][4] ?? '';

        // Update date to 5 days earlier with Y-m-d format and moment to 'soir'
        $newTargetDate = '2026-09-26';
        $this->client->request('POST', sprintf('/realisation/%d/update-date', $realisation->getId()), [
            '_token' => $token,
            'realise_at' => $newTargetDate,
            'moment' => 'soir',
        ]);

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        $this->assertSelectorExists('.alert-success');
        $this->assertStringContainsString('26/09/2026', $this->client->getResponse()->getContent());

        $realisationId = $realisation->getId();

        // Refresh from DB using fresh EntityManager
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $updatedRealisation = $freshEm->find(RecetteRealisation::class, $realisationId);
        $this->assertNotNull($updatedRealisation);
        $this->assertEquals('2026-09-26', $updatedRealisation->getRealiseAt()->format('Y-m-d'));
        $this->assertEquals('soir', $updatedRealisation->getMoment());

        // Clean up
        $freshEm->remove($updatedRealisation);
        $freshEm->flush();
    }

    public function testIntervalLabelCalculation(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Gâteau au chocolat test');
        $this->em->persist($recette);

        // Meal 1
        $r1 = new RecetteRealisation();
        $r1->setRecette($recette);
        $r1->setRealiseAt(new \DateTimeImmutable('2026-09-01 12:00:00'));
        $recette->addRealisation($r1);
        $this->em->persist($r1);

        // Meal 2 (15 days later)
        $r2 = new RecetteRealisation();
        $r2->setRecette($recette);
        $r2->setRealiseAt(new \DateTimeImmutable('2026-09-16 12:00:00'));
        $recette->addRealisation($r2);
        $this->em->persist($r2);

        $this->em->flush();

        // R1 is the first time
        $this->assertEquals('1ère fois', $r1->getIntervalLabel());

        // R2 was 15 days later
        $this->assertEquals(15, $r2->getDaysSincePreviousRealisation());
        $this->assertEquals('Ça faisait 15 jours', $r2->getIntervalLabel());

        // Clean up
        $this->em->remove($r2);
        $this->em->remove($r1);
        $this->em->remove($recette);
        $this->em->flush();
    }

    public function testUpdateRealisationComplementAndPopup(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Blanquette de veau test');
        $this->em->persist($recette);

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable('now'));
        $realisation->setMoment('midi');
        $this->em->persist($realisation);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // 1. Sur la timeline, le titre pointe vers la recette et le bouton + ouvre le modal de complément
        $titleLink = $crawler->filter(sprintf('.card-recette-title a[href="/recette/%d"]', $recette->getId()));
        $this->assertGreaterThan(0, $titleLink->count(), 'Timeline card title should link to the recipe');
        $this->assertStringContainsString('Blanquette de veau test', $titleLink->text());

        $addBtn = $crawler->filter(sprintf('.card-recette-btn-complement-add[onclick*="/realisation/%d/update-complement"]', $realisation->getId()));
        $this->assertGreaterThan(0, $addBtn->count(), 'Timeline card should have a + button to add complement');

        // Récupérer le token CSRF pour update_complement
        $onclick = $addBtn->attr('onclick');
        preg_match_all("/'([^']*)'/", $onclick, $tokenMatches);
        $token = $tokenMatches[1][3] ?? '';

        // 2. Mettre à jour le complément avec "riz"
        $this->client->request('POST', sprintf('/realisation/%d/update-complement', $realisation->getId()), [
            '_token' => $token,
            'complement' => 'riz',
        ]);
        $this->assertResponseRedirects('/');
        $crawlerAfter = $this->client->followRedirect();

        // 3. Vérifier le flash message et l'affichage dans la page
        $this->assertSelectorExists('.alert-success');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Complément mis à jour', $content);
        $this->assertStringContainsString('(riz)', $content);

        // Vérifier que le texte d'accompagnement est présent et cliquable
        $complementBtn = $crawlerAfter->filter(sprintf('.card-recette-complement-text[onclick*="/realisation/%d/update-complement"]', $realisation->getId()));
        $this->assertGreaterThan(0, $complementBtn->count(), 'Complement text should be clickable to update complement');

        // 4. Vérifier en base que la recette reste "Blanquette de veau test" et que le complément est "riz"
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $updatedRealisation = $freshEm->find(RecetteRealisation::class, $realisation->getId());
        $this->assertNotNull($updatedRealisation);
        $this->assertEquals('riz', $updatedRealisation->getComplement());
        $this->assertEquals('Blanquette de veau test', $updatedRealisation->getRecette()->getDesignation());

        // 5. Retirer le complément (texte vide)
        $this->client->request('POST', sprintf('/realisation/%d/update-complement', $realisation->getId()), [
            '_token' => $token,
            'complement' => '',
        ]);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        $updatedRealisation2 = $freshEm->find(RecetteRealisation::class, $realisation->getId());
        $this->assertNull($updatedRealisation2->getComplement());

        // Clean up
        $freshEm->remove($updatedRealisation2);
        $recetteToDelete = $freshEm->find(Recette::class, $recette->getId());
        if ($recetteToDelete) {
            $freshEm->remove($recetteToDelete);
        }
        $freshEm->flush();
    }

    public function testAddRealisationDisplaysToastNotificationWithCountdown(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Quiche lorraine toast test');
        $this->em->persist($recette);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $tokenInput = $crawler->filter('form.modal-recipe-form input[name="_token"]')->first();
        $token = $tokenInput->attr('value');

        // Enregistrer le repas
        $this->client->request('POST', '/realisation/add', [
            'recette_id' => $recette->getId(),
            'moment' => 'soir',
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/');
        $crawlerAfter = $this->client->followRedirect();

        // Vérifier la présence du toast avec le décompte
        $this->assertSelectorExists('.toast-container');
        $this->assertSelectorExists('.toast-notification.toast-success.alert.alert-success');
        $this->assertSelectorExists('.toast-notification .toast-countdown');
        $this->assertSelectorExists('.toast-notification .toast-progress-bar');
        $this->assertSelectorExists('.toast-notification .toast-close-btn');

        $toastText = $crawlerAfter->filter('.toast-notification .toast-message')->text();
        $this->assertStringContainsString('Repas enregistré', $toastText);
        $this->assertStringContainsString('Quiche lorraine toast test', $toastText);

        $countdownText = $crawlerAfter->filter('.toast-notification .toast-countdown')->text();
        $this->assertMatchesRegularExpression('/\d+s/', $countdownText);

        // Nettoyage en base
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $realisations = $freshEm->getRepository(RecetteRealisation::class)->findBy(['recette' => $recette->getId()]);
        foreach ($realisations as $r) {
            $freshEm->remove($r);
        }
        $recetteToRemove = $freshEm->find(Recette::class, $recette->getId());
        if ($recetteToRemove) {
            $freshEm->remove($recetteToRemove);
        }
        $freshEm->flush();
    }

    public function testPopupAndToastComponentCssAndJsInjected(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // 1. Toast CSS & JS
        $this->assertStringContainsString('.toast-container', $content);
        $this->assertStringContainsString('toastSlideInRight', $content);
        $this->assertStringContainsString('dismissToast', $content);

        // 2. Base Popup component CSS & JS
        $this->assertStringContainsString('.modal-overlay', $content);
        $this->assertStringContainsString('fadeInModal', $content);
        $this->assertStringContainsString('openModal', $content);
        $this->assertStringContainsString('closeModal', $content);

        // 3. Branches CSS & JS
        $this->assertStringContainsString('.modal-top-bar', $content);
        $this->assertStringContainsString('filterRecipesList', $content);
        $this->assertStringContainsString('.btn-edit-moment-toggle', $content);
        $this->assertStringContainsString('openEditMealModal', $content);
        $this->assertStringContainsString('openComplementModal', $content);

        // 4. Container & Home Component CSS
        $this->assertStringContainsString('--primary: #db4807', $content);
        $this->assertStringContainsString('.timeline-wrapper', $content);
    }

    public function testRecettesAndIngredientsPagesUseComponentsWithoutRecettesCss(): void
    {
        // 1. Page Recettes
        $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();
        $recettesContent = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('--primary: #db4807', $recettesContent);
        $this->assertStringContainsString('.card-recette', $recettesContent);
        $this->assertStringNotContainsString('addCss', $recettesContent);

        // 2. Page Ingrédients
        $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();
        $ingredientsContent = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('--primary: #db4807', $ingredientsContent);
        $this->assertStringContainsString('.card-ingredient', $ingredientsContent);
        $this->assertStringNotContainsString('addCss', $ingredientsContent);
    }

    public function testMealSuggestionAndTopRowLayout(): void
    {
        // 1. Créer une recette spécifique pour le test de suggestion
        $recette = new \App\Entity\Recette();
        $recette->setDesignation('Poulet rôti du dimanche test');
        $this->em->persist($recette);

        // Réalisation passée il y a 21 jours (même jour de semaine)
        $today = new \DateTimeImmutable('today');
        $pastDate = $today->modify('-21 days');
        $realisation = new \App\Entity\RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt($pastDate);
        $realisation->setMoment('midi');
        $this->em->persist($realisation);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // 2. Vérifier que la première ligne contient bien le titre et le bouton sur la même ligne
        $this->assertSelectorExists('.cta-top-row');
        $this->assertSelectorTextContains('.cta-top-row .empty-cta-title', "Qu'avez-vous mangé aujourd'hui ?");
        $this->assertSelectorTextContains('.cta-top-row button', 'Je rentre mon repas');

        // 3. Vérifier que le bloc suggestion est rendu
        $this->assertSelectorExists('.cta-suggestion-row');
        $this->assertSelectorExists('.cta-suggestion-row form.suggestion-form');
        $this->assertSelectorExists('.cta-suggestion-row .suggestion-btn');

        // 4. Cliquer / soumettre la suggestion pour l'enregistrer comme mangé
        $form = $crawler->filter('.cta-suggestion-row form.suggestion-form')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        $this->assertSelectorExists('.alert-success');
        $this->assertStringContainsString('Repas enregistré avec succès', $this->client->getResponse()->getContent());

        // Nettoyage
        $freshEm = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $freshEm->createQuery('DELETE FROM App\Entity\RecetteRealisation r WHERE r.recette = :recette')
            ->setParameter('recette', $recette)
            ->execute();
        $freshEm->createQuery('DELETE FROM App\Entity\Recette r WHERE r.id = :id')
            ->setParameter('id', $recette->getId())
            ->execute();
    }
}
