<?php

namespace App\Service;

class InputSanitizer
{
    /**
     * Sanitizes input data by removing potentially dangerous content
     */
    public static function sanitize(string $input): string
    {
        // Remove HTML tags
        $sanitized = strip_tags($input);
        // Decode HTML entities
        $sanitized = html_entity_decode($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove potentially dangerous characters
        $sanitized = preg_replace('/[<>"\']/', '', $sanitized);
        // Remove JavaScript events and protocols
        $sanitized = preg_replace('/javascript:/i', '', $sanitized);
        $sanitized = preg_replace('/on\w+\s*=/i', '', $sanitized);
        return trim($sanitized);
    }

    /**
     * Checks if string contains potentially dangerous HTML/JavaScript code
     */
    public static function containsXssAttempt(string $input): bool
    {
        $xssPatterns = [
            '/<[^>]*>/i',                    // HTML tags
            '/javascript:/i',                // JavaScript protocol
            '/on\w+\s*=/i',                  // JavaScript events
            '/vbscript:/i',                  // VBScript
            '/data:text\/html/i',            // Data URI with HTML
            '/expression\s*\(/i',            // CSS expression
            '/<iframe/i',                    // Iframe tags
            '/<script/i',                    // Script tags
            '/<(object|embed)/i',            // Object and embed tags
            '/<form/i',                      // Form tags
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }
}
