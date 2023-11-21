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
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];
            $password = $data['password'];

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                if ($this->isPasswordValid($user, $password)) {
                    $session->set('user_id', $user->getId());

                    $role = $user->getRole();
                    if ($role === "admin") {
                        return $this->redirectToRoute('admin_dashboard');
                        // $this->addFlash('success', 'admin');
                    } else if ($role === 'utilisateur') {
                        return $this->redirectToRoute('profile_signature');
                        // $this->addFlash('success', 'utilisateur');
                    }
                } else {
                    $this->addFlash('danger', 'Mot de passe invalide');
                }
            } else {
                $this->addFlash('danger', 'Utilisateur non trouvé');
            }
        }

        return $this->render('Home/login.html.twig', [
            'loginForm' => $form->createView(),
        ]);
    }

    private function isPasswordValid(User $user, string $password): bool
    {
        $hashedPassword = $user->getPassword();

        $hashedPasswordInput = hash('sha256', $password);

        return $hashedPassword === $hashedPasswordInput;
    }
   
}
