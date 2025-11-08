<?php

namespace App\Service;

/**
 * Menu Item Image Path Resolver Service
 *
 * This service is responsible for resolving menu item image paths from various formats
 * to a consistent format suitable for frontend display. It handles different image
 * storage scenarios and ensures all paths are properly formatted.
 *
 * Purpose:
 * - Centralizes image path resolution logic (Single Responsibility Principle)
 * - Makes image path handling reusable across the application
 * - Simplifies cart service by removing image path logic
 *
 * Supported image path formats:
 * - Absolute URLs (http/https) - returned as-is
 * - Absolute paths starting with /uploads/, /assets/ or /static/ - returned as-is
 * - Relative paths starting with assets/ or static/ - prepended with /
 * - Other relative paths - prepended with /uploads/menu/
 * - Null/empty values - returns default placeholder image
 *
 * Usage:
 * This service is typically injected into services that need to resolve menu item
 * image paths, such as CartService or MenuController.
 */
class MenuItemImageResolver
{
    /**
     * Default placeholder image path
     *
     * Used when a menu item has no image or the image path is null/empty.
     * This ensures the frontend always has a valid image path to display.
     */
    private const DEFAULT_IMAGE_PATH = '/static/img/default-dish.png';

    /**
     * Resolve image path to absolute URL or relative path
     *
     * Converts various image path formats stored in the database to a consistent
     * format that can be used by the frontend. This method handles:
     *
     * 1. Null/empty values: Returns default placeholder image
     * 2. Absolute URLs (http/https): Returns as-is (external images)
     * 3. Absolute paths (/uploads/, /assets/, /static/): Returns as-is (already correct)
     * 4. Relative paths starting with 'assets/' or 'static/': Prepends '/' to make absolute
     * 5. Other relative paths: Prepends '/uploads/menu/' (assumes menu item images)
     *
     * Examples:
     * - null → '/static/img/default-dish.png'
     * - 'http://example.com/image.jpg' → 'http://example.com/image.jpg'
     * - '/uploads/menu/dish.jpg' → '/uploads/menu/dish.jpg'
     * - 'static/img/dish.jpg' → '/static/img/dish.jpg'
     * - 'dish.jpg' → '/uploads/menu/dish.jpg'
     *
     * @param string|null $image Image path from database (can be null, relative, or absolute)
     * @return string Resolved image path suitable for frontend use (always returns a valid path)
     */
    public function resolve(?string $image): string
    {
        // Handle null or empty image paths
        // Return default placeholder to ensure frontend always has an image
        if (!$image) {
            return self::DEFAULT_IMAGE_PATH;
        }

        // Handle absolute URLs (external images)
        // If image starts with 'http' (http:// or https://), return as-is
        // These are external URLs and should not be modified
        if (str_starts_with($image, 'http')) {
            return $image;
        }

        // Handle absolute paths (already correctly formatted)
        // Paths starting with /uploads/, /assets/ or /static/ are already absolute and correct
        // Return them as-is without modification
        if (
            str_starts_with($image, '/uploads/')
            || str_starts_with($image, '/assets/')
            || str_starts_with($image, '/static/')
        ) {
            return $image;
        }

        // Handle relative paths starting with 'assets/' or 'static/'
        // These need to be converted to absolute paths by prepending '/'
        // Example: 'static/img/dish.jpg' → '/static/img/dish.jpg'
        if (str_starts_with($image, 'assets/') || str_starts_with($image, 'static/')) {
            return '/' . ltrim($image, '/');
        }

        // Handle all other relative paths
        // Assume these are menu item images stored in /uploads/menu/
        // Remove any leading slashes and prepend the base path
        // Example: 'dish.jpg' → '/uploads/menu/dish.jpg'
        // Example: '/dish.jpg' → '/uploads/menu/dish.jpg' (ltrim removes leading slash)
        return '/uploads/menu/' . ltrim($image, '/');
    }
}

