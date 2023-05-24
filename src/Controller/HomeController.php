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
                if ($this->isPasswordValid($user, $password)) {
                    $role = $user->getRole();
                    // faire la redirection en fonction du role si user = /profile si admin = /admin
                    if ($role === "admin") {
                        echo "je suis administrateur";
                    } else {
                        echo "je suis un user";
                    }
                } else {
                    // Le mot de passe est invalide
                    echo 'Mot de passe invalide<br>';
                }
            } else {
                // TODO: Ajoutez ici votre logique pour gérer l'erreur d'utilisateur inexistant
                echo 'Veuillez vérifiez votre adresse email ou votre mot de passe.<br>Si vous ne possédez pas d\'identifiant de connexion merci de vous rapprocher du responsable informatique';
            }
        }

        return $this->render('home/index.html.twig', [
            'loginForm' => $form->createView(),
        ]);
    }

    private function isPasswordValid(User $user, string $password): bool
    {
        $hashedPassword = $user->getPassword();

        return password_verify($password, $hashedPassword);
    }
}
