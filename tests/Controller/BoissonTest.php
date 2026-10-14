<?php

namespace App\Tests\Controller;

use App\Entity\Boisson;
use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\RepasBoisson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BoissonTest extends WebTestCase
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

    public function testDefaultIngredientImageIsDistinctFromMeal(): void
    {
        $ingredientTemplate = file_get_contents(
            static::getContainer()->getParameter('kernel.project_dir') . '/templates/cards/_ingredient/ingredient.html.twig'
        );
        $this->assertStringContainsString('default_ingredient.webp', $ingredientTemplate);
        $this->assertStringNotContainsString('default_food.webp', $ingredientTemplate);

        $ingredientImage = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/images/default_ingredient.webp';
        $mealImage = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/images/default_food.webp';

        $this->assertFileExists($ingredientImage);
        $this->assertFileExists($mealImage);
        $this->assertNotEquals(sha1_file($ingredientImage), sha1_file($mealImage), 'L\'image par défaut des ingrédients doit être différente de celle des repas.');
    }

    public function testBoissonsListPage(): void
    {
        $this->client->request('GET', '/boissons');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.page-title', 'Mes Boissons');
    }

    public function testCreateBoissonWithPhotoAndLieuAchat(): void
    {
        $crawler = $this->client->request('GET', '/boisson');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer la boisson')->form();
        $uniqueName = 'Château Test ' . uniqid();
        $form['boisson[nom]'] = $uniqueName;
        $form['boisson[lieuAchat]'] = 'Cave Millésime';
        $form['boisson[description]'] = 'Vin rouge équilibré avec arômes de fruits rouges.';

        $filePath = tempnam(sys_get_temp_dir(), 'boisson_img_');
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile = new UploadedFile($filePath, 'bouteille.gif', 'image/gif', null, true);

        $values = $form->getPhpValues();
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photos' => [$uploadedFile]]);

        $this->assertResponseRedirects('/boissons');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        /** @var Boisson|null $boisson */
        $boisson = $this->em->getRepository(Boisson::class)->findOneBy(['nom' => $uniqueName]);
        $this->assertNotNull($boisson);
        $this->assertSame('Cave Millésime', $boisson->getLieuAchat());
        $this->assertNotEmpty($boisson->getPhotos());
        $this->assertStringStartsWith('boisson_', $boisson->getPhoto());

        $diskPath = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/boissons/' . $boisson->getPhoto();
        $this->assertFileExists($diskPath);

        if (file_exists($diskPath)) {
            unlink($diskPath);
        }
    }

    public function testEditMealAddBoissonDateAchatAndNote(): void
    {
        // 1. Créer une recette
        $recette = new Recette();
        $recette->setDesignation('Plat Test Boisson ' . uniqid());
        $this->em->persist($recette);

        // 2. Créer une réalisation (repas)
        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable('today'));
        $realisation->setMoment('soir');
        $this->em->persist($realisation);

        // 3. Créer une boisson
        $boisson = new Boisson();
        $boisson->setNom('Bière IPA Artisanale ' . uniqid());
        $boisson->setLieuAchat('Brasserie Locale');
        $this->em->persist($boisson);

        $this->em->flush();

        // 4. Modifier le repas pour lui associer la boisson avec date d'achat et note sur 5
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $token = $this->getCsrfToken('update_realisation_date_' . $realisation->getId());

        $this->client->request('POST', '/realisation/' . $realisation->getId() . '/update-date', [
            '_token' => $token,
            'realise_at' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'moment' => 'soir',
            'boisson_id' => $boisson->getId(),
            'boisson_date_achat' => '2026-10-10',
            'boisson_note' => 5,
        ]);

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->em->clear();

        /** @var RecetteRealisation|null $updatedRealisation */
        $updatedRealisation = $this->em->getRepository(RecetteRealisation::class)->find($realisation->getId());
        $this->assertNotNull($updatedRealisation);

        $repasBoisson = $updatedRealisation->getRepasBoisson();
        $this->assertNotNull($repasBoisson);
        $this->assertSame($boisson->getId(), $repasBoisson->getBoisson()?->getId());
        $this->assertSame('2026-10-10', $repasBoisson->getDateAchat()?->format('Y-m-d'));
        $this->assertSame(5, $repasBoisson->getNote());

        // Vérifier que la note moyenne de la boisson est bien calculée
        $updatedBoisson = $this->em->getRepository(Boisson::class)->find($boisson->getId());
        $this->assertSame(5.0, $updatedBoisson->getAverageNote());
        $this->assertSame(1, $updatedBoisson->getNotesCount());

        // Vérifier l'affichage du badge boisson sur la page d'accueil
        $this->assertSelectorExists('.card-recette-boisson-badge');
        $this->assertSelectorTextContains('.card-recette-boisson-badge', $boisson->getNom());
        $this->assertSelectorTextContains('.card-recette-boisson-badge', '5/5');

        // 5. Retirer la boisson du repas
        $tokenClear = $this->getCsrfToken('update_realisation_date_' . $realisation->getId());
        $this->client->request('POST', '/realisation/' . $realisation->getId() . '/update-date', [
            '_token' => $tokenClear,
            'realise_at' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'moment' => 'soir',
            'boisson_id' => '',
        ]);

        $this->em->clear();
        $updatedRealisation = $this->em->getRepository(RecetteRealisation::class)->find($realisation->getId());
        $this->assertNull($updatedRealisation->getRepasBoisson());
    }

    public function testMenuContainsBoissonsEntry(): void
    {
        $this->client->request('GET', '/boissons');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-links-main a[href="/boissons"]');
        $this->assertSelectorTextContains('.nav-links-main a[href="/boissons"]', 'Boissons');
        $this->assertSelectorExists('.nav-links-main a[href="/boissons"].active');
    }

    public function testDeleteBoisson(): void
    {
        $boisson = new Boisson();
        $boisson->setNom('Boisson à Supprimer ' . uniqid());
        $this->em->persist($boisson);
        $this->em->flush();
        $boissonId = $boisson->getId();

        $crawler = $this->client->request('GET', '/boisson/' . $boissonId);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('#delete-boisson-form')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/boissons');
        $this->client->followRedirect();

        $this->em->clear();
        $this->assertNull($this->em->getRepository(Boisson::class)->find($boissonId));
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
