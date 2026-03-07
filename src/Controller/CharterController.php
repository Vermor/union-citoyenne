<?php

namespace App\Controller;

use App\Repository\SupporterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class CharterController extends AbstractController
{
    #[Route('/charte', name: 'app_charte')]
    public function index(SupporterRepository $supporterRepository): Response
    {
        try {
            $confirmedCount = $supporterRepository->countConfirmed();
        } catch (Throwable) {
            $confirmedCount = 0;
        }

        return $this->render('charter/index.html.twig', [
            'confirmedCount' => $confirmedCount,
        ]);
    }
}
