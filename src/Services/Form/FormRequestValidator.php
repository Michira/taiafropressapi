<?php
namespace App\Services\Form;

use App\Request\JsonRequestProcessor;
use Symfony\Component\HttpFoundation\Request;

class FormRequestValidator
{
    private const REGISTERED_FORMS = [
        2 => 'contact_form',
    ];

    private const SPECIAL_FORM_ROUTES = [
        '/contact.json',
    ];

    private JsonRequestProcessor $jsonProcessor;
    private FormBuilderMetadataValidator $metadataValidator;

    public function __construct(
        JsonRequestProcessor $jsonProcessor,
        FormBuilderMetadataValidator $metadataValidator
    ) {
        $this->jsonProcessor = $jsonProcessor;
        $this->metadataValidator = $metadataValidator;
    }

    public function isSpecialFormRequest(Request $request): bool
    {
        return $this->isFormApiRoute($request) || $this->containsRegisteredFormId($request);
    }

    public function isFormApiRoute(Request $request): bool
    {
        if (!$request->isMethod('POST')) {
            return false;
        }
        $currentPath = $request->getPathInfo();
        foreach (self::SPECIAL_FORM_ROUTES as $route) {
            if (str_starts_with($currentPath, $route)) {
                return true;
            }
        }
        return false;
    }

    public function containsRegisteredFormId(Request $request): bool
    {
        try {
            $content = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
            foreach ($content as $key => $data) {
                if (str_starts_with($key, 'dynamic_form_') &&
                    isset($data['formId']) &&
                    $this->isRegisteredForm((int)$data['formId'])) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isRegisteredForm(int $formId): bool
    {
        return isset(self::REGISTERED_FORMS[$formId]);
    }

    public function getFormType(int $formId): ?string
    {
        return self::REGISTERED_FORMS[$formId] ?? null;
    }

    public function validateFormData(array $data): array
    {
        $formKey = array_key_first($data);
        if (!$formKey || !isset($data[$formKey]) || !str_starts_with($formKey, 'dynamic_form_')) {
            throw new \InvalidArgumentException('Invalid form data structure.');
        }
        $formData = $data[$formKey];
        $this->metadataValidator->validateRequiredBuilderFields($formData);
        return [$formKey, $formData];
    }

    public function getRequiredFields($form): array
    {
        $requiredFields = [];
        foreach ($form as $child) {
            if ($child->getConfig()->getOption('required')) {
                $requiredFields[] = $child->getName();
            }
        }
        return $requiredFields;
    }
}