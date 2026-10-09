<?php

namespace App\Tests\Controller;

use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RepasPhotoTest extends WebTestCase
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

    public function testAddRealisationWithPhoto(): void
    {
        $recette = $this->em->getRepository(Recette::class)->findOneBy([]);
        if (!$recette) {
            $recette = new Recette();
            $recette->setDesignation('Plat Test Photo');
            $this->em->persist($recette);
            $this->em->flush();
        }

        $filePath = tempnam(sys_get_temp_dir(), 'test_photo_');
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $uploadedFile = new UploadedFile($filePath, 'mon_repas.gif', 'image/gif', null, true);

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $csrfToken = $this->getCsrfToken('add_realisation');

        $this->client->request(
            'POST',
            '/realisation/add',
            [
                'recette_id' => $recette->getId(),
                'moment' => 'midi',
                'date' => date('Y-m-d'),
                '_token' => $csrfToken,
            ],
            [
                'photo' => $uploadedFile,
            ]
        );

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.card-recette.has-photo-bg');
        $this->assertSelectorExists('.card-recette-bg-photo');
        $this->assertSelectorExists('.card-recette-title-row .card-recette-title');

        // Vérifier que la réalisation a bien une photo stockée
        /** @var RecetteRealisation|null $realisation */
        $realisation = $this->em->getRepository(RecetteRealisation::class)->findOneBy(
            ['recette' => $recette],
            ['id' => 'DESC']
        );

        $this->assertNotNull($realisation);
        $this->assertNotNull($realisation->getPhoto());
        $this->assertStringStartsWith('repas_', $realisation->getPhoto());
        $this->assertSame('/photos/repas/' . $realisation->getPhoto(), $realisation->getPhotoUrl());

        $photoPath = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/repas/' . $realisation->getPhoto();
        $this->assertFileExists($photoPath);

        // Tester la suppression de la photo lors de la mise à jour
        $updateToken = $this->getCsrfToken('update_realisation_date_' . $realisation->getId());
        $this->client->request(
            'POST',
            '/realisation/' . $realisation->getId() . '/update-date',
            [
                '_token' => $updateToken,
                'realise_at' => date('Y-m-d'),
                'moment' => 'midi',
                'delete_photo' => '1',
            ]
        );

        $this->assertResponseRedirects('/');
        $this->em->refresh($realisation);
        $this->assertNull($realisation->getPhoto());
        $this->assertFileDoesNotExist($photoPath);

        // Nettoyage
        $this->em->remove($realisation);
        $this->em->flush();
    }

    public function testUpdateRealisationWithNewPhotoAndMealDeletionRemovesFile(): void
    {
        $recette = $this->em->getRepository(Recette::class)->findOneBy([]);
        if (!$recette) {
            $recette = new Recette();
            $recette->setDesignation('Plat Test Photo 2');
            $this->em->persist($recette);
            $this->em->flush();
        }

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable());
        $realisation->setMoment('soir');
        $this->em->persist($realisation);
        $this->em->flush();

        $realisationId = $realisation->getId();
        $this->client->request('GET', '/');

        // 1. Ajouter une photo via update-date
        $filePath1 = tempnam(sys_get_temp_dir(), 'photo1_');
        file_put_contents($filePath1, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile1 = new UploadedFile($filePath1, 'photo1.gif', 'image/gif', null, true);

        $updateToken = $this->getCsrfToken('update_realisation_date_' . $realisationId);
        $this->client->request(
            'POST',
            '/realisation/' . $realisationId . '/update-date',
            [
                '_token' => $updateToken,
                'realise_at' => date('Y-m-d'),
                'moment' => 'soir',
            ],
            [
                'photo' => $uploadedFile1,
            ]
        );

        $this->assertResponseRedirects('/');
        $this->em->clear();
        $realisation = $this->em->getRepository(RecetteRealisation::class)->find($realisationId);
        $this->assertNotNull($realisation);
        $photo1 = $realisation->getPhoto();
        $this->assertNotNull($photo1);
        $photo1Path = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/repas/' . $photo1;
        $this->assertFileExists($photo1Path);

        // 2. Remplacer par une seconde photo : photo1 doit être supprimée
        $filePath2 = tempnam(sys_get_temp_dir(), 'photo2_');
        file_put_contents($filePath2, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $uploadedFile2 = new UploadedFile($filePath2, 'photo2.gif', 'image/gif', null, true);

        $this->client->request('GET', '/');
        $updateToken2 = $this->getCsrfToken('update_realisation_date_' . $realisationId);
        $this->client->request(
            'POST',
            '/realisation/' . $realisationId . '/update-date',
            [
                '_token' => $updateToken2,
                'realise_at' => date('Y-m-d'),
                'moment' => 'soir',
            ],
            [
                'photo' => $uploadedFile2,
            ]
        );

        $this->assertResponseRedirects('/');
        $this->em->clear();
        $realisation = $this->em->getRepository(RecetteRealisation::class)->find($realisationId);
        $this->assertNotNull($realisation);
        $photo2 = $realisation->getPhoto();
        $this->assertNotNull($photo2);
        $this->assertNotEquals($photo1, $photo2);

        $this->assertFileDoesNotExist($photo1Path);
        $photo2Path = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/repas/' . $photo2;
        $this->assertFileExists($photo2Path);

        // 3. Supprimer le repas : photo2 doit être supprimée du disque
        $this->client->request('GET', '/');
        $deleteToken = $this->getCsrfToken('delete_realisation_' . $realisationId);
        $this->client->request(
            'POST',
            '/realisation/' . $realisationId . '/delete',
            [
                '_token' => $deleteToken,
            ]
        );

        $this->assertResponseRedirects('/');
        $this->assertFileDoesNotExist($photo2Path);
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
