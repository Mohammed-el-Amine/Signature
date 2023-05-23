<?php

namespace App\Controller;

// require 'vendor/autoload.php';

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Mailgun\Mailgun;

class UserController extends AbstractController
{
    #[Route('/admin', name: 'admin_index', methods: ["GET"])]
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

    #[Route('/admin/add', name: 'admin_add', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/add", name="admin_add", methods={"GET","POST"})
     */
    public function add(Request $request, EntityManagerInterface $entityManager, SessionInterface $session)
    {
        $user = new User();
        $defaultPassword = "unsa_white_knight_pass_word_very_long";

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class)
            ->add('password', HiddenType::class, [
                'data' => $defaultPassword,
            ])
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'admin',
                    'Utilisateur' => 'utilisateur',
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $email = $user->getEmail();
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $session = new Session();
                $session->getFlashBag()->add('danger', 'L\'adresse e-mail est déjà utilisée.');
                return $this->render('admin/add.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            $hashedPassword = password_hash($user->getPassword(), PASSWORD_DEFAULT);
            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            $mgClient = Mailgun::create('9ce219b17ec2b8a27138b5099a79c392-07ec2ba2-8a4b0c3b');

            $domain = "sandbox2d5e1ea5fe7e445e9e7b8488a8c33c2a.mailgun.org";
            $params = [
                'from'    => 'mohammed-el-amine.djellal@epitech.eu',
                'to'      => $email,
                'subject' => 'Création de compte',
                'text'    => 'Veuillez cliquer sur le lien suivant pour créer votre mot de passe : https://127.0.0.1:8000/create-password',
            ];

            $mgClient->messages()->send($domain, $params);

            return $this->redirectToRoute('admin_index');
        }

        return $this->render('admin/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/show/{id}', name: 'user_show', methods: ['GET'])]
    /**
     * @Route("/admin/show/{id}", name="user_show", methods={"GET"})
     */
    public function show(User $user)
    {
        return $this->render('admin/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/edit/{id}', name: 'utilisateur_edit', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/edit/{id}", name="utilisateur_edit", methods={"GET","POST"})
     */
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager)
    {
        // Récupérer les valeurs actuelles de l'utilisateur
        $currentEmail = $user->getEmail();
        $currentRole = $user->getRole();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, [
                'data' => $currentEmail, // Affecter la valeur actuelle de l'email au champ
            ])
            ->add('password', PasswordType::class, [
                'required' => false, // Rendre le champ facultatif pour éviter de le modifier involontairement
                'empty_data' => '', // Définir une valeur par défaut vide pour le champ du mot de passe
            ])
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'admin',
                    'Utilisateur' => 'utilisateur',
                ],
                'data' => $currentRole, // Affecter la valeur actuelle du rôle au champ
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour les propriétés de l'utilisateur avec les nouvelles valeurs
            $user->setEmail($currentEmail); // Réaffecter la valeur actuelle de l'email
            $user->setRole($currentRole); // Réaffecter la valeur actuelle du rôle

            // Vérifier si le champ du mot de passe a été modifié
            $newPassword = $user->getPassword();
            if (!empty($newPassword)) {
                // Hacher le nouveau mot de passe avant de le sauvegarder dans la base de données
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $user->setPassword($hashedPassword);
            } else {
                // Aucun nouveau mot de passe fourni, réaffecter le mot de passe actuel
                $user->setPassword($user->getPassword());
            }

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

    #[Route('/admin/delete/{id}', name: 'user_delete', methods: ['DELETE'])]
    /**
     * @Route("/admin/delete/{id}", name="user_delete", methods={"GET", "DELETE"})
     */
    public function delete(User $user, EntityManagerInterface $entityManager)
    {
        $entityManager->remove($user);
        $entityManager->flush();

        return $this->redirectToRoute('admin_index');
    }
}
