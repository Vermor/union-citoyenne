<?php

namespace App\Controller;

use App\Repository\SupporterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(SupporterRepository $supporterRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'confirmedCount' => $supporterRepository->countConfirmed(),
        ]);
    }
}
