<?php

namespace App\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * Handles the validation and processing of incoming JSON requests.
 *
 * This class provides utility methods to check if a request has a
 * JSON content type and to populate its parameters with the decoded JSON body.
 * It's designed to be used in event listeners or middleware to
 * streamline the handling of API requests.
 */
class JsonRequestProcessor
{
    /**
     * Checks if the incoming request has an "application/json" content type.
     *
     * @param Request $request The request to check.
     * @return bool True if the request is a JSON request, false otherwise.
     */
    public function isJsonRequest(Request $request): bool
    {
        return str_starts_with($request->headers->get('Content-Type', ''), 'application/json');
    }

    /**
     * Populates the request with data from the JSON body.
     *
     * This method decodes the JSON content from the request body and
     * adds it to the request's parameters, making it accessible later
     * in the application lifecycle.
     *
     * @param Request $request The request to process.
     * @return Request The processed request with JSON data.
     */
    public function decodeJsonRequest(Request $request): array
    {
        // Get the content of the request body
        $content = $request->getContent();

        // Decode the JSON content into an associative array
        $data = json_decode($content, true);

        // Check if the decoding was successful and the result is an array
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            // Merge the decoded JSON data into the request's parameters
            return $data;
        }

        return [];
    }
}
