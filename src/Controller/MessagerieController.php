<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\RecetteRealisation;
use App\Entity\RepasProposition;
use App\Entity\RepasVote;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\RecetteRepository;
use App\Repository\RepasPropositionRepository;
use App\Service\PropositionNotifierService;
use App\Service\RecipeSuggestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MessagerieController extends AbstractController
{
    #[Route('/messagerie', name: 'app_messagerie')]
    public function index(
        ConversationRepository $conversationRepository,
        RecetteRepository $recetteRepository,
        \App\Repository\FamilleInvitationRepository $invitationRepository,
        EntityManagerInterface $em,
        RecipeSuggestionService $suggestionService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $famille = $user->getFamille();

        if (!$famille) {
            return $this->render('pages/pageComposant.html.twig', [
                'twig' => 'pages/messagerie',
                'has_famille' => false,
                'conversation' => null,
                'messages' => [],
                'recettes' => [],
                'ranks' => [],
            ]);
        }

        // Synchroniser automatiquement tout membre ayant accepté une invitation
        $acceptedInvitations = $invitationRepository->findBy([
            'famille' => $famille,
            'statut' => \App\Entity\FamilleInvitation::STATUT_ACCEPTEE,
        ]);
        $userRepo = $em->getRepository(User::class);
        $hasSynced = false;
        foreach ($acceptedInvitations as $accInv) {
            $accUser = $accInv->getInviteUser();
            if (!$accUser && $accInv->getInviteEmail()) {
                $accUser = $userRepo->findOneBy(['email' => $accInv->getInviteEmail()]);
            }
            if ($accUser && $accUser->getFamille()?->getId() !== $famille->getId()) {
                $accUser->setFamille($famille);
                $accInv->setInviteUser($accUser);
                $hasSynced = true;
            }
        }
        if ($hasSynced) {
            $em->flush();
            $em->refresh($famille);
        }

        // Trouver ou créer la conversation familiale
        $conversation = $conversationRepository->findOneBy(['famille' => $famille]);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setFamille($famille);
            $conversation->setTitre($famille->getNom());
            $em->persist($conversation);
            $em->flush();
        }

        $messages = $conversation->getMessages();
        $allRecettes = $recetteRepository->findAll();
        $ranks = $suggestionService->calculateRecipeRanks($allRecettes);
        $sortedRecettes = $suggestionService->sortRecettesByProbability($allRecettes, new \DateTimeImmutable('today'), $ranks);

        return $this->render('pages/pageComposant.html.twig', [
            'twig' => 'pages/messagerie',
            'has_famille' => true,
            'famille' => $famille,
            'conversation' => $conversation,
            'messages' => $messages,
            'recettes' => $sortedRecettes,
            'ranks' => $ranks,
            'defaultMoment' => $this->getDefaultMoment(),
        ]);
    }

    #[Route('/messagerie/send', name: 'app_messagerie_send', methods: ['POST'])]
    public function sendMessage(
        Request $request,
        ConversationRepository $conversationRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $famille = $user->getFamille();

        if (!$famille) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Aucune famille associée.'], Response::HTTP_FORBIDDEN);
            }
            return $this->redirectToRoute('app_messagerie');
        }

        $conversation = $conversationRepository->findOneBy(['famille' => $famille]);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setFamille($famille);
            $conversation->setTitre($famille->getNom());
            $em->persist($conversation);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            $payload = json_decode($request->getContent(), true);
            if (is_array($payload) && isset($payload['content'])) {
                $content = trim((string) $payload['content']);
            }
        }

        if ($content !== '') {
            $message = new Message();
            $message->setConversation($conversation);
            $message->setSender($user);
            $message->setContent($content);

            $em->persist($message);
            $em->flush();

            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse([
                    'success' => true,
                    'message' => [
                        'id' => $message->getId(),
                        'senderId' => $user->getId(),
                        'senderName' => $user->getDisplayName(),
                        'isMe' => true,
                        'content' => $message->getContent(),
                        'createdAt' => $message->getCreatedAt()->format('H:i'),
                        'proposition' => null,
                    ],
                ]);
            }
        }

        return $this->redirectToRoute('app_messagerie');
    }

    #[Route('/messagerie/proposer', name: 'app_repas_proposer', methods: ['POST'])]
    public function proposer(
        Request $request,
        RecetteRepository $recetteRepository,
        ConversationRepository $conversationRepository,
        PropositionNotifierService $notifierService,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $famille = $user->getFamille();

        if (!$famille) {
            $this->addFlash('danger', 'Vous devez faire partie d\'une famille pour proposer un repas.');
            return $this->redirectToRoute('home');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('proposer_repas', $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('home');
        }

        $recetteId = (int) $request->request->get('recette_id');
        $moment = strtolower(trim((string) $request->request->get('moment', 'soir')));
        if (!in_array($moment, ['matin', 'midi', 'soir'], true)) {
            $moment = 'soir';
        }
        $dateStr = (string) $request->request->get('date', '');

        $recette = $recetteRepository->find($recetteId);
        if (!$recette) {
            $this->addFlash('danger', 'Recette introuvable.');
            return $this->redirectToRoute('home');
        }

        try {
            $dateRepas = $dateStr !== '' ? new \DateTime($dateStr) : new \DateTime();
        } catch (\Exception) {
            $dateRepas = new \DateTime();
        }

        // Trouver ou créer la conversation
        $conversation = $conversationRepository->findOneBy(['famille' => $famille]);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setFamille($famille);
            $conversation->setTitre($famille->getNom());
            $em->persist($conversation);
        }

        // Créer la proposition
        $proposition = new RepasProposition();
        $proposition->setFamille($famille);
        $proposition->setProposePar($user);
        $proposition->setRecette($recette);
        $proposition->setDateRepas($dateRepas);
        $proposition->setMoment($moment);
        $proposition->setStatus(RepasProposition::STATUS_EN_ATTENTE);
        $em->persist($proposition);

        // Vote automatique pour le proposeur
        $autoVote = new RepasVote();
        $autoVote->setProposition($proposition);
        $autoVote->setUser($user);
        $autoVote->setChoix(RepasVote::CHOIX_POUR);
        $em->persist($autoVote);

        // Créer le message dans la discussion
        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setProposition($proposition);
        $message->setContent(sprintf(
            'Je propose : %s pour le %s du %s ! Qu\'en pensez-vous ?',
            $recette->getDesignation(),
            $moment,
            $dateRepas->format('d/m/Y')
        ));
        $em->persist($message);

        $em->flush();

        $this->addFlash('success', sprintf('Proposition pour « %s » envoyée à la famille !', $recette->getDesignation()));

        // Libérer le verrou de session immédiatement pour ne pas bloquer les requêtes suivantes
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }

        // Notifier les autres membres par email (ne bloquera plus la session PHP)
        $notifierService->notifyFamille($proposition);

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'message' => sprintf('Proposition pour « %s » envoyée à la famille !', $recette->getDesignation()),
                'redirect' => $this->generateUrl('app_messagerie'),
            ]);
        }

        return $this->redirectToRoute('app_messagerie');
    }

    #[Route('/messagerie/proposition/{id<\d+>}/supprimer', name: 'app_repas_supprimer_proposition', methods: ['POST'])]
    public function supprimerProposition(
        int $id,
        Request $request,
        RepasPropositionRepository $propositionRepository,
        MessageRepository $messageRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $famille = $user->getFamille();

        $proposition = $propositionRepository->find($id);
        if (!$proposition || !$famille || $proposition->getFamille()?->getId() !== $famille->getId()) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Proposition introuvable ou accès refusé.'], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('danger', 'Proposition introuvable.');
            return $this->redirectToRoute('app_messagerie');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('supprimer_proposition_' . $proposition->getId(), $token)) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_messagerie');
        }

        // L'auteur ou le créateur/chef de famille a le droit de supprimer
        $isAuthor = $proposition->getProposePar()?->getId() === $user->getId();
        $isChef = $famille->getCreateur()?->getId() === $user->getId();

        if (!$isAuthor && !$isChef) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer cette proposition.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à supprimer cette proposition.');
            return $this->redirectToRoute('app_messagerie');
        }

        $nomRecette = $proposition->getRecette()?->getDesignation() ?? 'Proposition';

        // Supprimer également les messages associés à cette proposition
        $messagesLie = $messageRepository->findBy(['proposition' => $proposition]);
        foreach ($messagesLie as $msg) {
            $em->remove($msg);
        }

        $em->remove($proposition);
        $em->flush();

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'deletedId' => $id,
            ]);
        }

        $this->addFlash('success', sprintf('La proposition pour « %s » a bien été supprimée.', $nomRecette));
        return $this->redirectToRoute('app_messagerie');
    }

    #[Route('/messagerie/vote/{id<\d+>}', name: 'app_repas_vote', methods: ['POST'])]
    public function voter(
        int $id,
        Request $request,
        RepasPropositionRepository $propositionRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $famille = $user->getFamille();

        $proposition = $propositionRepository->find($id);
        if (!$proposition || !$famille || $proposition->getFamille()?->getId() !== $famille->getId()) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Proposition introuvable ou accès refusé.'], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('danger', 'Proposition introuvable.');
            return $this->redirectToRoute('app_messagerie');
        }

        $token = (string) $request->request->get('_token', '');
        if ($token !== '' && !$this->isCsrfTokenValid('vote_proposition_' . $proposition->getId(), $token)) {
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                return new JsonResponse(['error' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_messagerie');
        }

        $choix = strtolower(trim((string) $request->request->get('choix', 'pour')));
        if (!in_array($choix, [RepasVote::CHOIX_POUR, RepasVote::CHOIX_CONTRE], true)) {
            $choix = RepasVote::CHOIX_POUR;
        }

        // Trouver ou créer le vote
        $vote = $proposition->getUserVote($user);
        if (!$vote) {
            $vote = new RepasVote();
            $vote->setProposition($proposition);
            $vote->setUser($user);
            $em->persist($vote);
        }
        $vote->setChoix($choix);
        $vote->setVotedAt(new \DateTimeImmutable());

        // Logique de validation : si chef de famille vote pour ou majorité de votes pour
        $totalMembres = $famille->getMembres()->count();
        $votesPourCount = count($proposition->getVotesPour()) + ($choix === RepasVote::CHOIX_POUR && !$proposition->getUserVote($user) ? 1 : 0);
        $isChef = $famille->getCreateur()?->getId() === $user->getId();

        if ($proposition->getStatus() === RepasProposition::STATUS_EN_ATTENTE) {
            // Le chef valide ou majorité atteinte
            if (($isChef && $choix === RepasVote::CHOIX_POUR) || ($totalMembres > 1 && $votesPourCount >= ceil($totalMembres / 2))) {
                $proposition->setStatus(RepasProposition::STATUS_VALIDEE);
            }
        }

        $em->flush();

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'success' => true,
                'status' => $proposition->getStatus(),
                'votesPour' => count($proposition->getVotesPour()),
                'votesContre' => count($proposition->getVotesContre()),
                'myVote' => $choix,
            ]);
        }

        $this->addFlash('success', 'Votre vote a bien été enregistré.');
        return $this->redirectToRoute('app_messagerie');
    }

    #[Route('/messagerie/valider-au-fil/{id<\d+>}', name: 'app_repas_valider_au_fil', methods: ['POST'])]
    public function validerAuFil(
        int $id,
        Request $request,
        RepasPropositionRepository $propositionRepository,
        ConversationRepository $conversationRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $famille = $user->getFamille();

        $proposition = $propositionRepository->find($id);
        if (!$proposition || !$famille || $proposition->getFamille()?->getId() !== $famille->getId()) {
            $this->addFlash('danger', 'Proposition introuvable.');
            return $this->redirectToRoute('app_messagerie');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('valider_au_fil_' . $proposition->getId(), $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_messagerie');
        }

        // Créer la réalisation
        $realisation = new RecetteRealisation();
        $realisation->setRecette($proposition->getRecette());
        $realisation->setMoment($proposition->getMoment());

        $dateRepas = $proposition->getDateRepas();
        if ($dateRepas) {
            $realisation->setRealiseAt(\DateTimeImmutable::createFromInterface($dateRepas));
        } else {
            $realisation->setRealiseAt(new \DateTimeImmutable());
        }

        $em->persist($realisation);

        $proposition->setRealisation($realisation);
        $proposition->setStatus(RepasProposition::STATUS_AJOUTEE_AU_FIL);

        // Message système dans la discussion
        $conversation = $conversationRepository->findOneBy(['famille' => $famille]);
        if ($conversation) {
            $sysMessage = new Message();
            $sysMessage->setConversation($conversation);
            $sysMessage->setSender($user);
            $sysMessage->setContent(sprintf(
                'Repas validé par %s ! « %s » a été ajouté au fil pour le %s du %s.',
                $user->getDisplayName(),
                $proposition->getRecette()->getDesignation(),
                $proposition->getMoment(),
                $dateRepas ? $dateRepas->format('d/m/Y') : date('d/m/Y')
            ));
            $em->persist($sysMessage);
        }

        $em->flush();

        $this->addFlash('success', sprintf('« %s » a été ajouté à votre fil des repas !', $proposition->getRecette()->getDesignation()));

        return $this->redirectToRoute('home');
    }

    #[Route('/messagerie/api/messages', name: 'app_messagerie_api_messages', methods: ['GET'])]
    public function apiMessages(
        Request $request,
        ConversationRepository $conversationRepository,
        MessageRepository $messageRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $famille = $user->getFamille();

        if (!$famille) {
            return new JsonResponse(['messages' => []]);
        }

        $conversation = $conversationRepository->findOneBy(['famille' => $famille]);
        if (!$conversation) {
            return new JsonResponse(['messages' => []]);
        }

        $sinceId = (int) $request->query->get('since_id', 0);
        $messages = $messageRepository->findMessagesAfter($conversation->getId(), $sinceId);

        $data = [];
        foreach ($messages as $msg) {
            $prop = $msg->getProposition();
            $propData = null;
            if ($prop) {
                $isAuthor = $prop->getProposePar()?->getId() === $user->getId();
                $isChef = $famille->getCreateur()?->getId() === $user->getId();
                $myVote = $prop->getUserVote($user);
                $propData = [
                    'id' => $prop->getId(),
                    'recetteId' => $prop->getRecette()?->getId(),
                    'recetteNom' => $prop->getRecette()?->getDesignation(),
                    'dateRepas' => $prop->getDateRepas()?->format('d/m/Y'),
                    'moment' => $prop->getMoment(),
                    'status' => $prop->getStatus(),
                    'proposePar' => $prop->getProposePar()?->getDisplayName(),
                    'votesPour' => array_map(fn($v) => $v->getUser()?->getDisplayName(), $prop->getVotesPour()),
                    'votesContre' => array_map(fn($v) => $v->getUser()?->getDisplayName(), $prop->getVotesContre()),
                    'myVote' => $myVote?->getChoix(),
                    'isValidee' => $prop->isValidee(),
                    'isAjouteeAuFil' => $prop->isAjouteeAuFil(),
                    'canDelete' => ($isAuthor || $isChef),
                    'csrfToken' => $csrfTokenManager->getToken('supprimer_proposition_' . $prop->getId())->getValue(),
                ];
            }

            $data[] = [
                'id' => $msg->getId(),
                'senderId' => $msg->getSender()?->getId(),
                'senderName' => $msg->getSender()?->getDisplayName(),
                'isMe' => $msg->getSender()?->getId() === $user->getId(),
                'content' => $msg->getContent(),
                'createdAt' => $msg->getCreatedAt()->format('H:i'),
                'createdAtDate' => $msg->getCreatedAt()->format('d/m/Y'),
                'proposition' => $propData,
            ];
        }

        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }

        return new JsonResponse([
            'messages' => $data,
            'familleNom' => $famille->getNom(),
        ]);
    }

    private function getDefaultMoment(): string
    {
        $hour = (int) date('G');
        if ($hour < 11) {
            return 'matin';
        }
        if ($hour < 16) {
            return 'midi';
        }
        return 'soir';
    }
}
