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
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class HomeController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request, SessionInterface $session): Response
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

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                if ($this->isPasswordValid($user, $password)) {
                    $session->set('user_id', $user->getId()); // Stocke l'identifiant de l'utilisateur dans la session

                    $role = $user->getRole();
                    // Faire la redirection en fonction du rôle : si utilisateur, vers /profile ; si admin, vers /admin
                    if ($role === "admin") {
                        return $this->redirectToRoute('admin_dashboard');
                    } else if ($role === 'utilisateur') {
                        return $this->redirectToRoute('profile');
                    }
                } else {
                    // Le mot de passe est invalide
                    echo 'Mot de passe invalide<br>';
                }
            } else {
                // TODO: Ajoutez ici votre logique pour gérer l'erreur d'utilisateur inexistant
                echo 'Veuillez vérifiez votre adresse email.<br>Si vous ne possédez pas d\'identifiant de connexion merci de vous rapprocher du responsable informatique.';
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
