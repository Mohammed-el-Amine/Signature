<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class HomeController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];
            $password = $data['password'];

            // Vérification de l'utilisateur dans la base de données par son adresse e-mail
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                // L'utilisateur existe dans la base de données
                if ($this->isPasswordValid($user, $password)) {
                    // Le mot de passe est valide - Redirection vers la page souhaitée après connexion réussie
                    // return $this->redirectToRoute('app_dashboard');
                    echo 'Mot de passe valide<br>';
                    $role = $user->getRole();
                    var_dump($role);
                    // faire la redirection en fonction du role si user = /profile si admin = /admin
                } else {
                    // Le mot de passe est invalide
                    echo 'Mot de passe invalide<br>';
                }
            } else {
                // L'utilisateur n'existe pas
                // TODO: Ajoutez ici votre logique pour gérer l'erreur d'utilisateur inexistant
                echo 'Utilisateur inexistant';
            }
        }

        return $this->render('home/index.html.twig', [
            'loginForm' => $form->createView(),
        ]);
    }

    private function isPasswordValid(User $user, string $password): bool
    {
        // Récupérez le mot de passe haché de l'utilisateur depuis la base de données
        $hashedPassword = $user->getPassword();

        // Vérifiez si le mot de passe fourni correspond au mot de passe haché
        // Vous pouvez utiliser la fonction password_verify() de PHP pour cela
        return password_verify($password, $hashedPassword);
    }
}
