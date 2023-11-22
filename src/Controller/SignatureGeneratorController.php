<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Logo;
use App\Entity\Signature;
use App\Repository\UserRepository;
use App\Repository\LogoRepository;
use App\Repository\SignatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\SlidingPagination;
use Knp\Component\Pager\Pagination\PaginationInterface;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\NotBlank;

class SignatureGeneratorController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/espace-perso', name: 'profile_signature')]
    /**
     * @Route("/espace-perso", name="profile_signature")
     */
    public function generateSignature(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UrlGeneratorInterface $urlGenerator, UserRepository $userRepository, LogoRepository $logoRepository, SignatureRepository $signatureRepository, PaginatorInterface $paginator): Response
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);
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
                        'message' => 'Veuillez saisir votre adresse postale.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ-09 -]).{2,24}$/u',
                        'message' => 'Veuillez saisir une adresse postale contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 24 caractères.',
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
                    'placeholder' => 'https://wwww.facebook.fr',
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

        $user = $userRepository->find($userId);

        $userForm = $this->createFormBuilder($user)
            ->add('password', PasswordType::class, [
                'label' => 'Vous souhaitez changez de mot de passe ?',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nouveau mot de passe',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).+$/',
                        'message' => 'Le mot de passe doit contenir au moins 8 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial.'
                    ]),
                ]
            ])
            ->add('userSubmit', SubmitType::class, [
                'label' => 'Modifier le mot de passe',
                'attr' => [
                    'class' => 'btn btn-primary',
                    'name' => 'userSubmit',
                    // Ajout de l'attribut name
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
                    }

                    $signature->setLogo($data['logo']);
                    $signature->setLogo2($data['logo_2']);

                    $facebookLink = $data['facebook'];
                    if ($facebookLink !== null) {
                        $signature->setFacebook($data['facebook']);
                    }

                    $unsaLink = $data['unsa'];
                    if ($unsaLink !== null) {
                        $signature->setUnsa($data['unsa']);
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

            if (isset($data['form']['userSubmit'])) {
                $userForm->handleRequest($request);

                if ($userForm->isSubmitted() && $userForm->isValid()) {
                    $password = $userForm->get('password')->getData();
                    // Vérifier si le bouton "userSubmit" a été cliqué
                    if ($password !== null && $password !== '') {
                        // Mettre à jour le mot de passe de l'utilisateur
                        $hashedPassword = hash('sha256', $password);
                        $user->setPassword($hashedPassword);
                    }
                    // Enregistrer l'utilisateur mis à jour dans la base de données
                    $entityManager->persist($user);
                    $entityManager->flush();
                    // Rediriger vers une page de confirmation ou vers le profil de l'utilisateur
                    $this->addFlash('success', 'Mot de passe changé avec succès');
                }
            }
        }

        $date = $request->query->get('date');
        $nom = $request->query->get('nom');
        $email = $request->query->get('email');
        // Récupérer les signatures de l'utilisateur connecté
        $page = $request->query->getInt('page', 1);
        // Nombre d'éléments par page
        $itemsPerPage = 5;
        // Récupérer toutes les signatures avec les conditions de recherche
        $signatureQuery = $signatureRepository->createQueryBuilder('s');

        if ($date) {
            $startDate = new \DateTime($date);
            $endDate = clone $startDate;
            // Ajouter 1 jour à la date de fin pour inclure les signatures du jour de fin
            $endDate->modify('+1 day');

            $signatureQuery->andWhere('s.createAt >= :startDate')
                ->andWhere('s.createAt < :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        if ($nom) {
            $signatureQuery->andWhere('LOWER(s.name) LIKE LOWER(:nom)')
                ->setParameter('nom', '%' . $nom . '%');
        }

        if ($email) {
            $signatureQuery->andWhere('LOWER(s.email) LIKE LOWER(:email)')
                ->setParameter('email', '%' . $email . '%');
        }

        $allSignatureQuery = $signatureQuery->getQuery();

        // Paginer les signatures
        /** @var PaginationInterface|SlidingPagination $pagination */
        $pagination = $paginator->paginate(
            $allSignatureQuery,
            $page,
            $itemsPerPage
        );

        if (empty($signatureID)) {
            $signatureID = null;
        }

        if (empty($srcLogo)) {
            $srcLogo = null;
        }

        if (empty($srcLogo2)) {
            $srcLogo2 = null;
        }

        return $this->render('signature/generate_signature.html.twig', [
            'form' => $form->createView(),
            'signature' => $generatedSignature,
            'pagination' => $pagination,
            'date' => $date,
            'nom' => $nom,
            'email' => $email,
            'userForm' => $userForm,
            'user' => $user,
            'signatureID' => $signatureID,
            'srcLogo' => $srcLogo,
            'srcLogo2' => $srcLogo2,
            'logo2' => $logo2,
        ]);
    }
    private function generateEmailSignature(array $data): string
    {
        $socialLink = [
            "facebook" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAD30lEQVR4AayShW4cMRCGLQgzHArKDK9xor5KnyDMSZmZmZmZRWWuKMy8e3y7U8/KvXG6PlR+6dM3Wtv/tElYulnzHXI918HnvQZ7PNfgGfdvz1XQEJzFtz14B++y+crCO+D2XoG97ssw475swhwuGWSOK26T3zX24luWbZbehTxe1uS+YOiuCzGwOB+3mA2yAvcFU8cO7Mrsf30R3Lz4nfMcL+eglZyNkm3QGXZhJ0snzrOw3nE61uc4zQs4wmKOxC3PaIszZA4/I2On6zSsY8ni4P9Kx6loX/WJMCCOk5G47dCZ+r79HLtxR8LfueN45F318TDEORYiW4TJHBcvbf8QhT/TJoRiJkQME2bCJvRpJnwaM8TbuX24Q/k3wS82VR0JwRwOB8nSjF5xJgRvhgxIEtEj3kngLibHcQzc1YdDetWhICSj8iD5UW8MUsX+PoBGdNzJ/qXqQHBv5f4AVHCEBX4yp1LMG26EIJ2I+2hpFn18J8OsuQi5lXv9MxV7/ZAul35HIY0k7cCduJtV7tN95bt0sNgdt5g1sjR/GrX/+E9+j8DyI35xV7eh6q/cpftY2Q5tDwfSoVxYj5jwf5Yf4qV0j+bknXtY2bbZZxxIh9KtlkERfjaD52QFcgdSvl17xkq3zP4u3TwDSAlZAZ2pgmeZgrtZ6aZpraRrGpR0kuVZFTpXv1eBu1lpx5RW3DEFFu1kmWzSM21AiehC04wWc/uUxorbpn4XtU6CiuLWKcvZ5HVPxN7ZQkZwNytqnnxW1DwBSGETWSabnPsSkjuV4G5W2DC+hwPJyCZdL/22ngLJYt7DihonfAW1Y2BRR5bJJhtvaeL9OFoJ7mZrGiC3sHb8b6vlcdswFATRL2ZCDagAX12H+3DOOWe7A8d2fFQZlsRMXpzu6z8kVnGdPcDgDbRJ5InP/nFB3lFOTMlck1TVuW8gD8wzcRO3FeQfFvfeQUbeQU5M2K0yOGBJXlVjcmaO1HFTsernecPZTd/c3ZQ0CeTM7M+S3L0MNWbpT/a94abql7ebXDrburidECiZa5LsspZUZO+kzNI2Z31r9JPsmlx7M2namzHZG7J1vaQkp6qBff09csYN3FKS6jt5w1mPA2stos8sCb/b6zFzIPMcduOG+kzORjpurUaBtRKRuRwSsz8LqnpWQuaoV8MAu9V3VF/MG9Zi2DQXAxrwUlhSENeZA7aWwyY/+bc1tkKu/hOXxkLwZswHVLlTUlINNW0mjFnswC71W+FtGLOd+9ps+7k2qw/MtEmSrlNZB+c6z5jBrPo3nZNjzXQm1FTrjgSp6dYDakr3oFd9U+9y1lO9tTZT6gAAAABJRU5ErkJggg==",

            "unsa" => "data:image/png;base64,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",

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

        if ($data['facebook'] !== null) {
            $html .= "<a id=\"facebook\" href=" . $data['facebook'] . " style=\"color:#4267B2;font-weight: bold; font-size: 14px;text-decoration:none\"><img src= \"" . $socialLink['facebook'] . "\"> " . $data['facebook'] . "</a> &nbsp;";
        }

        if ($data['unsa'] !== null) {
            $html .= "<a id=\"unsa\"href=" . $data['unsa'] . " style=\"color:#4267B2;font-weight: bold; font-size: 14px;text-decoration:none\"><img src=\"" . $socialLink['unsa'] . " \"> " . $data['unsa'] . "</a> &nbsp;";
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

    #[Route('/logout', name: 'logout')]
    /**
     * @Route("/logout", name="logout")
     */
    public function logout(SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        $session->invalidate();
        return new RedirectResponse($urlGenerator->generate('app_home'));
    }

    #[Route('/espace-perso', name: 'signature')]
    /**
     * @Route("/espace-perso", name="signature")
     */
    public function getSignature(SignatureRepository $signatureRepository, SessionInterface $session, UrlGeneratorInterface $urlGenerator, UserRepository $userRepository)
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);
        } else {
            return $this->redirectToRoute('app_home');
        }

        $allSignature = $signatureRepository->findAll();
        return $this->render('signature/generate_signature.html.twig', []);
    }

    #[Route('/signature/edit/{id}', name: 'edit_signature')]
    /**
     * @Route("/signature/edit/{id}", name="edit_signature")
     */
    public function editSignature(Request $request, SignatureRepository $signatureRepository, LogoRepository $logoRepository, $id, SessionInterface $session, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager): Response
    {
        if (!$session->has('user_id')) {
            return new RedirectResponse($urlGenerator->generate('app_home'));
        }

        $userId = $session->get('user_id');

        if ($userId) {
            $user = $userRepository->find($userId);
        } else {
            return $this->redirectToRoute('app_home');
        }
        // Récupérer l'ID de la signature à modifier à partir de la route
        $signatureId = $id;

        // Récupérer la signature existante à partir du repository
        $signature = $signatureRepository->find($signatureId);

        if (!$signature) {
            throw $this->createNotFoundException('La signature demandée n\'existe pas.');
        }

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
                        'message' => 'Veuillez saisir votre adresse postale.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-zÀ-ÿ-09 -]).{2,24}$/u',
                        'message' => 'Veuillez saisir une adresse postale contenant uniquement des lettres, des espaces, des tirets et ayant une longueur de 2 à 24 caractères.',
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
            ->add('facebook', TextType::class, [
                'label' => 'URL FACEBOOK',
                'attr' => [
                    'placeholder' => 'https://www.facebook.fr',
                ],
                'required' => false,
            ])

            ->add('youtube', TextType::class, [
                'label' => 'URL YOUTUBE',
                'attr' => [
                    'placeholder' => 'https://www.unsa.org',
                ],
                'required' => false,
            ])

            ->add('unsa', TextType::class, [
                'label' => 'URL UNSA',
                'attr' => [
                    'placeholder' => 'https://www.unsa.org',
                ],
                'required' => false,
            ])

            ->add('linkedin', TextType::class, [
                'label' => 'URL LINKEDIN',
                'attr' => [
                    'placeholder' => 'https://www.linkedin.fr',
                ],
                'required' => false,
            ])

            ->add('twitter', TextType::class, [
                'label' => 'URL TWITTER',
                'attr' => [
                    'placeholder' => 'https://www.twitter.fr',
                ],
                'required' => false,
            ])
            ->add('signatureSubmit', SubmitType::class, [
                'label' => 'Générer la signature',
                'attr' => [
                    'class' => 'btn btn-primary',
                    'name' => 'signatureSubmit',
                ],
            ])
            ->getForm();

        $nameParts = explode('&', $signature->getName());
        $phone = $signature->getPhone();
        $phoneLandline = "";
        $phoneMobile = "";
        if ($phone !== null) {
            if (strpos($phone, '-') !== false) {
                $phoneParts = explode('-', $phone);
                $phoneLandline = $phoneParts[0];
                $phoneMobile = $phoneParts[1];
            } elseif (substr($phone, 0, 2) === '01') {
                $phoneLandline = $phone;
                $phoneMobile = "";
            } elseif (substr($phone, 0, 2) === '06') {
                $phoneMobile = $phone;
                $phoneLandline = "";
            }
        } else {
        }
        $firstName = $nameParts[0];
        $lastName = $nameParts[1];

        $form->setData([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $signature->getRole(),
            'organization' => $signature->getOrganization(),
            'adress' => $signature->getAdress(),
            'zip_code' => $signature->getZipCode(),
            'city' => $signature->getCity(),
            'email' => $signature->getEmail(),
            'phone_landline' => $phoneLandline,
            'phone_mobile' => $phoneMobile,
            'logo' => $signature->getLogo(),
            'logo_2' => $signature->getLogo2(),
            'facebook' => $signature->getFacebook(),
            'unsa' => $signature->getUnsa(),
            'youtube' => $signature->getYoutube(),
            'linkedin' => $signature->getLinkedin(),
            'twitter' => $signature->getTwitter(),
        ]);

        $form->handleRequest($request);

        // Traitement de la modification de la signature
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les données modifiées à partir de la requête
            $newSignatureData = $form->getData();

            // Appliquer les modifications à la signature existante
            $signature->setName($newSignatureData['first_name'] . '&' . $newSignatureData['last_name']);
            $signature->setRole($newSignatureData['role']);
            $signature->setOrganization($newSignatureData['organization']);
            $signature->setAdress($newSignatureData['adress']);
            $signature->setZipCode($newSignatureData['zip_code']);
            $signature->setCity($newSignatureData['city']);
            $signature->setEmail($newSignatureData['email']);

            if (!empty($data['phone_landline']) && !empty($data['phone_mobile'])) {
                $signature->setPhone($data['phone_landline'] . ' - ' . $data['phone_mobile']);
            } elseif (!empty($data['phone_landline']) && empty($data['phone_mobile'])) {
                // Si $phone_mobile est vide, agissez en conséquence (par exemple, enregistrez $phone_landline)
                $signature->setPhone($data['phone_landline']);
            } elseif (!empty($data['phone_mobile']) && empty($data['phone_landline'])) {
                // Si $phone_landline est vide, agissez en conséquence (par exemple, enregistrez $phone_mobile)
                $signature->setPhone($data['phone_mobile']);
            } else {
            }

            $signature->setLogo($newSignatureData['logo']);
            $signature->setLogo2($newSignatureData['logo_2']);
            $signature->setFacebook($newSignatureData['facebook']);
            $signature->setUnsa($newSignatureData['unsa']);
            $signature->setYoutube($newSignatureData['youtube']);
            $signature->setLinkedin($newSignatureData['linkedin']);
            $signature->setTwitter($newSignatureData['twitter']);
            $entityManager->flush();
        }

        return $this->render('signature/edit_signature.html.twig', [
            'signature' => $signature,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/signature-png/{id}', name: 'png_signature')]
    /**
     * @Route("/signature-png/{id}", name="png_signature")
     */
    public function pngSignature(SignatureRepository $signatureRepository, $id): Response
    {
        $signature = $signatureRepository->find($id);
        $nameParts = explode('&', $signature->getName());
        $phoneLandline = "";
        $phoneMobile = "";
        $phone = $signature->getPhone();

        if ($phone !== null) {
            if (strpos($phone, '-') === false) {
                $deuxPremiersCaracteres = substr($phone, 0, 2);
        
                if ($deuxPremiersCaracteres === "01") {
                    $phoneLandline = $phone;
                    $phoneMobile = "";
                } elseif ($deuxPremiersCaracteres === "06") {
                    $phoneLandline = '';
                    $phoneMobile = $phone;
                    echo "La chaîne commence par '06'.";
                } else {
                    $phoneParts = explode('-', $phone);
        
                    // Vérifiez si l'index 1 existe avant de l'utiliser
                    $phoneLandline = isset($phoneParts[0]) ? $phoneParts[0] : '';
                    $phoneMobile = isset($phoneParts[1]) ? $phoneParts[1] : '';
                }
            }
        }       

        $firstName = $nameParts[0];
        $lastName = $nameParts[1];


        return $this->render('signature/png_signature.html.twig', [
            'signature' => $signature,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phoneLandline' => $phoneLandline,
            'phoneMobile' => $phoneMobile,
        ]);
    }

    #[Route('/signature-html/{id}', name: 'html_signature')]
    /**
     * @Route("/signature-html/{id}", name="html_signature")
     */
    public function htmlSignature(SignatureRepository $signatureRepository, $id): Response
    {
        $signature = $signatureRepository->find($id);
        $nameParts = explode('&', $signature->getName());
        $phoneLandline = "";
        $phoneMobile = "";
        $phone = $signature->getPhone();

        if ($phone !== null) {
            if (strpos($phone, '-') === false) {
                $deuxPremiersCaracteres = substr($phone, 0, 2);
        
                if ($deuxPremiersCaracteres === "01") {
                    $phoneLandline = $phone;
                    $phoneMobile = "";
                } elseif ($deuxPremiersCaracteres === "06") {
                    $phoneLandline = '';
                    $phoneMobile = $phone;
                    echo "La chaîne commence par '06'.";
                } else {
                    $phoneParts = explode('-', $phone);
        
                    // Vérifiez si l'index 1 existe avant de l'utiliser
                    $phoneLandline = isset($phoneParts[0]) ? $phoneParts[0] : '';
                    $phoneMobile = isset($phoneParts[1]) ? $phoneParts[1] : '';
                }
            }
        }       
        $firstName = $nameParts[0];
        $lastName = $nameParts[1];

        return $this->render('signature/html_signature.html.twig', [
            'signature' => $signature,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phoneLandline' => $phoneLandline,
            'phoneMobile' => $phoneMobile,
        ]);
    }
}
