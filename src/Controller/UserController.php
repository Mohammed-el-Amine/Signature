<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use App\Entity\Logo;
use App\Entity\HtmlSignature;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\HtmlSignatureRepository;
use App\Repository\LogoRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Mailgun\Mailgun;
use Symfony\Component\Uid\Uuid;

class UserController extends AbstractController
{
    #[Route('/admin/users', name: 'admin_index', methods: ["GET"])]
    public function index(UserRepository $userRepository, SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);

            if ($user && $user->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

        $users = $userRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/add', name: 'admin_add', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/user/add", name="admin_add", methods={"GET","POST"})
     */
    public function add(UserRepository $userRepository, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);

            if ($user && $user->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

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
    public function show(UserRepository $userRepository, User $user, SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);

            if ($user && $user->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

        return $this->render('admin/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/user/edit/{id}', name: 'utilisateur_edit', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/user/edit/{id}", name="utilisateur_edit", methods={"GET","POST"})
     */
    public function edit(User $editUser, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $currentUser = $userRepository->find($userId);

            if ($currentUser && $currentUser->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

        $currentEmail = $editUser->getEmail();
        $currentRole = $editUser->getRole();
        $currentPassword = $editUser->getPassword();

        $form = $this->createFormBuilder($editUser)
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
            $editUser->setEmail($currentEmail);
            $editUser->setRole($currentRole);

            $newRole = $form->get('role')->getData();
            $editUser->setRole($newRole);

            $newPassword = $editUser->getPassword();

            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $editUser->setPassword($hashedPassword);
            } else {
                // Aucun nouveau mot de passe fourni, on ne l'actualise pas
                $editUser->setPassword($currentPassword);
            }

            $entityManager->flush();

            $this->addFlash('success', 'L\'enregistrement a été effectué avec succès.');
        }
        return $this->render('admin/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $editUser,
        ]);
    }

    #[Route('/admin/user/delete/{id}', name: 'user_delete', methods: ['DELETE'])]
    /**
     * @Route("/admin/user/delete/{id}", name="user_delete", methods={"GET", "DELETE"})
     */
    public function delete(User $userToDelete, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $adminId = $session->get('user_id');

        if ($adminId) {
            $adminUser = $userRepository->find($adminId);

            if ($adminUser && $adminUser->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

        $entityManager->remove($userToDelete);
        $entityManager->flush();

        return $this->redirectToRoute('admin_index');
    }

    #[Route('/admin', name: 'admin_dashboard')]
    /**
     * @Route("/admin", name="admin_dashboard")
     */
    public function adminHome(UserRepository $userRepository, SessionInterface $session, UrlGeneratorInterface $urlGenerator, HtmlSignatureRepository $signatureRepository, LogoRepository $logo)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);

            if ($user && $user->getRole() !== 'admin') {
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

        $users = $userRepository->findAll();
        $allSignatures = $signatureRepository->findAll();

        dump($allSignatures);
        dump($logo);

        return $this->render('admin/home.html.twig', [
            'users' => $users,
            'signatures' => $allSignatures,
        ]);
    }

    #[Route('/profile', name: 'user_profile')]
    /**
     * @Route("/profile", name="user_profile")
     */
    public function userProfile(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator, HtmlSignatureRepository $signatureRepository, LogoRepository $logoRepository)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

        $currentEmail = $user->getEmail();
        $currentPassword = $user->getPassword();

        $userForm = $this->createFormBuilder($user)
            ->add('email', EmailType::class, [
                'data' => $currentEmail,
                'disabled' => true,
            ])
            ->add('password', PasswordType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->getForm();

        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $user->setEmail($currentEmail);

            $newPassword = $user->getPassword();

            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $user->setPassword($hashedPassword);
            } else {
                $user->setPassword($currentPassword);
            }

            $entityManager->flush();

            $this->addFlash('success', 'L\'enregistrement a été effectué avec succès.');
        }

        $signature = new HtmlSignature();
        $signature->setUserId($user);

        $defaultHtmlCode = 'not null'; // Mettez votre valeur par défaut ici

        $signatureForm = $this->createFormBuilder($signature)
            ->add('name', TextType::class)
            ->add('job_title', TextType::class)
            ->add('organization', TextType::class)
            ->add('adress', TextType::class)
            ->add('postal_code', TextType::class)
            ->add('city', TextType::class)
            ->add('email', EmailType::class)
            ->add('phone', TelType::class)
            ->add('html_code', TextareaType::class, [
                'label' => 'Contenu HTML',
                'disabled' => false,
                'required' => false,
                'attr' => [
                    'readonly' => false, // Modifier readonly en false
                    'rows' => 5
                ],
                'data' => $defaultHtmlCode
            ])
            ->add('baniere', TextareaType::class, ['required' => false])
            ->getForm();

        $signatureForm->handleRequest($request);

        if ($signatureForm->isSubmitted() && $signatureForm->isValid()) {

            $signatureData = $signatureForm->getData();
            $name = $signatureData->getName();
            $jobTitle = $signatureData->getJobTitle();
            $organization = $signatureData->getOrganization();
            $address = $signatureData->getAdress();
            $postalCode = $signatureData->getPostalCode();
            $city = $signatureData->getCity();
            $email = $signatureData->getEmail();
            $phone = $signatureData->getPhone();
            $path = 'https://www.unsa.org/';
            // $logo = $signatureData_>getLogo(); 
            $logo = '/img/LOGO_UNSA_2k19.png';

            // Mettez à jour le code HTML de la signature avec les valeurs dynamiques
            $defaultHtmlCode = '<style>
                                .logo p {
                                  padding-right: 10px;
                                  font-size: 12px;
                                  line-height: 14px;
                                }
                                .logo img {
                                  border: none;
                                  width: 120px;
                                }
                                .info p {
                                  font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;
                                  font-size: 12px;
                                  line-height: 14px;
                                  color: #000;
                                  text-align: left;
                                }
                                .info span.name {
                                  color: #000;
                                  font-weight: bold;
                                  font-size: 14px;
                                }
                                .info span.role {
                                  color: #666;
                                  font-style: italic;
                                }
                                .info span.organization {
                                  color: #666;
                                  font-style: italic;
                                }
                                .address p {
                                  font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;
                                  font-size: 12px;
                                  line-height: 14px;
                                  color: #000;
                                }
                                .contact p {
                                  font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;
                                  font-size: 12px;
                                  line-height: 14px;
                                  color: #000;
                                }
                                .contact img {
                                  border: none;
                                  height: 13px;
                                  margin-right: .5em;
                                }
                                .contact a {
                                  color: #666;
                                  font-style: italic;
                                }
                                .contact span {
                                  color: #666;
                                }
                                .social p {
                                  margin: 0;
                                  padding: 0;
                                }
                                .social img {
                                  border: none;
                                  height: 23px;
                                  margin-right: .5em;
                                }
                                .social a {
                                  color: #666;
                                  font-style: italic;
                                }
                              </style>
                                </head>                          
                                <body>
                                  <table border="0" cellpadding="0" width="500">
                                    <tbody>
                                      <tr>
                                        <td align="left" valign="middle" width="10">
                                          <div class="logo">
                                            <p><a href="https://www.unsa.org"><img src="' . $logo . '"></a></p>
                                          </div>
                                        </td>
                                        <td>
                                          <div class="info">
                                            <p><span class="name">' . $name . '</span><br><span class="role"><i>' . $jobTitle . '</i></span><br><span
                                                class="organization"><i>' . $organization . '</i></span><br></p>
                                          </div>
                                          <div class="address">
                                            <p><span>' . $address . '</span><br><span>' . $postalCode . '</span>&nbsp;<span>' . $city . ' CEDEX</span><br></p>
                                          </div>
                                          <div class="contact">
                                            <p><img src="https://reseaux.unsa.org/signature/_mail.svg"><a
                                                href="mailto:' . $email . '">' . $email . '</a><br><img
                                                src="https://reseaux.unsa.org/signature/_phone.svg"><span>' . $phone . '</span></p>
                                          </div>
                                          <div class="social">
                                            <p><img
                                                src="' . $logo . '"><a
                                                href="' . $path . '">' . $path . '</a><br></p>
                                          </div>
                                        </td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </body>';

            $signature->setHtmlCode($defaultHtmlCode); // Mettez à jour la propriété htmlCode de l'objet $signature

            $entityManager->persist($signature);
            $entityManager->flush();

            $this->addFlash('success', 'L\'enregistrement a été effectué avec succès.');
            echo "je suis passé";
        }



        $allSignatures = $signatureRepository->findAll(); //toutes les signatures

        return $this->render('user/profile.html.twig', [
            'form' => $userForm->createView(),
            'signatureForm' => $signatureForm->createView(),
            'user' => $user,
            'signatures' => $allSignatures,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => HtmlSignature::class,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    /**
     * @Route("/logout", name="logout")
     */
    public function logout(SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        $session->invalidate();
        return new RedirectResponse($urlGenerator->generate('app_home'));
    }
}
