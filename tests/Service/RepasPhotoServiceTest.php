<?php

namespace App\Tests\Service;

use App\Service\RepasPhotoService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RepasPhotoServiceTest extends TestCase
{
    private string $tempDir;
    private RepasPhotoService $service;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/repas_photos_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->service = new RepasPhotoService($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) {
                    unlink($f);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testUploadValidImage(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'test_img_');
        // Simple valid 1x1 gif
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $uploadedFile = new UploadedFile($filePath, 'repas.gif', 'image/gif', null, true);

        $savedName = $this->service->upload($uploadedFile);

        $this->assertNotEmpty($savedName);
        $this->assertStringStartsWith('repas_', $savedName);
        $this->assertFileExists($this->tempDir . '/' . $savedName);

        // Test delete
        $this->service->delete($savedName);
        $this->assertFileDoesNotExist($this->tempDir . '/' . $savedName);
    }

    public function testUploadInvalidMimeTypeThrowsException(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'test_txt_');
        file_put_contents($filePath, 'Not an image');

        $uploadedFile = new UploadedFile($filePath, 'document.txt', 'text/plain', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->upload($uploadedFile);
    }

    public function testGetUrl(): void
    {
        $this->assertNull($this->service->getUrl(null));
        $this->assertNull($this->service->getUrl(''));
        $this->assertSame('/photos/repas/test.jpg', $this->service->getUrl('test.jpg'));
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/sample.jpg', $this->service->getUrl('https://res.cloudinary.com/demo/image/upload/sample.jpg'));
    }
}
