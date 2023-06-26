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

class SignatureGeneratorController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/signature', name: 'profile_signature')]
    public function generateSignature(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, UrlGeneratorInterface $urlGenerator, UserRepository $userRepository, LogoRepository $logoRepository, SignatureRepository $signatureRepository, PaginatorInterface $paginator): Response
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

        $user = $userRepository->find($userId);

        $userForm = $this->createFormBuilder($user)
            ->add('password', PasswordType::class, [
                'label' => 'Vous souhaitez changez de mot de passe ?',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nouveau mot de passe',
                    'class' => 'form-control',
                ],
            ])
            ->add('userSubmit', SubmitType::class, [
                'label' => 'Modifier le mot de passe',
                'attr' => [
                    'class' => 'btn btn-primary',
                    'name' => 'userSubmit', // Ajout de l'attribut name
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

        return $this->render('signature/generate_signature.html.twig', [
            'form' => $form->createView(),
            'signature' => $generatedSignature,
            'pagination' => $pagination,
            'date' => $date,
            'nom' => $nom,
            'email' => $email,
            'userForm' => $userForm,
            'user' => $user,
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
        $html .= '<a href="https://www.unsa.org"><img id="LOGO"src="' . $data['logo']->getPath() . '" style="border: none;inline-size: 120px;"></a>';
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
        $html .= '<img id="LOGO-MAIL" src="/img/mail.png" style="border: none;block-size: 12px;margin-inline-end: .5em;">';
        $html .= '<a href="mailto:' . $data['email'] . '" style="color: #666;font-style: italic;">' . $data['email'] . '</a><br>';
        $html .= '<img id="LOGO-PHONE" src="/img/phone.png" style="border: none;block-size: 14px;margin-inline-end: .5em;">';
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

    #[Route('/logout', name: 'logout')]
    /**
     * @Route("/logout", name="logout")
     */
    public function logout(SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        $session->invalidate();
        return new RedirectResponse($urlGenerator->generate('app_home'));
    }

    /**
     * @Route("/signature", name="signature")
     */
    public function getSignature(SignatureRepository $signatureRepository)
    {
        $allSignature = $signatureRepository->findAll();
        return $this->render('signature/generate_signature.html.twig', []);
    }
}
