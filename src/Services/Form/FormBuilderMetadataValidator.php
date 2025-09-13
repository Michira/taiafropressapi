<?php
namespace App\Services\Form;

use InvalidArgumentException;

class FormBuilderMetadataValidator
{
    private array $requiredFields;

    public function __construct(array $requiredFields = [
        'formId',
        'formName',
        'type',
        'typeId',
        'checksum',
    ]) {
        $this->requiredFields = $requiredFields;
    }

    public function validateRequiredBuilderFields(array $data): void
    {
        foreach ($this->requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException("Missing or empty required form builder field: $field");
            }
        }
    }
}