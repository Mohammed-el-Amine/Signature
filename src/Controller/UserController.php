<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
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

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hacher le mot de passe avant de le sauvegarder dans la base de données
            $hashedPassword = password_hash($user->getPassword(), PASSWORD_DEFAULT);
            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('admin_index');
        }

        // Afficher le formulaire dans le template
        return $this->render('admin/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/show/{id}', name: 'app_admin')]
    /**
     * @Route("/admin/show/{id}", name="user_show", methods={"GET"})
     */
    public function show(User $user)
    {
        return $this->render('admin/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/edit/{id}', name: 'app_admin')]
    /**
     * @Route("/admin/edit/{id}", name="utilisateur_edit", methods={"GET","POST"})
     */
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager)
    {
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

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hacher le mot de passe avant de le sauvegarder dans la base de données
            $hashedPassword = password_hash($user->getPassword(), PASSWORD_DEFAULT);
            $user->setPassword($hashedPassword);

            // Sauvegarder les modifications dans la base de données
            $entityManager->flush();

            return $this->redirectToRoute('admin_index');
        }

        // Afficher le formulaire d'édition dans le template
        return $this->render('admin/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }


    #[Route('/admin/delete/{id}', name: 'app_admin')]
    /**
     * @Route("/admin/delete/{id}", name="user_delete", methods={"DELETE"})
     */
    public function delete(User $user, EntityManagerInterface $entityManager)
    {
        $entityManager->remove($user);
        $entityManager->flush();

        return $this->redirectToRoute('admin_index');
    }
}
