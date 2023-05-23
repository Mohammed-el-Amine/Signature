<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    /**
     * @Route("/admin", name="admin_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository)
    {
        $users = $userRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'users' => $users, // liste des users
        ]);
    }

    #[Route('/admin/add', name: 'app_admin')]
    /**
     * @Route("/admin/add", name="admin_add", methods={"GET","POST"})
     */
    public function add(Request $request, EntityManagerInterface $entityManager)
    {
        $user = new User();

        // Créer le formulaire manuellement avec les champs requis
        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'admin',
                    'Utilisateur' => 'utilisateur',
                ],
            ])
            ->getForm();

        // Gérer la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarder les données de l'utilisateur dans la base de données
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('admin_index');
        }

        // Afficher le formulaire dans le template
        return $this->render('admin/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/{id}/show', name: 'app_admin')]
    /**
     * @Route("/admin/show/{id}", name="user_show", methods={"GET"})
     */
    public function show(User $user)
    {
        // Votre logique pour afficher un utilisateur
    }

    /**
     * @Route("/admin/{id}/edit", name="utilisateur_edit", methods={"GET","POST"})
     */
    public function edit()
    {
        // Votre logique pour éditer un utilisateur
    }

    #[Route('/admin/{id}/delete', name: 'app_admin')]
    /**
     * @Route("/admin/{id}/delete", name="user_delete", methods={"DELETE"})
     */
    public function delete(User $user, EntityManagerInterface $entityManager)
    {
        $entityManager->remove($user);
        $entityManager->flush();

        return $this->redirectToRoute('admin_index');
    }
}
