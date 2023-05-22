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
    #[Route('/user', name: 'app_user')]
    /**
     * @Route("/users", name="user_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository)
    {
        // Récupérer la liste des utilisateurs depuis le repository
        $users = $userRepository->findAll();

        // Rendre une vue (template) en passant la liste des utilisateurs en tant que variable
        return $this->render('user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/add', name: 'app_user')]
    /**
     * @Route("/users/add", name="user_add", methods={"GET","POST"})
     */
    public function add(Request $request, EntityManagerInterface $entityManager)
    {
        // Créer une nouvelle instance de l'entité User
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

            // Rediriger vers la page de liste des utilisateurs après l'ajout
            return $this->redirectToRoute('user_index');
        }

        // Afficher le formulaire dans le template
        return $this->render('user/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/users/show/{id}", name="user_show", methods={"GET"})
     */
    public function show(User $user)
    {
        // Votre logique pour afficher un utilisateur
    }

    // /**
    //  * @Route("/{id}/edit", name="utilisateur_edit", methods={"GET","POST"})
    //  */
    public function edit()
    {
        // Votre logique pour éditer un utilisateur
    }

    // /**
    //  * @Route("/{id}", name="utilisateur_delete", methods={"DELETE"})
    //  */
    public function delete()
    {
        // Votre logique pour supprimer un utilisateur
    }
}
