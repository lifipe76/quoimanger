<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PwaController extends AbstractController
{
    #[Route('/manifest.json', name: 'app_pwa_manifest', methods: ['GET'])]
    public function manifest(): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public_html/manifest.json';
        if (!file_exists($path)) {
            throw $this->createNotFoundException('Manifest not found');
        }

        return new Response(
            file_get_contents($path),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/manifest+json; charset=utf-8',
                'Cache-Control' => 'public, max-age=86400',
            ]
        );
    }

    #[Route('/sw.js', name: 'app_pwa_service_worker', methods: ['GET'])]
    public function serviceWorker(): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public_html/sw.js';
        if (!file_exists($path)) {
            throw $this->createNotFoundException('Service Worker not found');
        }

        return new Response(
            file_get_contents($path),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/javascript; charset=utf-8',
                'Service-Worker-Allowed' => '/',
                'Cache-Control' => 'no-cache',
            ]
        );
    }
}
