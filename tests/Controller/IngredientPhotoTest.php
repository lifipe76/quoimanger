<?php

namespace App\Tests\Controller;

use App\Entity\Ingredient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class IngredientPhotoTest extends WebTestCase
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

    public function testCreateIngredientWithPhoto(): void
    {
        $crawler = $this->client->request('GET', '/ingredient');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer l\'ingrédient')->form();
        $form['ingredient[designation]'] = 'Poivron Rouge Test Photo ' . uniqid();

        $filePath = tempnam(sys_get_temp_dir(), 'ingredient_img_');
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile = new UploadedFile($filePath, 'poivron.gif', 'image/gif', null, true);

        $values = $form->getPhpValues();
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile]);
        $this->assertResponseRedirects('/ingredients');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        /** @var Ingredient|null $ingredient */
        $ingredient = $this->em->getRepository(Ingredient::class)->findOneBy([], ['id' => 'DESC']);
        $this->assertNotNull($ingredient);
        $this->assertNotNull($ingredient->getPhoto());
        $this->assertStringStartsWith('ingredient_', $ingredient->getPhoto());

        $diskPath = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/ingredients/' . $ingredient->getPhoto();
        $this->assertFileExists($diskPath);

        // Nettoyage
        if (file_exists($diskPath)) {
            unlink($diskPath);
        }
    }

    public function testEditIngredientChangePhotoAndDeletePhoto(): void
    {
        // 1. Créer un ingrédient
        $ingredient = new Ingredient();
        $ingredient->setDesignation('Ingrédient Test Cycle Photo ' . uniqid());
        $this->em->persist($ingredient);
        $this->em->flush();

        $ingredientId = $ingredient->getId();

        // 2. Ajouter une photo
        $filePath1 = tempnam(sys_get_temp_dir(), 'ing1_');
        file_put_contents($filePath1, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile1 = new UploadedFile($filePath1, 'p1.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/ingredient/' . $ingredientId);
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $values = $form->getPhpValues();
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile1]);
        $this->assertResponseRedirects('/ingredients');

        $this->em->clear();
        $ingredient = $this->em->getRepository(Ingredient::class)->find($ingredientId);
        $photo1 = $ingredient->getPhoto();
        $this->assertNotNull($photo1);

        $photo1Path = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/ingredients/' . $photo1;
        $this->assertFileExists($photo1Path);

        // 3. Remplacer par une seconde photo : supprimer photo1 via delete_photos et uploader photo2
        $filePath2 = tempnam(sys_get_temp_dir(), 'ing2_');
        file_put_contents($filePath2, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile2 = new UploadedFile($filePath2, 'p2.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/ingredient/' . $ingredientId);
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $values = $form->getPhpValues();
        $values['delete_photos'] = [$photo1];
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile2]);
        $this->assertResponseRedirects('/ingredients');

        $this->em->clear();
        $ingredient = $this->em->getRepository(Ingredient::class)->find($ingredientId);
        $photo2 = $ingredient->getPhoto();
        $this->assertNotNull($photo2);
        $this->assertNotEquals($photo1, $photo2);

        $this->assertFileDoesNotExist($photo1Path);
        $photo2Path = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/ingredients/' . $photo2;
        $this->assertFileExists($photo2Path);

        // 4. Supprimer la photo via delete_photo = 1
        $crawler = $this->client->request('GET', '/ingredient/' . $ingredientId);
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $values = $form->getPhpValues();
        $values['delete_photo'] = '1';
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/ingredients');

        $this->em->clear();
        $ingredient = $this->em->getRepository(Ingredient::class)->find($ingredientId);
        $this->assertNull($ingredient->getPhoto());
        $this->assertFileDoesNotExist($photo2Path);
    }

    public function testUploadMultiplePhotosAndSelectiveDeletion(): void
    {
        $ingredient = new Ingredient();
        $ingredient->setDesignation('Ingrédient Multi Photos ' . uniqid());
        $this->em->persist($ingredient);
        $this->em->flush();

        $ingredientId = $ingredient->getId();

        $filePathA = tempnam(sys_get_temp_dir(), 'multi_a_');
        file_put_contents($filePathA, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $fileA = new UploadedFile($filePathA, 'a.gif', 'image/gif', null, true);

        $filePathB = tempnam(sys_get_temp_dir(), 'multi_b_');
        file_put_contents($filePathB, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $fileB = new UploadedFile($filePathB, 'b.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/ingredient/' . $ingredientId);
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $values = $form->getPhpValues();

        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photos' => [$fileA, $fileB]]);
        $this->assertResponseRedirects('/ingredients');

        $this->em->clear();
        $ingredient = $this->em->getRepository(Ingredient::class)->find($ingredientId);
        $photos = $ingredient->getPhotos();
        $this->assertCount(2, $photos);

        $pathA = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/ingredients/' . $photos[0];
        $pathB = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/ingredients/' . $photos[1];
        $this->assertFileExists($pathA);
        $this->assertFileExists($pathB);

        // Suppression sélective de la première photo
        $values['delete_photos'] = [$photos[0]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/ingredients');

        $this->em->clear();
        $ingredient = $this->em->getRepository(Ingredient::class)->find($ingredientId);
        $remainingPhotos = $ingredient->getPhotos();
        $this->assertCount(1, $remainingPhotos);
        $this->assertEquals($photos[1], $remainingPhotos[0]);
        $this->assertFileDoesNotExist($pathA);
        $this->assertFileExists($pathB);

        // Nettoyage disque
        if (file_exists($pathB)) {
            unlink($pathB);
        }
    }

    public function testDeleteIngredientRemovesPhotoFromDisk(): void
    {
        $ingredient = new Ingredient();
        $ingredient->setDesignation('Ingrédient Test Suppression Photo ' . uniqid());
        $this->em->persist($ingredient);
        $this->em->flush();

        $ingredientId = $ingredient->getId();

        // Uploader une photo
        $filePath = tempnam(sys_get_temp_dir(), 'ing_del_');
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile = new UploadedFile($filePath, 'del.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/ingredient/' . $ingredientId);
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $values = $form->getPhpValues();
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile]);

        $this->em->clear();
        $ingredient = $this->em->getRepository(Ingredient::class)->find($ingredientId);
        $photo = $ingredient->getPhoto();
        $this->assertNotNull($photo);

        $photoPath = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/ingredients/' . $photo;
        $this->assertFileExists($photoPath);

        // Supprimer l'ingrédient
        $deleteToken = $this->getCsrfToken('delete_ingredient_' . $ingredientId);
        $this->client->request(
            'POST',
            '/ingredient/' . $ingredientId . '/delete',
            [
                '_token' => $deleteToken,
            ]
        );

        $this->assertResponseRedirects('/ingredients');
        $this->assertFileDoesNotExist($photoPath);
    }

    public function testIngredientPhotoDisplayedInCard(): void
    {
        $ingredient = new Ingredient();
        $ingredient->setDesignation('Basilic Test Card ' . uniqid());
        $ingredient->setPhoto('sample_photo.jpg');
        $this->em->persist($ingredient);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();

        // Vérifier la présence de l'image de l'ingrédient dans la page
        $this->assertGreaterThan(0, $crawler->filter('img[src="/photos/ingredients/sample_photo.jpg"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('.card-ingredient-photo-wrap img.card-ingredient-bg-photo')->count());
    }

    public function testIngredientCardElementsAndRank(): void
    {
        // Créer une recette et un ingrédient lié
        $recette = new \App\Entity\Recette();
        $recette->setDesignation('Pâtes au Pesto Test ' . uniqid());
        $this->em->persist($recette);

        $ingredient = new Ingredient();
        $ingredient->setDesignation('Pignons de pin ' . uniqid());
        $ingredient->setPhoto('pignons.jpg');
        $this->em->persist($ingredient);

        $recetteIngredient = new \App\Entity\RecetteIngredient();
        $recetteIngredient->setRecette($recette);
        $recetteIngredient->setIngredient($ingredient);
        $recetteIngredient->setQuantite('50g');
        $this->em->persist($recetteIngredient);

        $this->em->flush();

        $crawler = $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();

        // Vérifier le header gauche : bulle avec nombre de recettes
        $statusNodes = $crawler->filter('.card-ingredient-status');
        $this->assertGreaterThan(0, $statusNodes->count());
        $this->assertStringContainsString('recette', $statusNodes->first()->text());

        // Vérifier le header droit : rang et bouton crayon
        $rankNodes = $crawler->filter('.card-ingredient-rank');
        $this->assertGreaterThan(0, $rankNodes->count());
        $this->assertStringStartsWith('#', trim($rankNodes->first()->text()));

        $pencilNodes = $crawler->filter('.card-btn-pencil');
        $this->assertGreaterThan(0, $pencilNodes->count());
        $this->assertStringContainsString('✏️', $pencilNodes->first()->text());

        // Vérifier le corps avec les chips de recettes
        $recipeChips = $crawler->filter('.recipe-chip:contains("Pâtes au Pesto")');
        $this->assertGreaterThan(0, $recipeChips->count());
    }

    public function testEditIngredientFormHasSmallTrashButton(): void
    {
        $ingredient = new Ingredient();
        $ingredient->setDesignation('Ail Frais ' . uniqid());
        $this->em->persist($ingredient);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/ingredient/' . $ingredient->getId());
        $this->assertResponseIsSuccessful();

        // Vérifier le bouton poubelle en mode édition
        $deleteBtn = $crawler->filter('button[form="delete-ingredient-form"].btn-delete-small');
        $this->assertCount(1, $deleteBtn);
        $this->assertStringContainsString('🗑️', $deleteBtn->text());
        $this->assertStringNotContainsString("Supprimer l'ingrédient", $deleteBtn->text());
    }

    public function testIngredientCardPhotoSliderRenderWithMultiplePhotos(): void
    {
        $ingredient = new Ingredient();
        $ingredient->setDesignation('Basilic Slider ' . uniqid());
        $ingredient->setPhotos(['ing1.jpg', 'ing2.jpg']);
        $this->em->persist($ingredient);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();

        $slider = $crawler->filter('.card-ingredient .card-photo-slider[data-photos*="ing1.jpg"]');
        $this->assertGreaterThanOrEqual(1, $slider->count());
        $this->assertGreaterThanOrEqual(1, $slider->filter('.card-photo-arrow-prev')->count());
        $this->assertGreaterThanOrEqual(1, $slider->filter('.card-photo-arrow-next')->count());
        $this->assertGreaterThanOrEqual(1, $slider->filter('.card-photo-nav-group')->count());
        $this->assertGreaterThanOrEqual(2, $slider->filter('.card-photo-dot')->count());

        // Nettoyage
        $this->em->remove($ingredient);
        $this->em->flush();
    }

    public function testIngredientCardPlaceholderRenderWhenNoPhoto(): void
    {
        $ingredient = new Ingredient();
        $ingredient->setDesignation('Ingrédient Sans Photo ' . uniqid());
        $this->em->persist($ingredient);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();

        // Sur le catalogue ingrédients, une carte sans photo doit avoir le placeholder flou
        $card = $crawler->filterXPath("//div[contains(@class, 'card-ingredient')][.//a[contains(text(), '{$ingredient->getDesignation()}')]]");
        $this->assertGreaterThanOrEqual(1, $card->count());
        $this->assertGreaterThanOrEqual(1, $card->filter('.card-photo-placeholder')->count());
        $this->assertGreaterThanOrEqual(1, $card->filter('.card-photo-placeholder-bg')->count());

        // Nettoyage
        $this->em->remove($ingredient);
        $this->em->flush();
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
