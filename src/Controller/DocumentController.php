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

    #[Route('/liste-fichiers', name: 'app_liste_fichiers')]
    public function listeFichiers()
    {
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img';
    
        $contents = $this->getDirectoryContents($publicDirectory);
    
        $html = $this->generateTreeHtml($contents);
    
        return new Response($html);
    }
    
    private function getDirectoryContents(string $dir)
    {
        $contents = [];
        $handle = opendir($dir);
    
        while (false !== ($item = readdir($handle))) {
            if ($item !== '.' && $item !== '..') {
                $path = $dir . '/' . $item;
                if (is_dir($path)) {
                    // Récupérer les fichiers du sous-répertoire récursivement
                    $subContents = $this->getDirectoryContents($path);
                    // Ajouter les fichiers du sous-répertoire à la liste principale
                    $contents[$item] = $subContents;
                } else {
                    // Ajouter le fichier au tableau des contenus
                    $contents[] = $item;
                }
            }
        }
    
        closedir($handle);
    
        return $contents;
    }
    
    private function generateTreeHtml(array $contents, $isSubDirectory = false)
    {
        $html = $isSubDirectory ? '<ul>' : '<ul class="tree">';
        foreach ($contents as $key => $item) {
            if (is_array($item)) {
                // Sous-répertoire, appel récursif
                $html .= '<li>' . $key . $this->generateTreeHtml($item, true) . '</li>';
            } else {
                // Fichier
                $html .= '<li>' . $item . '</li>';
            }
        }
        $html .= '</ul>';
    
        return $html;
    }
    
}
