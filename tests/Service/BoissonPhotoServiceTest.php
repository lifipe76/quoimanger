<?php

namespace App\Tests\Service;

use App\Service\BoissonPhotoService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BoissonPhotoServiceTest extends KernelTestCase
{
    private BoissonPhotoService $service;
    private string $uploadDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = static::getContainer()->get(BoissonPhotoService::class);
        $this->uploadDir = static::getContainer()->getParameter('kernel.project_dir') . '/public_html/photos/boissons';
    }

    public function testUploadValidImage(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'boisson_test_');
        // GIF 1x1
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $uploadedFile = new UploadedFile($filePath, 'test_boisson.gif', 'image/gif', null, true);
        $filename = $this->service->upload($uploadedFile);

        $this->assertNotEmpty($filename);
        $this->assertStringStartsWith('boisson_', $filename);
        $this->assertFileExists($this->uploadDir . '/' . $filename);

        // Delete test
        $this->service->delete($filename);
        $this->assertFileDoesNotExist($this->uploadDir . '/' . $filename);
    }

    public function testGetUrl(): void
    {
        $this->assertNull($this->service->getUrl(null));
        $this->assertNull($this->service->getUrl(''));
        $this->assertEquals('https://example.com/photo.jpg', $this->service->getUrl('https://example.com/photo.jpg'));
        $this->assertEquals('/photos/boissons/boisson_test.jpg', $this->service->getUrl('boisson_test.jpg'));
    }
}
