<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\SignatureRepository;
use App\Repository\LogoRepository;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\Pagination\SlidingPagination;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Mailgun\Mailgun;
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
use App\Entity\Signature;
use DateTime;
use DateTimeImmutable;
use App\Entity\Logo;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\DataTransformer\FileTransformer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

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
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
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
            $startDate = new \DateTime($createAt);
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

    #[Route('/admin/user/delete/{id}', name: 'user_delete', methods: ['POST'])]
    /**
     * @Route("/admin/user/delete/{id}", name="user_delete", methods={"POST"})
     */
    public function delete(Request $request, User $userToDelete, EntityManagerInterface $entityManager, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator)
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

            $token = Uuid::v4();
            $tokenExpiration = new DateTime();
            $tokenExpiration->modify('+24 hours');

            $tokenWithExpiration = $token . '-' . str_replace([' ', ':'], '-', $tokenExpiration->format('d-m-Y H:i:s'));

            $user->setToken($tokenWithExpiration);


            $entityManager->persist($user);
            $entityManager->flush();

            //configurer mon server smtp pcq mailgun c'est pas fou
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

            $newEmail = $form->get('email')->getData();
            $editUser->setEmail($newEmail);
            $newRole = $form->get('role')->getData();
            $editUser->setRole($newRole);

            $newPassword = $editUser->getPassword();

            if (!empty($newPassword)) {
                $hashedPassword = hash('sha256', $newPassword);
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
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
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

            ])
            ->add('path', FileType::class, [
                'label' => 'Chemin du logo :',
                'required' => true,
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
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form->get('path')->getData();

            // Générer un nom de fichier unique
            // Utiliser le nom donné dans le formulaire
            $filename = '/img/' . $form->get('name')->getData() . '.png';

            // Déplacer le fichier vers le répertoire public/img
            $uploadedFile->move(
                $this->getParameter('kernel.project_dir') . '/public/img',
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
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
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
                'required' => true,
                'attr' => ['placeholder' => 'Nouveau nom de logo'],

            ])
            ->add('path', FileType::class, [
                'label' => 'Chemin du logo :',
                'required' => true,
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
            $logo->setUpdateAt(new \DateTimeImmutable());

            $entityManager->flush();

            $this->addFlash('success', 'Le logo a été mis à jour avec succès.');
            return $this->redirectToRoute('admin_logo');
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
    public function deleteLogo(Request $request, EntityManagerInterface $entityManager, Logo $logo): Response
    {
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
                throw $this->createAccessDeniedException('Access Denied');
            }
        } else {
            throw $this->createAccessDeniedException('Access Denied');
        }

        $generatedSignature = '';
        $logos = $logoRepository->findAll();
        $logoChoices = [];

        foreach ($logos as $logo) {
            $name = $logo->getName();
            $path = $logo->getPath();
            $logoChoices[$name] = $logo;
        }

        $form = $this->createFormBuilder()
            ->add('first_name', TextType::class, [
                'label' => 'Prénom : ',
                'attr' => [
                    'placeholder' => 'Prénom',
                ],
            ])
            ->add('last_name', TextType::class, [
                'label' => 'Nom : ',
                'attr' => [
                    'placeholder' => 'Nom',
                ],
            ])
            ->add('role', TextType::class, [
                'label' => 'Rôle : ',
                'attr' => [
                    'placeholder' => 'Poste dans l\'entreprise',
                ],
            ])
            ->add('organization', TextType::class, [
                'label' => 'Organisation : ',
                'attr' => [
                    'placeholder' => 'Nom de l\'entreprise'
                ],
            ])
            ->add('adress', TextType::class, [
                'label' => 'Adresse : ',
                'attr' => [
                    'placeholder' => 'Addresse',
                ],
            ])
            ->add('zip_code', TextType::class, [
                'label' => 'Code postal : ',
                'attr' => [
                    'placeholder' => 'Code Postal',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville : ',
                'attr' => [
                    'placeholder' => 'Ville',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email : ',
                'attr' => [
                    'placeholder' => 'Email',
                ],
            ])
            ->add('phone_landline', TelType::class, [
                'label' => 'Téléphone fixe : ',
                'attr' => [
                    'placeholder' => 'Tél fixe',
                ],
            ])
            ->add('phone_mobile', TelType::class, [
                'label' => 'Téléphone portable : ',
                'attr' => [
                    'placeholder' => 'Tél portable',
                ],
            ])
            ->add('logo', EntityType::class, [
                'label' => 'Logo : ',
                'class' => Logo::class,
                'choice_label' => 'name',
                'choice_attr' => function ($key) {
                    return ['name' => $key->getPath()];
                },
                'choices' => $logoChoices,
                'required' => false,
            ])
            ->add('signatureSubmit', SubmitType::class, [
                'label' => 'Générer la signature',
                'attr' => [
                    'class' => 'btn btn-primary',
                    'name' => 'signatureSubmit', // Ajout de l'attribut name
                ],
            ])
            ->getForm();


        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            if (isset($data['form']['signatureSubmit'])) {
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $data = $form->getData();
                    // Créer une instance de l'entité Signature et définir les valeurs des propriétés
                    $signature = new Signature();
                    $signature->setName($data['first_name'] . ' ' . $data['last_name']);
                    $signature->setRole($data['role']);
                    $signature->setOrganization($data['organization']);
                    $signature->setAdress($data['adress']);
                    $signature->setZipCode($data['zip_code']);
                    $signature->setCity($data['city']);
                    $signature->setEmail($data['email']);
                    $signature->setPhone($data['phone_landline'] . ' - ' . $data['phone_mobile']);
                    $signature->setLogo($data['logo']);
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
                }
            }
        }
        return $this->render('admin/create_signature.html.twig', [
            'form' => $form->createView(),
            'signature' => $generatedSignature,
        ]);
    }

    private function generateEmailSignature(array $data): string
    {
        $html = '<!DOCTYPE html>';
        $html .= '<html lang="fr">';
        $html .= '<head>';
        $html .= '<meta charset="utf-8">';
        $html .= '<title>UNSA Signature</title>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<table border="0" cellpadding="0" width="500">';
        $html .= '<tbody>';
        $html .= '<tr>';
        $html .= '<td align="left" valign="middle" width="10">';
        $html .= '<p style="padding-inline-end: 10px;font-size: 12px;line-height: 14px;">';
        $html .= '<a href="https://www.unsa.org"><img id="LOGO"src="'.'https://lab-web.unsa.org/signature'. $data['logo']->getPath() . '" style="border: none;inline-size: 120px;"></a>';
        $html .= '</p>';
        $html .= '</td>';
        $html .= '<td>';
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
        $html .= '<img id="LOGO-MAIL" src="https://lab-web.unsa.org/signature/img/mail.png" style="border: none;block-size: 12px;margin-inline-end: .5em;">';
        $html .= '<a href="mailto:' . $data['email'] . '" style="color: #666;font-style: italic;">' . $data['email'] . '</a><br>';
        $html .= '<img id="LOGO-PHONE" src="https://lab-web.unsa.org/signature/img/phone.png" style="border: none;block-size: 14px;margin-inline-end: .5em;">';
        $html .= '<span style="color: #666;">' . $data['phone_landline'] . ' - ' . $data['phone_mobile'] . '</span>';
        $html .= '</p>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }
}
