<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    public function contact(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;
        $email = $data['email'] ?? null;
        $subject = $data['subject'] ?? null;
        $message = $data['message'] ?? null;
        $name = $firstName . ' ' . $lastName;

        if (!$name || !$email || !$message) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $emailMessage = (new Email())
            ->from($email)
            ->to($_ENV('MAILER_TO_EMAIL'))
            ->subject('New Contact Form Submission')
            ->text("Name: $name\nEmail: $email\nMessage: $message");

        $mailer->send($emailMessage);

        return new JsonResponse(['success' => true]);
    }
}
