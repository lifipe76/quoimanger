<?php

namespace App\Tests\Controller;

use App\Entity\Recette;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RecettePhotoTest extends WebTestCase
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

    public function testCreateRecetteWithPhoto(): void
    {
        $crawler = $this->client->request('GET', '/recette');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer la recette')->form();
        $form['recette[designation]'] = 'Tarte aux Fraises Test Photo ' . uniqid();

        $filePath = tempnam(sys_get_temp_dir(), 'recette_img_');
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile = new UploadedFile($filePath, 'tarte.gif', 'image/gif', null, true);

        $values = $form->getPhpValues();
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile]);
        $this->assertResponseRedirects('/recettes');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        /** @var Recette|null $recette */
        $recette = $this->em->getRepository(Recette::class)->findOneBy([], ['id' => 'DESC']);
        $this->assertNotNull($recette);
        $this->assertNotNull($recette->getPhoto());
        $this->assertStringStartsWith('recette_', $recette->getPhoto());

        $diskPath = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/recettes/' . $recette->getPhoto();
        $this->assertFileExists($diskPath);

        // Nettoyage
        if (file_exists($diskPath)) {
            unlink($diskPath);
        }
    }

    public function testEditRecetteChangePhotoAndDeletePhoto(): void
    {
        // 1. Créer une recette
        $recette = new Recette();
        $recette->setDesignation('Plat Test Cycle Photo ' . uniqid());
        $this->em->persist($recette);
        $this->em->flush();

        $recetteId = $recette->getId();

        // 2. Ajouter une photo
        $filePath1 = tempnam(sys_get_temp_dir(), 'recette1_');
        file_put_contents($filePath1, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile1 = new UploadedFile($filePath1, 'p1.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/recette/' . $recetteId);
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer la recette')->form();
        $values = $form->getPhpValues();
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile1]);
        $this->assertResponseRedirects('/recettes');

        $this->em->clear();
        $recette = $this->em->getRepository(Recette::class)->find($recetteId);
        $photo1 = $recette->getPhoto();
        $this->assertNotNull($photo1);

        $photo1Path = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/recettes/' . $photo1;
        $this->assertFileExists($photo1Path);

        // 3. Remplacer par une seconde photo : supprimer photo1 via delete_photos et uploader photo2
        $filePath2 = tempnam(sys_get_temp_dir(), 'recette2_');
        file_put_contents($filePath2, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile2 = new UploadedFile($filePath2, 'p2.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/recette/' . $recetteId);
        $form = $crawler->selectButton('Enregistrer la recette')->form();
        $values = $form->getPhpValues();
        $values['delete_photos'] = [$photo1];
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile2]);
        $this->assertResponseRedirects('/recettes');

        $this->em->clear();
        $recette = $this->em->getRepository(Recette::class)->find($recetteId);
        $photo2 = $recette->getPhoto();
        $this->assertNotNull($photo2);
        $this->assertNotEquals($photo1, $photo2);

        $this->assertFileDoesNotExist($photo1Path);
        $photo2Path = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/recettes/' . $photo2;
        $this->assertFileExists($photo2Path);

        // 4. Supprimer la photo via delete_photo = 1
        $crawler = $this->client->request('GET', '/recette/' . $recetteId);
        $form = $crawler->selectButton('Enregistrer la recette')->form();
        $values = $form->getPhpValues();
        $values['delete_photo'] = '1';
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/recettes');

        $this->em->clear();
        $recette = $this->em->getRepository(Recette::class)->find($recetteId);
        $this->assertNull($recette->getPhoto());
        $this->assertFileDoesNotExist($photo2Path);
    }

    public function testUploadMultiplePhotosForRecette(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Recette Multi Photos ' . uniqid());
        $this->em->persist($recette);
        $this->em->flush();

        $recetteId = $recette->getId();

        $filePathA = tempnam(sys_get_temp_dir(), 'rc_multi_a_');
        file_put_contents($filePathA, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $fileA = new UploadedFile($filePathA, 'ra.gif', 'image/gif', null, true);

        $filePathB = tempnam(sys_get_temp_dir(), 'rc_multi_b_');
        file_put_contents($filePathB, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $fileB = new UploadedFile($filePathB, 'rb.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/recette/' . $recetteId);
        $form = $crawler->selectButton('Enregistrer la recette')->form();
        $values = $form->getPhpValues();

        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photos' => [$fileA, $fileB]]);
        $this->assertResponseRedirects('/recettes');

        $this->em->clear();
        $recette = $this->em->getRepository(Recette::class)->find($recetteId);
        $photos = $recette->getPhotos();
        $this->assertCount(2, $photos);

        $pathA = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/recettes/' . $photos[0];
        $pathB = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/recettes/' . $photos[1];
        $this->assertFileExists($pathA);
        $this->assertFileExists($pathB);

        // Suppression sélective
        $values['delete_photos'] = [$photos[0]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/recettes');

        $this->em->clear();
        $recette = $this->em->getRepository(Recette::class)->find($recetteId);
        $remaining = $recette->getPhotos();
        $this->assertCount(1, $remaining);
        $this->assertEquals($photos[1], $remaining[0]);
        $this->assertFileDoesNotExist($pathA);
        $this->assertFileExists($pathB);

        if (file_exists($pathB)) {
            unlink($pathB);
        }
    }

    public function testDeleteRecetteRemovesPhotoFromDisk(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Plat Test Suppression Photo ' . uniqid());
        $this->em->persist($recette);
        $this->em->flush();

        $recetteId = $recette->getId();

        // Uploader une photo
        $filePath = tempnam(sys_get_temp_dir(), 'recette_del_');
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile = new UploadedFile($filePath, 'del.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/recette/' . $recetteId);
        $form = $crawler->selectButton('Enregistrer la recette')->form();
        $values = $form->getPhpValues();
        $this->client->request($form->getMethod(), $form->getUri(), $values, ['photo' => $uploadedFile]);

        $this->em->clear();
        $recette = $this->em->getRepository(Recette::class)->find($recetteId);
        $photo = $recette->getPhoto();
        $this->assertNotNull($photo);

        $photoPath = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/recettes/' . $photo;
        $this->assertFileExists($photoPath);

        // Supprimer la recette
        $this->client->request('GET', '/recette/' . $recetteId);
        $deleteToken = $this->getCsrfToken('delete_recette_' . $recetteId);
        $this->client->request(
            'POST',
            '/recette/' . $recetteId . '/delete',
            [
                '_token' => $deleteToken,
            ]
        );

        $this->assertResponseRedirects('/recettes');
        $this->assertFileDoesNotExist($photoPath);
    }

    public function testRecetteCardPhotoSliderRenderWithMultiplePhotos(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Paëlla Slider ' . uniqid());
        $recette->setPhotos(['photo1.jpg', 'photo2.jpg', 'photo3.jpg']);
        $this->em->persist($recette);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();

        $slider = $crawler->filter('.card-recette .card-photo-slider[data-photos*="photo1.jpg"]');
        $this->assertGreaterThanOrEqual(1, $slider->count());
        $this->assertGreaterThanOrEqual(1, $slider->filter('.card-photo-arrow-prev')->count());
        $this->assertGreaterThanOrEqual(1, $slider->filter('.card-photo-arrow-next')->count());
        $this->assertGreaterThanOrEqual(1, $slider->filter('.card-photo-nav-group')->count());
        $this->assertGreaterThanOrEqual(3, $slider->filter('.card-photo-dot')->count());

        // Nettoyage
        $this->em->remove($recette);
        $this->em->flush();
    }

    public function testRecetteCardPlaceholderRenderWhenNoPhoto(): void
    {
        $recette = new Recette();
        $recette->setDesignation('Plat Sans Photo ' . uniqid());
        $this->em->persist($recette);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();

        // Sur le catalogue recettes, une carte sans photo doit avoir le placeholder flou
        $card = $crawler->filterXPath("//div[contains(@class, 'card-recette')][.//a[contains(text(), '{$recette->getDesignation()}')]]");
        $this->assertGreaterThanOrEqual(1, $card->count());
        $this->assertGreaterThanOrEqual(1, $card->filter('.card-photo-placeholder')->count());
        $this->assertGreaterThanOrEqual(1, $card->filter('.card-photo-placeholder-bg')->count());

        // Nettoyage
        $this->em->remove($recette);
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
