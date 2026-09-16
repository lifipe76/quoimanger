<?php

namespace App\Controller\_Logs;

use App\Entity\User;
use App\Enum\EtablissementType;
use App\Enum\UserType;
use App\Form\RegistrationFormType;
use App\Service\UserServices;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_ADMIN")]
class RegistrationController extends AbstractController
{
    use TargetPathTrait;

    #[Route('/register/distributeur', name: 'register.distributeur')]
    public function registerDistributeur(
        Request $request,
        UserServices $userServices,
        Security $security,
    ): Response {

        $user = new User();

        $form = $this->createForm(RegistrationFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $type = $form->get('type')->getData();

            $initialData = [
                'email' => $form->get('email')->getData(),
                'password' => $form->get('plainPassword')->getData(),
                'createForm' => true,
            ];


            $userServices->creationUser($user, $initialData);

            $security->login($user, 'App\Security\ManualAuthenticator');

            return $this->redirectToRoute('home');
        }

        return $this->render('_logs/register.html.twig', [
            'registrationForm' => $form,
            'isNotError' => !$form->isSubmitted(),
            'cse' => false,
            'pro' => false,
            'titre' => 'distributeurs.bouton.creer',
        ]);
    }
}
