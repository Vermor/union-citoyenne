<?php

namespace App\Security;

use App\Entity\Supporter;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class SupporterEmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
    ) {
    }

    public function sendConfirmationEmail(Supporter $supporter): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_supporter_confirm',
            (string) $supporter->getId(),
            $supporter->getEmail(),
            ['id' => $supporter->getId()],
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'Union Citoyenne'))
            ->to($supporter->getEmail())
            ->subject('Confirmez votre adhesion a la charte')
            ->htmlTemplate('emails/supporter_confirmation.html.twig')
            ->context([
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAtMessageKey' => $signature->getExpirationMessageKey(),
                'expiresAtMessageData' => $signature->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, Supporter $supporter): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $supporter->getId(),
            $supporter->getEmail(),
        );
    }
}
