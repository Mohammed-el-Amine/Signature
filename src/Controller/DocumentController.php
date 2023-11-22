<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

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
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img/Sous-repertoire';

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


    private function generateTreeHtml(array $contents, $basePath = '', $isSubDirectory = false)
    {
        $html = '<ul class="list-group">';

        foreach ($contents as $key => $item) {
            $fullPath = $basePath . '/' . $key;
            $path = $basePath;

            $html .= '<li class="list-group-item">';
            $html .= '<div class="d-flex align-items-center">';

            if (is_array($item)) {
                // Sous-répertoire, appel récursif
                $html .= '<i class="bi bi-folder"></i>';
                $html .= '<span class="folder"><img src="https://lab-web.unsa.org/signature/img/Application/folder.png" alt="boxIcon dossier"> ' . $key . '</span><br><br>';
                $html .= '<div class="ml-auto">';
                $html .= '<form method="post" action="' . $this->generateUrl('app_supprimer_dossier') . '" style="display:inline;"onsubmit="return confirmDelete(event)">'; // Utilisez "display:inline;" pour éviter les sauts de ligne
                $html .= '<input type="hidden" name="chemin-dossier" value="' . $fullPath . '/">';
                $html .= '<button type="submit" class="btn"><img src="https://lab-web.unsa.org/signature/img/Application/trash.png"></button>';
                $html .= '</form>';
                $html .= '</div>';
            } else {
                // Fichier
                $html .= '<i class="bi bi-file"></i>';
                $html .= '<span class="file"><img src="https://lab-web.unsa.org/signature/img/Application/file.png" alt="boxIcon dossier">  <a href="/signature/sign';

                // Ajout du nom du dossier dans le lien pour les sous-répertoires
                if ($isSubDirectory) {
                    $html .= $path . '/';
                }

                $html .= $item . '">' . $item . '</a></span>';
                $html .= '<div class="ml-auto">'; // Déplacez le bouton à droite
                $html .= '<form method="post" action="' . $this->generateUrl('app_supprimer_fichier') . '" style="display:inline;" onsubmit="return confirmDelete(event)">'; // Utilisez "display:inline;" pour éviter les sauts de ligne
                $html .= '<input type="hidden" name="chemin-fichier" value="' . $path . '/' . $item . '">';
                $html .= '<button type="submit" class="btn"><img src="https://lab-web.unsa.org/signature/img/Application/trash.png"></button>';
                $html .= '</form>';
                $html .= '</div>';
            }



            $html .= '</div>';

            if (is_array($item)) {
                $html .= $this->generateTreeHtml($item, $fullPath, true);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        $html .= '<script>function confirmDelete(event) {return confirm(\'Êtes-vous sûr de vouloir supprimer ce document ?\')}</script>';
        return $html;
    }

    #[Route('/supprimer-dossier', name: 'app_supprimer_dossier')]
    public function supprimerFichierOuDossier(Request $request)
    {
        $chemin = $request->request->get('chemin-dossier'); // Récupérez le chemin depuis le formulaire
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img/Sous-repertoire';

        // Ajoutez le chemin du dossier à supprimer
        $dossierASupprimer = $publicDirectory . $chemin;

        // Vérifiez si le dossier existe avant de le supprimer
        if (file_exists($dossierASupprimer)) {
            $filesystem = new Filesystem();

            // Supprimez le dossier
            $filesystem->remove($dossierASupprimer);

            $this->addFlash('success', 'Le dossier a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Le dossier n\'existe pas.');
        }

        return $this->redirectToRoute('app_liste_fichiers');
    }

    #[Route('/supprimer-fichier', name: 'app_supprimer_fichier')]
    public function supprimerFichier(Request $request)
    {
        $chemin = $request->request->get('chemin-fichier');
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img/Sous-repertoire';

        $fichierASupprimer = $publicDirectory . '/' . $chemin;

        if (file_exists($fichierASupprimer)) {
            $filesystem = new Filesystem();

            $filesystem->remove($fichierASupprimer);

            $this->addFlash('success', 'Le fichier a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Le fichier n\'existe pas.');
        }

        return $this->redirectToRoute('app_liste_fichiers');
    }

    #[Route('/liste-fichiers', name: 'app_liste_fichiers')]
    public function listeFichiers()
    {
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img/Sous-repertoire';

        $contents = $this->getDirectoryContents($publicDirectory);

        $treeHtml = $this->generateTreeHtml($contents);

        $finder = new Finder();
        $finder->directories()->in($publicDirectory);

        $dossiers = [];
        foreach ($finder as $directory) {
            $dossiers[] = $directory->getRelativePathname();
        }

        return $this->render('Document/liste_fichiers.html.twig', [
            'treeHtml' => $treeHtml,
            'dossiers' => $dossiers,
        ]);
    }

    /**
     * @Route("/creation-dossier", name="app_cree_dossier")
     */
    public function Dossier(): Response
    {
        return $this->render('Document/ajouter-dossier.html.twig');
    }

    /**
     * @Route("/dossier_ajouter", name="app_dossier_ajouter", methods={"POST"})
     */
    public function ajoutDossier(Request $request): Response
    {
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img/Sous-repertoire';

        $nomDossier = $request->request->get('nomDossier');

        $cheminNouveauDossier = $publicDirectory . '/' . $nomDossier;

        if (!is_dir($cheminNouveauDossier)) {
            mkdir($cheminNouveauDossier);

            $this->addFlash('success', 'Le dossier a été créé avec succès.');
        } else {
            $this->addFlash('warning', 'Le dossier existe déjà.');
        }

        return $this->redirectToRoute('app_liste_fichiers');
    }


    /**
     * @Route("/ajouter-fichier", name="app_ajouter_fichier")
     */
    public function ajouterFichierForm(): Response
    {
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img/Sous-repertoire';

        $finder = new Finder();
        $finder->directories()->in($publicDirectory);

        $dossiers = [];
        foreach ($finder as $directory) {
            $dossiers[] = $directory->getRelativePathname();
        }

        return $this->render('Document/ajouter-fichier.html.twig', [
            'dossiers' => $dossiers,
        ]);
    }

    /**
     * @Route("/fichier_ajouter", name="app_fichier_ajouter", methods={"POST"})
     */
    public function ajoutFichier(Request $request): Response
    {
        $publicDirectory = $this->getParameter('kernel.project_dir') . '/public/img/Sous-repertoire';

        $dossierDestination = $request->request->get('dossierDestination');
        $cheminDestination = $publicDirectory . '/' . $dossierDestination;

        if (is_dir($cheminDestination)) {
            $nomFichier = $request->request->get('nomFichier');
            $file = $request->files->get('fichier');

            if ($file) {
                // Vérifier la taille du fichier
                $maxFileSize = 300 * 1024; // 300 Ko en octets
                if ($file->getSize() > $maxFileSize) {
                    // Gérer le cas où le fichier est trop grand
                    // Vous pouvez retourner une réponse ou effectuer d'autres actions nécessaires.
                    return new Response('Le fichier est trop grand. La taille maximale autorisée est de 300 Ko.', 400);
                }

                // Vérifier le format du fichier
                $allowedExtensions = ['html', 'jpeg', 'jpg', 'png'];
                $extension = $file->guessExtension();

                if (!in_array($extension, $allowedExtensions)) {
                    // Gérer le cas où le format du fichier n'est pas autorisé
                    // Vous pouvez retourner une réponse ou effectuer d'autres actions nécessaires.
                    return new Response('Format de fichier non autorisé. Les formats autorisés sont : html, jpeg, jpg, png.', 400);
                }

                $fileName = $nomFichier . '.' . $extension;
                $cheminFichier = $cheminDestination . '/' . $fileName;

                if (file_exists($cheminFichier)) {
                    unlink($cheminFichier);
                }

                $file->move($cheminDestination, $fileName);
            }
        }

        return $this->redirectToRoute('app_liste_fichiers');
    }
}
