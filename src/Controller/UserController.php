<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Mailgun\Mailgun;
use Symfony\Component\Uid\Uuid;

class UserController extends AbstractController
{
    #[Route('/admin/users', name: 'admin_index', methods: ["GET"])]
    /**
     * @Route("/admin/users", name="admin_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository)
    {
        $users = $userRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/add', name: 'admin_add', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/user/add", name="admin_add", methods={"GET","POST"})
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
                    'Utilisateur' => 'utilisateur',
                    'Admin' => 'admin'
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

            $user->setToken(Uuid::v4());

            $entityManager->persist($user);
            $entityManager->flush();

            $mgClient = Mailgun::create('9ce219b17ec2b8a27138b5099a79c392-07ec2ba2-8a4b0c3b');

            $domain = "sandbox2d5e1ea5fe7e445e9e7b8488a8c33c2a.mailgun.org";
            $params = [
                'from'    => 'mohammed-el-amine.djellal@epitech.eu',
                'to'      => $email,
                'subject' => 'Création de compte',
                'text'    => 'Veuillez cliquer sur le lien suivant pour créer votre mot de passe : ' . $this->generateUrl('create_password', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];

            $mgClient->messages()->send($domain, $params);
            $this->addFlash('success', 'L\'ajout d\'un nouvelle utilisateur a été effectué avec succès.Un e-mail vient de lui être envoyer pour crée son mot de passe');

            // return $this->redirectToRoute('home_index');
        }

        return $this->render('admin/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('user/create-password/{token}', name: 'create_password', methods: ['GET', 'POST'])]
    public function createPassword(Request $request, EntityManagerInterface $entityManager, string $token)
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $form = $this->createFormBuilder($user)
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les champs du mot de passe doivent correspondre.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options' => ['label' => 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $user->setPassword($hashedPassword);
            $user->setToken(null);

            $entityManager->flush();

            // Rediriger vers une page de confirmation ou de connexion
            return $this->redirectToRoute('app_home');
        }

        return $this->render('password/create_password.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/admin/user/show/{id}', name: 'user_show', methods: ['GET'])]
    /**
     * @Route("/admin/user/show/{id}", name="user_show", methods={"GET"})
     */
    public function show(User $user)
    {
        return $this->render('admin/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/user/edit/{id}', name: 'utilisateur_edit', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/user/edit/{id}", name="utilisateur_edit", methods={"GET","POST"})
     */
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager)
    {
        $currentEmail = $user->getEmail();
        $currentRole = $user->getRole();
        $currentPassword = $user->getPassword();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, [
                'data' => $currentEmail,
            ])
            ->add('password', PasswordType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'admin',
                    'Utilisateur' => 'utilisateur',
                ],
                'data' => $currentRole,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail($currentEmail);
            $user->setRole($currentRole);

            $newRole = $form->get('role')->getData();
            $user->setRole($newRole);

            $newPassword = $user->getPassword();

            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $user->setPassword($hashedPassword);
            } else {
                // Aucun nouveau mot de passe fourni, on ne l'actualise pas
                $user->setPassword($currentPassword);
            }

            $entityManager->flush();

            $this->addFlash('success', 'L\'enregistrement a été effectué avec succès.');
        }
        return $this->render('admin/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/user/delete/{id}', name: 'user_delete', methods: ['DELETE'])]
    /**
     * @Route("/admin/user/delete/{id}", name="user_delete", methods={"GET", "DELETE"})
     */
    public function delete(User $user, EntityManagerInterface $entityManager)
    {
        $entityManager->remove($user);
        $entityManager->flush();

        return $this->redirectToRoute('admin_index');
    }

    #[Route('/admin', name: 'admin_dashboard')]
    /**
     * @Route("/admin", name="admin_dashboard")
     */
    public function adminHome(UserRepository $userRepository)
    {
        $users = $userRepository->findAll();
        return $this->render('admin/home.html.twig', [
            'users' => $users,
        ]);
    }
}
