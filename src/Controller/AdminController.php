<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\SignatureRepository;
use App\Repository\LogoRepository;
use App\Entity\User;
use App\Entity\Signature;
use App\Entity\Logo;
use DateTime;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\Pagination\SlidingPagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    /**
     * @Route("/admin", name="admin_dashboard")
     */
    public function adminHome(Request $request, UserRepository $userRepository, SessionInterface $session, UrlGeneratorInterface $urlGenerator, SignatureRepository $signatureRepository, LogoRepository $logo, PaginatorInterface $paginator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);

            if ($user && $user->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $createAt = $request->query->get('date');
        $name = $request->query->get('nom');
        $mail = $request->query->get('email');
        // Récupérer les signatures de l'utilisateur connecté
        $page = $request->query->getInt('page', 1);
        // Nombre d'éléments par page
        $itemsPerPage = 5;
        // Récupérer toutes les signatures avec les conditions de recherche
        $signatureQuery = $signatureRepository->createQueryBuilder('s');

        if ($createAt) {
            $startDate = new DateTime($createAt);
            $endDate = clone $startDate;
            // Ajouter 1 jour à la date de fin pour inclure les signatures du jour de fin
            $endDate->modify('+1 day');

            $signatureQuery->andWhere('s.createAt >= :startDate')
                ->andWhere('s.createAt < :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        if ($name) {
            $signatureQuery->andWhere('LOWER(s.name) LIKE LOWER(:nom)')
                ->setParameter('nom', '%' . $name . '%');
        }

        if ($mail) {
            $signatureQuery->andWhere('LOWER(s.email) LIKE LOWER(:email)')
                ->setParameter('email', '%' . $mail . '%');
        }
        $allSignatureQuery = $signatureQuery->getQuery();

        // Paginer les signatures
        /** @var PaginationInterface|SlidingPagination $pagination */
        $pagination = $paginator->paginate(
            $allSignatureQuery,
            $page,
            $itemsPerPage
        );



        $users = $userRepository->findAll();
        $allSignatures = $signatureRepository->findAll();

        return $this->render('admin/home.html.twig', [
            'users' => $users,
            'signatures' => $allSignatures,
            'pagination' => $pagination,
            'createAt' => $createAt,
            'name' => $name,
            'mail' => $mail,
            'user' => $user,
        ]);
    }

    #[Route('/admin-users', name: 'admin_index', methods: ["GET"])]
    /**
     * @Route("/admin/users", name="admin_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository, SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);

            if ($user && $user->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $users = $userRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/delete/{id}', name: 'user_delete', methods: ['POST'])]
    /**
     * @Route("/admin/user/delete/{id}", name="user_delete", methods={"POST"})
     */
    public function delete(Request $request, User $userToDelete, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }
        $userId = $session->get('user_id');

        if ($userId) {
            $currentUser = $userRepository->find($userId);

            if ($currentUser && $currentUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $adminId = $session->get('user_id');

        if ($adminId) {
            $adminUser = $userRepository->find($adminId);

            if ($adminUser && $adminUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $entityManager->remove($userToDelete);
        $entityManager->flush();

        return $this->redirectToRoute('admin_index');
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
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $defaultPassword = "unsa_white_knight_pass_word_very_long";

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Ce champ ne doit pas être vide.'
                    ]),
                    new Regex([
                        'pattern' => '/^[\w.%+-]+@[\w.-]+\.[a-zA-Z]{2,}$/i',
                        'message' => 'L\'adresse email doit être valide. Elle doit suivre le format standard nom_utilisateur@domaine.com.'
                    ]),
                ],
            ])
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

            $token = Uuid::v4();
            $tokenExpiration = new DateTime();
            $tokenExpiration->modify('+24 hours');

            $tokenWithExpiration = $token . '-' . str_replace([' ', ':'], '-', $tokenExpiration->format('d-m-Y H:i:s'));

            $user->setToken($tokenWithExpiration);


            $entityManager->persist($user);
            $entityManager->flush();

            $to = $email;
            $subject = '[UNSA SIGNATURE] ' . mb_encode_mimeheader('Création de compte', 'UTF-8'); //encode le sujet en UTF-8 pour les caractères spéciaux

            $message = '
                <!DOCTYPE html
                  PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
                  xmlns:o="urn:schemas-microsoft-com:office:office">
                        
                <head>
                  <!--[if gte mso 9]><xml>
                <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
                </xml><![endif]-->
                  <title>Mot de passe</title>
                  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                  <meta name="viewport" content="width=device-width, initial-scale=1.0 " />
                  <meta name="format-detection" content="telephone=no" />
                  <!--[if !mso]><!-->
                  <link href="https://fonts.googleapis.com/css?family=Lato:100,100i,300,300i,400,700,700i,900,900i" rel="stylesheet" />
                  <!--<![endif]-->
                        
                        
                </head>
                        
                <body class="em_body" style="margin:0px auto; padding:0px;" bgcolor="#efefef">
                  <td align="center" valign="top" style="padding:0 25px; background-color:#ffffff;" class="em_aside10">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
                      <tr>
                        <td height="45" style="block-size:45px;" class="em_h20">&nbsp;</td>
                      </tr>
                      <tr>
                        <td class="em_blue em_font_22" align="center" valign="top"
                          style="font-family: Arial, sans-serif; font-size: 26px; line-height: 29px; color:#264780; font-weight:bold;">
                          Création de votre compte</td>
                      </tr>
                      <tr>
                        <td height="14" style="block-size:14px; font-size:0px; line-height:0px;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td class="em_grey" align="center" valign="top"
                          style="font-family: Arial, sans-serif; font-size: 16px; line-height: 26px; color:#434343;">Veuillez créer
                          votre mot de passe en suivant le lien ci-dessous pour l\'activation de votre espace personnel :</td>
                      </tr>
                      <tr>
                        <td height="26" style="block-size:26px;" class="em_h20">&nbsp;</td>
                      </tr>
                      <tr>
                        <td align="center" valign="top">
                          <table width="250" style="inline-size:250px; background-color:#6bafb2; border-radius:4px;" border="0"
                            cellspacing="0" cellpadding="0" align="center">
                            <tr>
                              <td class="em_white" height="42" align="center" valign="middle"
                                style="font-family: Arial, sans-serif; font-size: 16px; color:#ffffff; font-weight:bold; block-size:42px;">
                                <a href="' . $this->generateUrl('create_password', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL) . '"
                                  target="_blank" style="text-decoration:none; color:#ffffff; line-height:42px; display:block;">Créer votre mot de passe</a>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td height="25" style="block-size:25px;" class="em_h20">&nbsp;</td>
                      </tr>
                      <tr>
                        <td class="em_grey" align="center" valign="top"
                          style="font-family: Arial, sans-serif; font-size: 16px; line-height: 26px; color:#434343;">Si vous n\'avez pas
                          demandé de création de mot de passe ou de compte, vous n\'avez rien à faire.<br class="em_hide" />
                          Ignorez simplement cet e-mail comme votre chat vous ignore.</td>
                      </tr>
                      <tr>
                        <td height="44" style="block-size:44px;" class="em_h20">&nbsp;</td>
                      </tr>
                    </table>
                  </td>
                </body>
                        
                </html>';

            $headers = 'From: support@signature_unsa.org' . "\r\n" .
                'Reply-To: amine.djellal@unsa.org' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
            $headers .= 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";


            mail($to, $subject, $message, $headers);


            $this->addFlash('success', 'L\'ajout d\'un nouvelle utilisateur a été effectué avec succès.Un e-mail vient de lui être envoyer pour crée son mot de passe');
        }

        return $this->render('admin/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('user/create-password/{token}', name: 'create_password', methods: ['GET', 'POST'])]
    /**
     * @Route("/user/create-password/{token}", name="create_password", methods={"GET","POST"})
     */
    public function createPassword(Request $request, EntityManagerInterface $entityManager, string $token)
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['token' => $token]);

        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        $userToken = $user->getToken();
        $today = new DateTime('now');

        $tokenCreationDate = DateTime::createFromFormat('d-m-Y-H-i-s', substr($userToken, -19));

        $diff = $tokenCreationDate->diff($today);

        if ($diff->h >= 24 || $diff->d > 0) {
            throw $this->createNotFoundException('Le lien de création de mot de passe a expiré');
        }

        $form = $this->createFormBuilder($user)
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les champs du mot de passe doivent correspondre.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options' => ['label' => 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).+$/',
                        'message' => 'Le mot de passe doit contenir au moins 8 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial.'
                    ]),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();
            $hashedPassword = hash('sha256', $newPassword);

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
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $currentEmail = $editUser->getEmail();
        $currentRole = $editUser->getRole();
        $currentPassword = $editUser->getPassword();

        $form = $this->createFormBuilder($editUser)
            ->add('email', EmailType::class, [
                'data' => $currentEmail,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir une adresse email',
                    ]),
                    new Regex([
                        'pattern' => '/^[\w.%+-]+@[\w.-]+\.[a-zA-Z]{2,}$/i',
                        'message' => 'Veuillez saisir une adresse email valide',
                    ]),
                ],
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

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $currentEmail]);
        $email = $user->getEmail();

        if ($form->isSubmitted() && $form->isValid()) {

            $editUser->setEmail($currentEmail);
            $editUser->setRole($currentRole);

            $newEmail = $form->get('email')->getData();
            $editUser->setEmail($newEmail);
            $newRole = $form->get('role')->getData();
            $editUser->setRole($newRole);

            $entityManager->flush();

            $this->addFlash('success', 'L\'enregistrement a été effectué avec succès.');
        }
        return $this->render('admin/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $editUser,
        ]);
    }

    #[Route('/admin-logo', name: 'admin_logo', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/logo", name="admin_logo", methods={"GET","POST"})
     */
    public function logo(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }
        $userId = $session->get('user_id');

        if ($userId) {
            $currentUser = $userRepository->find($userId);

            if ($currentUser && $currentUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $currentDateTime = new DateTimeImmutable();

        $logo = new Logo();
        $logo->setCreateAt($currentDateTime);
        $logo->setUpdateAt($currentDateTime);

        $form = $this->createFormBuilder($logo)
            ->add('name', TextType::class, [
                'label' => 'Nom :',
                'required' => true,
                'attr' => ['placeholder' => 'Nom du logo'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir un nom',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*).{4,24}$/i',
                        'message' => 'Veuillez saisir un nom contenant au moins 4 caractères et au maximum 24 caractères.',
                    ]),
                ],
            ])
            ->add('refLink', TextType::class, [
                'label' => 'Adresse du site :',
                'required' => true,
                'attr' => ['placeholder' => 'Chemin du site'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir une adresse',
                    ]),
                    new Regex([
                        'pattern' => '/^(https?:\/\/)?(www\.)?.{1,255}$/i',
                        'message' => 'Veuillez saisir une URL valide commençant par  http://www. ou https://www., et ayant entre 1 et 255 caractères.',
                    ]),
                ],
            ])
            ->add('path', FileType::class, [
                'label' => 'Chemin du logo :',
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '1M',
                        'mimeTypes' => [
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier image valide.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Ajouter',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form->get('path')->getData();

            // Générer un nom de fichier unique
            // Utiliser le nom donné dans le formulaire
            $filename = '/img/Logo/' . $form->get('name')->getData() . '.png';

            // Déplacer le fichier vers le répertoire public/img
            $uploadedFile->move(
                $this->getParameter('kernel.project_dir') . '/public/img/Logo', // a modifier pour le dossier logo
                $filename
            );

            $logo->setPath($filename);

            $entityManager->persist($logo);
            $entityManager->flush();

            $this->addFlash('success', 'Le logo a été ajouté avec succès.');
            return $this->redirectToRoute('admin_logo');
        }

        $logoRepository = $entityManager->getRepository(Logo::class);
        $logos = $logoRepository->findAll();

        return $this->render('admin/logo.html.twig', [
            'form' => $form->createView(),
            'logos' => $logos,
        ]);
    }

    #[Route('/admin/logo/{id}/edit', name: 'admin_logo_edit', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/logo/{id}/edit", name="admin_logo_edit", methods={"GET","POST"})
     */
    public function editLogo(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator, $id)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }
        $userId = $session->get('user_id');

        if ($userId) {
            $currentUser = $userRepository->find($userId);

            if ($currentUser && $currentUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $logo = $entityManager->getRepository(Logo::class)->find($id);

        if (!$logo) {
            throw $this->createNotFoundException('Aucun logo trouvé ');
        }

        $currentDateTime = new DateTimeImmutable();

        $newLogo = new Logo();
        $newLogo->setCreateAt($currentDateTime);
        $newLogo->setUpdateAt($currentDateTime);

        $form = $this->createFormBuilder($newLogo)
            ->add('name', TextType::class, [
                'label' => 'Nom :',
                'required' => false,
                'attr' => ['placeholder' => 'Nouveau nom de logo'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir un nom',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*).{4,24}$/i',
                        'message' => 'Veuillez saisir un nom contenant au moins 4 caractères et au maximum 24 caractères.',
                    ]),
                ],
            ])
            ->add('refLink', TextType::class, [
                'label' => 'Adresse du site :',
                'required' => false,
                'attr' => ['placeholder' => 'Chemin du site'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir une adresse',
                    ]),
                    new Regex([
                        'pattern' => '/^(https?:\/\/)?(www\.)?.{1,255}$/i',
                        'message' => 'Veuillez saisir une URL valide commençant par http://www. ou https://www., et ayant entre 1 et 255 caractères.',
                    ]),
                ],
            ])
            ->add('path', FileType::class, [
                'label' => 'Chemin du logo :',
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1M',
                        'mimeTypes' => [
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier PNG valide.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Ajouter',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $form->get('path')->getData();

            if ($uploadedFile) {
                $filename = '/img/' . $form->get('name')->getData() . '.png';

                $uploadedFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/img',
                    $filename
                );

                $logo->setPath($filename);
            }

            $logo->setName($form->get('name')->getData());
            $logo->setRefLink($form->get('refLink')->getData());
            $logo->setUpdateAt(new DateTimeImmutable());

            $entityManager->flush();

            $this->addFlash('success', 'Le logo a été mis à jour avec succès.');
        }


        return $this->render('admin/edit_logo.html.twig', [
            'logo' => $logo,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin-logo/{id}/delete', name: 'admin_logo_delete', methods: ['GET'])]
    /**
     * @Route("/admin/logo/{id}/delete", name="admin_logo_delete", methods={"GET"})
     */
    public function deleteLogo(Request $request, EntityManagerInterface $entityManager, Logo $logo, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator): Response
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }
        $userId = $session->get('user_id');

        if ($userId) {
            $currentUser = $userRepository->find($userId);

            if ($currentUser && $currentUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $entityManager->remove($logo);
        $entityManager->flush();

        $this->addFlash('success', 'Le logo a été supprimé avec succès.');

        return $this->redirectToRoute('admin_logo');
    }

    #[Route('/admin-create-signature', name: 'admin_create_signature', methods: ['GET', 'POST'])]
    /**
     * @Route("/admin/create-signature", name="admin_create_signature", methods={"GET","POST"})
     */
    public function createSignature(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator, LogoRepository $logoRepository)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }
        $userId = $session->get('user_id');
        if ($userId) {
            $currentUser = $userRepository->find($userId);
            if ($currentUser && $currentUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $generatedSignature = '';
        $logos = $logoRepository->findAll();
        $logoChoices = [];
        $logo2Choices = [];

        foreach ($logos as $logo) {
            $name = $logo->getName();
            $path = $logo->getPath();
            $logoChoices[$name] = $logo;
        }
        foreach ($logos as $logo2) {
            $name = $logo2->getName();
            $path = $logo2->getPath();
            $logo2Choices[$name] = $logo2;
        }

        $form = $this->createFormBuilder()
            ->add('first_name', TextType::class, [
                'label' => 'Prénom(*) : ',
                'attr' => [
                    'placeholder' => 'Prénom',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre prénom.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ -]).{2,58}$/u',
                        'message' => 'Veuillez saisir un prénom contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 58 caractères.',
                    ]),
                ],
            ])
            ->add('last_name', TextType::class, [
                'label' => 'Nom(*) : ',
                'attr' => [
                    'placeholder' => 'Nom',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre nom.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ-09 -]).{2,58}$/u',
                        'message' => 'Veuillez saisir un nom contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 58 caractères.',
                    ]),
                ],
            ])
            ->add('role', TextType::class, [
                'label' => 'Rôle(*) : ',
                'attr' => [
                    'placeholder' => 'Poste dans l\'organisation',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre rôle dans l\'organisation.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ -]).{2,64}$/u',
                        'message' => 'Veuillez saisir un rôle contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 64 caractères.',
                    ]),
                ],
            ])
            ->add('organization', TextType::class, [
                'label' => 'Organisation(*) : ',
                'attr' => [
                    'placeholder' => 'Nom de l\'organisation'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir le nom de votre organisation.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ-09 -]).{2,64}$/u',
                        'message' => 'Veuillez saisir un nom d\'organisation contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 64 caractères.',
                    ]),
                ],
            ])
            ->add('adress', TextType::class, [
                'label' => 'Adresse(*) : ',
                'attr' => [
                    'placeholder' => 'Addresse postale',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre adresse.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ-09 -]).{2,24}$/u',
                        'message' => 'Veuillez saisir une adresse contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 24 caractères.',
                    ]),
                ],
            ])
            ->add('zip_code', TextType::class, [
                'label' => 'Code postal(*) : ',
                'attr' => [
                    'placeholder' => 'Code Postal',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre code postal.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[0-9]).{5,5}$/u',
                        'message' => 'Veuillez saisir un code postal contenant uniquement des chiffres et ayant une longueur de 5 caractères.',
                    ]),
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville(*) : ',
                'attr' => [
                    'placeholder' => 'Ville',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre ville.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ -]).{2,24}$/u',
                        'message' => 'Veuillez saisir une ville contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 24 caractères.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email(*) : ',
                'attr' => [
                    'placeholder' => 'Email',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre adresse email.'
                    ]),
                    new Regex([
                        'pattern' => '/^[\w.%+-]+@[\w.-]+\.[a-zA-Z]{2,}$/i',
                        'message' => 'L\'adresse email doit être valide. Elle doit suivre le format standard nom_utilisateur@domaine.com.'
                    ]),
                ],
            ])
            ->add('phone_landline', TelType::class, [
                'label' => 'Tél fixe : ',
                'attr' => [
                    'placeholder' => 'Tél fixe',
                ],
                'constraints' => [],
                'required' => false,
            ])
            ->add('phone_mobile', TelType::class, [
                'label' => 'Tél portable : ',
                'attr' => [
                    'placeholder' => 'Tél portable',
                ],
                'constraints' => [],
                'required' => false,
            ])
            ->add('logo', EntityType::class, [
                'label' => 'Logo(*) : ',
                'class' => Logo::class,
                'choice_label' => 'name',
                'choice_attr' => function ($key) {
                    return ['name' => $key->getPath()];
                },
                'choices' => $logoChoices,
                'required' => false,
                'placeholder' => 'Choisir un logo',
            ])
            ->add('logo_2', EntityType::class, [
                'label' => '2nd logo ?',
                'class' => Logo::class,
                'choice_label' => 'name',
                'choice_attr' => function ($key) {
                    return ['name' => $key->getPath()];
                },
                'choices' => $logo2Choices,
                'required' => false,
                'placeholder' => 'Choisir un logo ',
            ])
            ->add('signatureSubmit', SubmitType::class, [
                'label' => 'Générer la signature',
                'attr' => [
                    'class' => 'btn btn-primary',
                    'name' => 'signatureSubmit',
                ],
            ])
            ->add('facebook', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'https://www.facebook.fr',
                ],
                'required' => false,
            ])

            ->add('youtube', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'https://www.youtube.fr',
                ],
                'required' => false,
            ])

            ->add('unsa', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'https://www.unsa.org',
                ],
                'required' => false,
            ])

            ->add('linkedin', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'https://www.linkedin.fr',
                ],
                'required' => false,
            ])

            ->add('twitter', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'https://www.twitter.fr',
                ],
                'required' => false,
            ])
            ->getForm();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            if (isset($data['form']['signatureSubmit'])) {
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $data = $form->getData();
                    $signature = new Signature();
                    $signature->setName($data['first_name'] . '&' . $data['last_name']);
                    $signature->setRole($data['role']);
                    $signature->setOrganization($data['organization']);
                    $signature->setAdress($data['adress']);
                    $signature->setZipCode($data['zip_code']);
                    $signature->setCity($data['city']);
                    $signature->setEmail($data['email']);

                    if (!empty($data['phone_landline']) && !empty($data['phone_mobile'])) {
                        $signature->setPhone($data['phone_landline'] . ' - ' . $data['phone_mobile']);
                    } elseif (!empty($data['phone_landline']) && empty($data['phone_mobile'])) {
                        // Si $phone_mobile est vide, agissez en conséquence (par exemple, enregistrez $phone_landline)
                        $signature->setPhone($data['phone_landline']);
                    } elseif (!empty($data['phone_mobile']) && empty($data['phone_landline'])) {
                        // Si $phone_landline est vide, agissez en conséquence (par exemple, enregistrez $phone_mobile)
                        $signature->setPhone($data['phone_mobile']);
                    } else {
                        $signature->setPhone('');
                    }

                    $signature->setLogo($data['logo']);
                    $signature->setLogo2($data['logo_2']);

                    $unsaLink = $data['unsa'];
                    if ($unsaLink !== null) {
                        $signature->setUnsa($data['unsa']);
                    }
                    
                    $facebookLink = $data['facebook'];
                    if ($facebookLink !== null) {
                        $signature->setFacebook($data['facebook']);
                    }

                    $youtubeLink = $data['youtube'];
                    if ($youtubeLink !== null) {
                        $signature->setYoutube($data['youtube']);
                    }

                    $linkedinLink = $data['linkedin'];
                    if ($linkedinLink !== null) {
                        $signature->setLinkedin($data['linkedin']);
                    }

                    $twitterLink = $data['twitter'];
                    if ($twitterLink !== null) {
                        $signature->setTwitter($data['twitter']);
                    }

                    $signature->setUserId($session->get('user_id'));
                    // Définir la date de création
                    $createAt = new DateTimeImmutable();
                    $signature->setCreateAt($createAt);
                    // Définir la date de mise à jour (identique à la date de création initialement)
                    $updateAt = clone $createAt;
                    $signature->setUpdateAt($updateAt);
                    // Enregistrer l'entité dans la base de données
                    $entityManager->persist($signature);
                    $entityManager->flush();
                    // Générer la signature avec les données fournies
                    $generatedSignature = $this->generateEmailSignature($data);
                    $signatureID = $signature->getId();
                    $srcLogo = $signature->getLogo()->getRefLink();
                    if ($signature->getLogo2()) {
                        $srcLogo2 = $signature->getLogo2()->getRefLink();
                    } else {
                        $srcLogo2 = '';
                    }
                }
            }
        }

        if (empty($signatureID)) {
            $signatureID = null;
        }

        if (empty($srcLogo)) {
            $srcLogo = null;
        }

        if (empty($srcLogo2)) {
            $srcLogo2 = null;
        }

        return $this->render('admin/create_signature.html.twig', [
            'form' => $form->createView(),
            'signature' => $generatedSignature,
            'signatureID' => $signatureID,
            'srcLogo' => $srcLogo,
            'srcLogo2' => $srcLogo2,
            'logo2' => $logo2,
        ]);
    }

    private function generateEmailSignature(array $data): string
    {
        $socialLink = [
            "unsa" => "data:image/png;base64,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",
            
            "facebook" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAD30lEQVR4AayShW4cMRCGLQgzHArKDK9xor5KnyDMSZmZmZmZRWWuKMy8e3y7U8/KvXG6PlR+6dM3Wtv/tElYulnzHXI918HnvQZ7PNfgGfdvz1XQEJzFtz14B++y+crCO+D2XoG97ssw475swhwuGWSOK26T3zX24luWbZbehTxe1uS+YOiuCzGwOB+3mA2yAvcFU8cO7Mrsf30R3Lz4nfMcL+eglZyNkm3QGXZhJ0snzrOw3nE61uc4zQs4wmKOxC3PaIszZA4/I2On6zSsY8ni4P9Kx6loX/WJMCCOk5G47dCZ+r79HLtxR8LfueN45F318TDEORYiW4TJHBcvbf8QhT/TJoRiJkQME2bCJvRpJnwaM8TbuX24Q/k3wS82VR0JwRwOB8nSjF5xJgRvhgxIEtEj3kngLibHcQzc1YdDetWhICSj8iD5UW8MUsX+PoBGdNzJ/qXqQHBv5f4AVHCEBX4yp1LMG26EIJ2I+2hpFn18J8OsuQi5lXv9MxV7/ZAul35HIY0k7cCduJtV7tN95bt0sNgdt5g1sjR/GrX/+E9+j8DyI35xV7eh6q/cpftY2Q5tDwfSoVxYj5jwf5Yf4qV0j+bknXtY2bbZZxxIh9KtlkERfjaD52QFcgdSvl17xkq3zP4u3TwDSAlZAZ2pgmeZgrtZ6aZpraRrGpR0kuVZFTpXv1eBu1lpx5RW3DEFFu1kmWzSM21AiehC04wWc/uUxorbpn4XtU6CiuLWKcvZ5HVPxN7ZQkZwNytqnnxW1DwBSGETWSabnPsSkjuV4G5W2DC+hwPJyCZdL/22ngLJYt7DihonfAW1Y2BRR5bJJhtvaeL9OFoJ7mZrGiC3sHb8b6vlcdswFATRL2ZCDagAX12H+3DOOWe7A8d2fFQZlsRMXpzu6z8kVnGdPcDgDbRJ5InP/nFB3lFOTMlck1TVuW8gD8wzcRO3FeQfFvfeQUbeQU5M2K0yOGBJXlVjcmaO1HFTsernecPZTd/c3ZQ0CeTM7M+S3L0MNWbpT/a94abql7ebXDrburidECiZa5LsspZUZO+kzNI2Z31r9JPsmlx7M2namzHZG7J1vaQkp6qBff09csYN3FKS6jt5w1mPA2stos8sCb/b6zFzIPMcduOG+kzORjpurUaBtRKRuRwSsz8LqnpWQuaoV8MAu9V3VF/MG9Zi2DQXAxrwUlhSENeZA7aWwyY/+bc1tkKu/hOXxkLwZswHVLlTUlINNW0mjFnswC71W+FtGLOd+9ps+7k2qw/MtEmSrlNZB+c6z5jBrPo3nZNjzXQm1FTrjgSp6dYDakr3oFd9U+9y1lO9tTZT6gAAAABJRU5ErkJggg==",

            "youtube" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAA3ElEQVR4Ae2WIQzDIBBFEdPV6Ip6L2Y3Me9NMzuB98GbWrysqHf1otWYismm+vYFTRCEhXXjJrjkmSslLwHgi8vjxkoRKAL/J0BCSHAHGhjQgxHMwIKnY/Px+taNHd2/Bmg3p4wKuIH0Y1RQAB+ugDJxDgn0GQVMSMBmFJhDApSTYwLLQtR13xNAo0oWINS6ErXtpxInX0CmC3g1TURNkypQ+QL1IYG9hiFFRPIL8C8B/ybkO4bsFxH7Vcz+GLE/x5yBRL+LZDVQkTi2Ochn70dimQpFspKKi0AReAF/IVUTZ3/BGQAAAABJRU5ErkJggg==",

            "linkedin" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAABDUlEQVR4AWP4////gOLB44D6nTcsGIo33QHi/zTGd0B2YTiAPpYjHIHNAf/piQk6wGPW8f/rLz8HYRCbXg5AWI4GQGJ0cwDY12gAJDbcHUA4CkZAIqQUK7Ts/m/SfxBMs5RupswBaACr+P47b/5zlG/5DyzZ/r/+8hNF7vuvP//nn3r0X6JhJ+0ccPrR+/+H7735jw9cf/n5v0D1Nuo5gBxQve06zR0AjoL7b7/+//zjN4bc+ScfaOeA33///k9Yfg4mDw7u/Xdeo6uhnQP6D93FMNxlxjF0ZbRzgMXEQ9iyI90cALIMJoccDXRzAK6CZog6YNQBow6gIx54Bwx4x2RAu2bAysoEZu9o7xgAQrvkxt3WZi0AAAAASUVORK5CYII=",

            "twitter" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAAAAABWESUoAAAA3ElEQVR4Ac2SGQDFMBBE1ylOcYpTner0neK0TnGpU53iFKc6xWl+p+f25D7InZ295Cv0mGjFUOzWDVVVa/WykaBiaBBFfsiyory3JASRjs8mInqxUGRw47CIBIy7Ew0Sh0nED4OXCwkNh8h7GrrgCkVK9a7w6Q2BjoVa6Oo9EZEDVJ7I1M4UeMDXzIEhvoukFxNMaP8o4gpon1l9qnqc7DcPIgpd7Ce0t/fJVO0q0qIsVeuY1XwNYK1gwm8J2GAqvHRF5mD7BcG0Rl6yegjw0BqdakE8Bni0RyjyDf4Y1Y0n0wNT4wAAAABJRU5ErkJggg=="
        ];
        $html = '<!DOCTYPE html>';
        $html .= '<html lang="fr">';
        $html .= '<head>';
        $html .= '<meta charset="utf-8">';
        $html .= '<title>UNSA Signature</title>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="border p-3">';
        $html .= '<table border="0" cellpadding="0"';
        $html .= '<tbody>';
        $html .= '<tr>';
        $html .= '<td align="left" valign="middle" width="10">';
        $html .= '<p style="padding-inline-end: 10px;font-size: 12px;line-height: 14px;">';
        $html .= '<a href="' . $data['logo']->getRefLink() . '"><img id="LOGO"src="' . '/signature' . $data['logo']->getPath() . '" style="border: none;inline-size: 120px;"></a>';
        $html .= '</p>';
        $html .= '</td>';
        $html .= '<td><br>';
        $html .= '<p style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size: 12px; line-height: 14px; color: #000;text-align: start;">';
        $html .= '<span style="color: #000;font-weight: bold;font-size: 14px;">' . $data['first_name'] . ' ' . $data['last_name'] . '</span><br>';
        $html .= '<span style="color: #666;"><i>' . $data['role'] . '</i></span><br>';
        $html .= '<span style="color: #666;"><i>' . $data['organization'] . '</i></span><br>';
        $html .= '</p>';
        $html .= '<p style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size: 12px; line-height: 14px; color: #000;">';
        $html .= '<span style="color: #000;">' . $data['adress'] . '</span><br>';
        $html .= '<span style="color: #000;">' . $data['zip_code'] . '&nbsp;</span>';
        $html .= '<span style="color: #000;">' . $data['city'] . '</span><br>';
        $html .= '</p>';
        $html .= '<p style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size: 12px; line-height: 14px; color: #000;">';
        $html .= '<img id="LOGO-MAIL" src="/signature/img/Application/mail.png" style="border: none;block-size: 12px;margin-inline-end: .5em;">';
        $html .= '<a href="mailto:' . $data['email'] . '" style="color: #666;font-style: italic;">' . $data['email'] . '</a><br>';

        if (!empty($data['phone_landline']) && !empty($data['phone_mobile'])) {
            $html .= '<img id="LOGO-PHONE" src="/signature/img/Application/phone.png" style="border: none;block-size: 14px;margin-inline-end: .5em;">';
            $html .= '<span style="color: #666;">' . $data['phone_landline'] . '</span>';
            $html .= '<img id="LOGO-PHONE" src="/signature/img/Application/mobile.png" style="border: none;block-size: 14px;margin-inline-end: .5em;">';
            $html .= '<span style="color: #666;">' . $data['phone_mobile'] . '</span>';
        } elseif (!empty($data['phone_landline']) && empty($data['phone_mobile'])) {
            $html .= '<img id="LOGO-PHONE" src="/signature/img/Application/phone.png" style="border: none;block-size: 14px;margin-inline-end: .5em;">';
            $html .= '<span style="color: #666;">' . $data['phone_landline'] . '</span>';
        } elseif (!empty($data['phone_mobile']) && empty($data['phone_landline'])) {
            $html .= '<img id="LOGO-PHONE" src="/signature/img/Application/mobile.png" style="border: none;block-size: 14px;margin-inline-end: .5em;">';
            $html .= '<span style="color: #666;">' . $data['phone_mobile'] . '</span>';
        } else {
        }

        $html .= '</p>';
        $html .= '</td>';

        if ($data['logo_2'] !== null) {
            $html .= '<td align="right" valign="middle" width="10">';
            $html .= '<p style="padding-inline-end: 10px;font-size: 12px;line-height: 14px;">';
            $html .= '<a href="' . $data['logo_2']->getRefLink() . '"><img id="LOGO" src="' . '/signature' . $data['logo_2']->getPath() . '" style="border: none; inline-size: 120px; "></a>';
            $html .= '</p>';
            $html .= '</td>';
        }

        $html .= '</tr>';
        $html .= '</tbody>';
        $html .= '</table>';

        if ($data['unsa'] !== null) {
            $html .= "<a id=\"unsa\"href=" . $data['unsa'] . " style=\"color:#4267B2;font-weight: bold; font-size: 14px;text-decoration:none\"><img src=\"" . $socialLink['unsa'] . " \"> " . $data['unsa'] . "</a> &nbsp;";
        }
        
        if ($data['facebook'] !== null) {
            $html .= "<a id=\"facebook\" href=" . $data['facebook'] . " style=\"color:#4267B2;font-weight: bold; font-size: 14px;text-decoration:none\"><img src= \"" . $socialLink['facebook'] . "\"> " . $data['facebook'] . "</a> &nbsp;";
        }

        if ($data['youtube'] !== null) {
            $html .= "<a id=\"youtube\"href=" . $data['youtube'] . " style=\"color:#4267B2;font-weight: bold; font-size: 14px;text-decoration:none\"> <img src= \"" . $socialLink['youtube'] . " \"> " . $data['youtube'] . "</a> &nbsp;";
        }

        if ($data['linkedin'] !== null) {
            $html .= "<a id=\"linkedin\"href=" . $data['linkedin'] . " style=\"color:#4267B2;font-weight: bold; font-size: 14px;text-decoration:none\"> <img src= \" " . $socialLink['linkedin'] . " \"> " . $data['linkedin'] . "</a> &nbsp;";
        }

        if ($data['twitter'] !== null) {
            $html .= "<a id=\"twitter\"href=" . $data['twitter'] . " style=\"color:#4267B2;font-weight: bold; font-size: 14px;text-decoration:none\"> <img src= \" " . $socialLink['twitter'] . "\"> " . $data['twitter'] . "</a> &nbsp;";
        }

        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }

    #[Route('/admin/delete-signature/{id}', name: "admin_delete_signature", methods: ['GET'])]
    /**
     * @Route("/admin/delete-signature/{id}", name="admin_delete_signature", methods={"GET"})
     */
    public function deleteSignature(EntityManagerInterface $entityManager, Signature $signature, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }
        $userId = $session->get('user_id');

        if ($userId) {
            $currentUser = $userRepository->find($userId);

            if ($currentUser && $currentUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $entityManager->remove($signature);
        $entityManager->flush();

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin-send-password/{id}', name: "admin_send_password", methods: ['GET', 'POST'])]
    /**
     * @Route("/admin-send-password/{id}", name="admin_send_password", methods={"GET", "POST"})
     */
    public function sendPassword(UrlGeneratorInterface $urlGenerator, SessionInterface $session, UserRepository $userRepository, User $user, EntityManagerInterface $entityManager)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }
        $userId = $session->get('user_id');

        if ($userId) {
            $currentUser = $userRepository->find($userId);

            if ($currentUser && $currentUser->getRole() !== 'admin') {
                return $this->redirectToRoute('app_home');
            }
        } else {
            return $this->redirectToRoute('app_home');
        }

        $id = $user->getId();
        $token = $user->getToken();

        $today = new DateTime('now');

        $tokenCreationDate = DateTime::createFromFormat('d-m-Y-H-i-s', substr($token, -19));
        if (!$token) {
            $Newtoken = Uuid::v4();
            $tokenExpiration = new DateTime();
            $tokenExpiration->modify('+24 hours');

            $tokenWithExpiration = $Newtoken . '-' . str_replace([' ', ':'], '-', $tokenExpiration->format('d-m-Y H:i:s'));
            $user->setToken($tokenWithExpiration);
            $entityManager->flush();
        } else

            $diff = $tokenCreationDate->diff($today);

        if ($diff->h >= 24 || $diff->d > 0) {
            $Newtoken = Uuid::v4();
            $tokenExpiration = new DateTime();
            $tokenExpiration->modify('+24 hours');

            $tokenWithExpiration = $Newtoken . '-' . str_replace([' ', ':'], '-', $tokenExpiration->format('d-m-Y H:i:s'));
            $user->setToken($tokenWithExpiration);
            $entityManager->flush();

            $tokenWithExpiration = $token;
        }

        $to = $user->getEmail();
        $subject = '[UNSA SIGNATURE] ' . mb_encode_mimeheader('MODIFICATION DE MOT DE PASSE', 'UTF-8'); //encode le sujet en UTF-8 pour les caractères spéciaux

        $message = '
                <!DOCTYPE html
                  PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
                  xmlns:o="urn:schemas-microsoft-com:office:office">
                        
                <head>
                  <!--[if gte mso 9]><xml>
                <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
                </xml><![endif]-->
                  <title>Mot de passe</title>
                  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                  <meta name="viewport" content="width=device-width, initial-scale=1.0 " />
                  <meta name="format-detection" content="telephone=no" />
                  <!--[if !mso]><!-->
                  <link href="https://fonts.googleapis.com/css?family=Lato:100,100i,300,300i,400,700,700i,900,900i" rel="stylesheet" />
                  <!--<![endif]-->
                        
                        
                </head>
                        
                <body class="em_body" style="margin:0px auto; padding:0px;" bgcolor="#efefef">
                  <td align="center" valign="top" style="padding:0 25px; background-color:#ffffff;" class="em_aside10">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
                      <tr>
                        <td height="45" style="block-size:45px;" class="em_h20">&nbsp;</td>
                      </tr>
                      <tr>
                        <td class="em_blue em_font_22" align="center" valign="top"
                          style="font-family: Arial, sans-serif; font-size: 26px; line-height: 29px; color:#264780; font-weight:bold;">
                          Création de votre nouveau mot de passe</td>
                      </tr>
                      <tr>
                        <td height="14" style="block-size:14px; font-size:0px; line-height:0px;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td class="em_grey" align="center" valign="top"
                          style="font-family: Arial, sans-serif; font-size: 16px; line-height: 26px; color:#434343;">Veuillez créer
                          votre nouveau mot de passe en suivant le lien ci-dessous pour la connexion à votre espace personnel :</td>
                      </tr>
                      <tr>
                        <td height="26" style="block-size:26px;" class="em_h20">&nbsp;</td>
                      </tr>
                      <tr>
                        <td align="center" valign="top">
                          <table width="250" style="inline-size:250px; background-color:#6bafb2; border-radius:4px;" border="0"
                            cellspacing="0" cellpadding="0" align="center">
                            <tr>
                              <td class="em_white" height="42" align="center" valign="middle"
                                style="font-family: Arial, sans-serif; font-size: 16px; color:#ffffff; font-weight:bold; block-size:42px;">
                                <a href="' . $this->generateUrl('create_password', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL) . '"
                                  target="_blank" style="text-decoration:none; color:#ffffff; line-height:42px; display:block;">Modifier votre mot de passe</a>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td height="25" style="block-size:25px;" class="em_h20">&nbsp;</td>
                      </tr>
                      <tr>
                        <td class="em_grey" align="center" valign="top"
                          style="font-family: Arial, sans-serif; font-size: 16px; line-height: 26px; color:#434343;">Si vous n\'avez pas
                          demandé de réinitialisation de mot de passe, vous n\'avez rien à faire.<br class="em_hide" />
                          Ignorez simplement cet e-mail comme votre chat vous ignore.</td>
                      </tr>
                      <tr>
                        <td height="44" style="block-size:44px;" class="em_h20">&nbsp;</td>
                      </tr>
                    </table>
                  </td>
                </body>
                        
                </html>';

        $headers = 'From: support@signature_unsa.org' . "\r\n" .
            'Reply-To: amine.djellal@unsa.org' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";


        mail($to, $subject, $message, $headers);

        return new Response();
    }
}
