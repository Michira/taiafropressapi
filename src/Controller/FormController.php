<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Sulu\Bundle\FormBundle\Dynamic\Checksum;

class FormController extends AbstractController
{
    protected $checksum;

    // public function __construct(Checksum $checksum)
    // {
    //     $this->checksum = $checksum;
    // }

    #[Route('/api/form-token', name: 'api_form_token', methods: ['POST'])]
    public function getChecksum(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $type = $payload->get('type');
        $typeId = $payload->get('typeId');
        $formId = $payload->get('formId');
        $formName = $payload->get('formName');
        $secret  = $_ENV['APP_SECRET'] ?? "";

        $checksum = (new Checksum($secret))->get(
            $type,
            $typeId,
            $formId,
            $formName
        );

        return new JsonResponse([
            'checksum' => $checksum,
        ]);
    }
}
    