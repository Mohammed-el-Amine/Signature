<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;


class DocumentController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('sign/{subdirectory}/{fileName}', name: 'app_afficher_fichier')]
    public function voirFichier($subdirectory, $fileName)
    {
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img';

        $filePath = sprintf('%s/%s/%s', $publicDirectory, $subdirectory, $fileName);

        if (file_exists($filePath)) {
            $fileContent = file_get_contents($filePath);

            $mimeType = mime_content_type($filePath);

            return new Response($fileContent, 200, [
                'Content-Type' => $mimeType,
            ]);
        } else {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }
    }
}
