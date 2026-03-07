<?php

namespace App\Controller;

use App\Repository\SupporterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(SupporterRepository $supporterRepository): Response
    {
        try {
            $confirmedCount = $supporterRepository->countConfirmed();
        } catch (Throwable) {
            $confirmedCount = 0;
        }

        return $this->render('home/index.html.twig', [
            'confirmedCount' => $confirmedCount,
        ]);
    }
}
