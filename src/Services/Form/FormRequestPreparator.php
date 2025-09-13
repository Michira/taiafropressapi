<?php
namespace App\Services\Form;

use App\Request\JsonRequestProcessor;
use Symfony\Component\HttpFoundation\Request;

class FormRequestPreparator
{
    private JsonRequestProcessor $jsonProcessor;
    private FormRequestValidator $validator;

    public function __construct(
        JsonRequestProcessor $jsonProcessor,
        FormRequestValidator $validator
    ) {
        $this->jsonProcessor = $jsonProcessor;
        $this->validator = $validator;
    }

    public function prepareFormRequest(Request $request): Request
    {
        $data = $this->jsonProcessor->decodeJsonRequest($request);
        [$formKey, $formData] = $this->validator->validateFormData($data);

        $formData['formId'] = (string) $formData['formId'];
        $formData['formName'] = (string) $formData['formName'];
        $formData['type'] = (string) $formData['type'];
        $formData['typeId'] = (string) $formData['typeId'];
        $formData['checksum'] = (string) $formData['checksum'];
        $formData['locale'] = (string) ($formData['locale'] ?? $request->getLocale());

        return $this->mergeFormDataIntoRequest($request, $formKey, $formData);
    }

    public function mergeFormDataIntoRequest(Request $request, string $formKey, array $formData): Request
    {
        $request->request->set($formKey, $formData);
        // $request->request->replace($formData);
        return $request;
    }
}