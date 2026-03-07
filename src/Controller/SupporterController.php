<?php

namespace App\Controller;

use App\Entity\Supporter;
use App\Form\SupporterAdhesionType;
use App\Repository\SupporterRepository;
use App\Security\SupporterEmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class SupporterController extends AbstractController
{
    #[Route('/adherer', name: 'app_supporter_adhere')]
    public function adhere(
        Request $request,
        SupporterRepository $supporterRepository,
        EntityManagerInterface $entityManager,
        SupporterEmailVerifier $emailVerifier,
    ): Response {
        $form = $this->createForm(SupporterAdhesionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isPotentialSpam($request, (string) $form->get('website')->getData())) {
                return $this->redirectToRoute('app_supporter_confirmation_sent');
            }

            $email = (string) $form->get('email')->getData();
            $acceptsFutureContact = (bool) $form->get('acceptsFutureContact')->getData();
            $existing = $supporterRepository->findOneByEmailInsensitive($email);

            if ($existing instanceof Supporter) {
                if ($existing->isConfirmed()) {
                    $this->addFlash('info', 'Cette adresse email a deja adhere a la charte.');

                    return $this->redirectToRoute('app_supporter_adhere');
                }

                $existing
                    ->setAcceptsFutureContact($acceptsFutureContact)
                    ->setAgreesToCharter(true)
                    ->setConfirmationSentAt(new \DateTimeImmutable())
                    ->setIpHash($this->hashIp($request->getClientIp()));

                $entityManager->flush();
                try {
                    $emailVerifier->sendConfirmationEmail($existing);
                } catch (TransportExceptionInterface) {
                    $this->addFlash('error', 'Impossible d’envoyer l’email pour le moment. Merci de réessayer plus tard.');

                    return $this->redirectToRoute('app_supporter_adhere');
                }
                $this->addFlash('success', 'Un nouvel email de confirmation vous a ete envoye.');

                return $this->redirectToRoute('app_supporter_confirmation_sent');
            }

            $supporter = (new Supporter())
                ->setEmail($email)
                ->setAgreesToCharter(true)
                ->setAcceptsFutureContact($acceptsFutureContact)
                ->setIsConfirmed(false)
                ->setConfirmationSentAt(new \DateTimeImmutable())
                ->setIpHash($this->hashIp($request->getClientIp()));

            $entityManager->persist($supporter);
            $entityManager->flush();

            try {
                $emailVerifier->sendConfirmationEmail($supporter);
            } catch (TransportExceptionInterface) {
                $this->addFlash('error', 'Impossible d’envoyer l’email pour le moment. Merci de réessayer plus tard.');

                return $this->redirectToRoute('app_supporter_adhere');
            }

            return $this->redirectToRoute('app_supporter_confirmation_sent');
        }

        return $this->render('supporter/adhere.html.twig', [
            'form' => $form,
            'confirmedCount' => $supporterRepository->countConfirmed(),
        ]);
    }

    #[Route('/adherer/confirmation-envoyee', name: 'app_supporter_confirmation_sent')]
    public function confirmationSent(SupporterRepository $supporterRepository): Response
    {
        return $this->render('supporter/confirmation_sent.html.twig', [
            'confirmedCount' => $supporterRepository->countConfirmed(),
        ]);
    }

    #[Route('/adherer/confirmer/{id}', name: 'app_supporter_confirm')]
    public function confirm(
        Request $request,
        int $id,
        SupporterRepository $supporterRepository,
        EntityManagerInterface $entityManager,
        SupporterEmailVerifier $emailVerifier,
    ): Response {
        $supporter = $supporterRepository->find($id);
        if (!$supporter instanceof Supporter) {
            throw $this->createNotFoundException();
        }

        if ($supporter->isConfirmed()) {
            return $this->render('supporter/confirmed.html.twig', [
                'confirmedCount' => $supporterRepository->countConfirmed(),
                'alreadyConfirmed' => true,
            ]);
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $supporter);
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('error', 'Le lien de confirmation est invalide ou a expire.');

            return $this->redirectToRoute('app_supporter_adhere');
        }

        $supporter
            ->setIsConfirmed(true)
            ->setConfirmedAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $this->render('supporter/confirmed.html.twig', [
            'confirmedCount' => $supporterRepository->countConfirmed(),
            'alreadyConfirmed' => false,
        ]);
    }

    private function isPotentialSpam(Request $request, string $honeypotValue): bool
    {
        if (trim($honeypotValue) !== '') {
            return true;
        }

        if (!$request->hasSession()) {
            return false;
        }

        $session = $request->getSession();
        $key = 'supporter_last_submit_at';
        $now = time();
        $lastSubmitAt = (int) $session->get($key, 0);

        if ($lastSubmitAt !== 0 && ($now - $lastSubmitAt) < 5) {
            return true;
        }

        $session->set($key, $now);

        return false;
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip);
    }
}
