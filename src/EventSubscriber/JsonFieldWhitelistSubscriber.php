<?php

namespace App\EventSubscriber;

use App\Service\JsonFieldWhitelistService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * JSON Field Whitelist Event Subscriber
 *
 * WHAT IT DOES:
 * This subscriber validates and filters JSON request data BEFORE it reaches controllers.
 * It prevents security attacks by:
 * 1. Filtering out unauthorized fields (mass assignment protection)
 * 2. Limiting JSON depth to prevent stack overflow attacks
 * 3. Limiting field count to prevent DoS attacks
 * 4. Limiting field name length to prevent DoS attacks
 *
 * WHEN IT TRIGGERS:
 * - Automatically on EVERY API request (KernelEvents::REQUEST event)
 * - Runs BEFORE the controller is called
 * - Only processes POST/PUT/PATCH requests with JSON content
 * - Only processes requests to /api/* endpoints
 *
 * HOW IT WORKS:
 * 1. Checks if request is JSON and to API endpoint
 * 2. Validates JSON structure (must be object, not array)
 * 3. Checks depth, field count, and field name length
 * 4. Filters out unauthorized fields using JsonFieldWhitelistService
 * 5. Stores filtered data in request attributes for controllers
 *
 * WHY IT'S HIDDEN:
 * - Runs automatically, so controllers don't need to worry about it
 * - But it's essential for security - prevents malicious data from reaching controllers
 *
 * HOW TO USE IN CONTROLLERS:
 * - Access filtered data: $request->attributes->get('filtered_json_data')
 * - Original data (if needed): $request->attributes->get('original_json_data')
 *
 * HOW TO DEBUG:
 * - If request is rejected, you'll get a 400 error with details
 * - Check which fields were rejected in the error response
 */
class JsonFieldWhitelistSubscriber implements EventSubscriberInterface
{
    // Maximum JSON nesting depth (prevents stack overflow attacks)
    // PHP default is 512, but we limit to 64 for security
    // Example: {"a": {"b": {"c": ...}}} - max 64 levels deep
    private const MAX_JSON_DEPTH = 64;
    
    // Maximum number of fields in JSON object (prevents DoS via excessive fields)
    // Example: {"field1": 1, "field2": 2, ...} - max 100 fields
    private const MAX_FIELD_COUNT = 100;
    
    // Maximum field name length (prevents DoS via very long field names)
    // Example: {"very_long_field_name_that_could_be_used_for_attack": 1} - max 255 chars
    private const MAX_FIELD_NAME_LENGTH = 255;

    public function __construct(
        private JsonFieldWhitelistService $whitelistService
    ) {
    }

    /**
     * Tell Symfony which events this subscriber listens to
     *
     * Priority 10 means this runs AFTER ApiRateLimitSubscriber (priority 9),
     * so payload size is checked first, then field validation happens.
     *
     * @return array Event name => [method to call, priority]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // When Symfony receives a request, call onKernelRequest()
            // Priority 10: runs after payload size check (priority 9)
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    /**
     * Called automatically when Symfony receives a request
     *
     * This method validates and filters JSON data before it reaches controllers.
     * If validation fails, it returns a 400 error response immediately.
     *
     * @param RequestEvent $event Contains the request that will be processed
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only process API endpoints
        if (strpos($path, '/api') !== 0) {
            return;
        }

        // Only check POST, PUT, PATCH requests with JSON content
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }

        $contentType = $request->headers->get('Content-Type', '');
        if (!str_contains(strtolower($contentType), 'application/json')) {
            return;
        }

        // Get JSON data
        $content = $request->getContent(false);
        if ($content === false || empty($content)) {
            return;
        }

        // Decode JSON with depth limit to prevent stack overflow attacks
        $data = json_decode($content, true, self::MAX_JSON_DEPTH);
        
        // Check for JSON decode errors
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            $errorMessage = match($jsonError) {
                JSON_ERROR_DEPTH => sprintf('JSON maximum nesting depth exceeded (max: %d levels)', self::MAX_JSON_DEPTH),
                JSON_ERROR_SYNTAX => 'Invalid JSON syntax',
                JSON_ERROR_CTRL_CHAR => 'Invalid JSON: control character error',
                JSON_ERROR_STATE_MISMATCH => 'Invalid JSON: state mismatch',
                JSON_ERROR_UTF8 => 'Invalid JSON: invalid UTF-8 characters',
                default => 'Invalid JSON format'
            };
            
            $event->setResponse(new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON payload',
                'message' => $errorMessage,
                'code' => 'JSON_PARSE_ERROR'
            ], 400));
            return;
        }

        if (!is_array($data)) {
            return; // Invalid JSON structure, will be handled by other validators
        }

        // Ensure JSON is an object (associative array), not a plain array
        if (array_is_list($data)) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON structure',
                'message' => 'JSON must be an object, not an array',
                'code' => 'JSON_STRUCTURE_ERROR'
            ], 400));
            return;
        }

        // Check field count limit (prevent DoS via excessive fields)
        $fieldCount = count($data);
        if ($fieldCount > self::MAX_FIELD_COUNT) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'error' => 'Too many fields',
                'message' => sprintf('JSON object contains too many fields (max: %d, received: %d)', self::MAX_FIELD_COUNT, $fieldCount),
                'code' => 'TOO_MANY_FIELDS'
            ], 400));
            return;
        }

        // Check field name length limit (prevent DoS via very long field names)
        foreach (array_keys($data) as $fieldName) {
            if (strlen($fieldName) > self::MAX_FIELD_NAME_LENGTH) {
                $event->setResponse(new JsonResponse([
                    'success' => false,
                    'error' => 'Field name too long',
                    'message' => sprintf('Field name exceeds maximum length (max: %d characters)', self::MAX_FIELD_NAME_LENGTH),
                    'code' => 'FIELD_NAME_TOO_LONG'
                ], 400));
                return;
            }
        }

        // Check for rejected fields
        $rejectedFields = $this->whitelistService->getRejectedFields($data, $path);
        
        if (!empty($rejectedFields)) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'error' => 'Unauthorized fields detected',
                'message' => sprintf(
                    'The following fields are not allowed for this endpoint: %s',
                    implode(', ', $rejectedFields)
                ),
                'rejected_fields' => array_values($rejectedFields),
                'code' => 'UNAUTHORIZED_FIELDS'
            ], 400));
            return;
        }

        // Store filtered data in request attributes for controllers to use
        // Controllers can access via: $request->attributes->get('filtered_json_data')
        $filteredData = $this->whitelistService->filterFields($data, $path);
        $request->attributes->set('filtered_json_data', $filteredData);
        $request->attributes->set('original_json_data', $data); // Keep original for logging if needed
    }
}

