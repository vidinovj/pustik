<?php
// app/Helpers/MetadataDisplayHelper.php

namespace App\Helpers;

class MetadataDisplayHelper
{
    /**
     * Safely display metadata values, handling complex nested structures
     */
    public static function displayValue($value, int $maxDepth = 2, int $currentDepth = 0): string
    {
        if ($currentDepth >= $maxDepth) {
            return '[Complex data structure]';
        }

        if (is_null($value)) {
            return 'N/A';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return self::displayArray($value, $maxDepth, $currentDepth);
        }

        // Fallback for objects or other types
        return '[' . gettype($value) . ']';
    }

    /**
     * Handle array display with special cases for known structures
     */
    private static function displayArray(array $value, int $maxDepth, int $currentDepth): string
    {
        if (empty($value)) {
            return 'None';
        }

        // Special handling for TIK keywords structure
        if (self::isTikKeywordsArray($value)) {
            return self::displayTikKeywords($value);
        }

        // Special handling for found_keywords from tik_summary
        if (self::isFoundKeywordsArray($value)) {
            return self::displayFoundKeywords($value);
        }

        // Check if all values are simple (string/number/bool)
        $allSimple = array_reduce($value, function($carry, $item) {
            return $carry && (is_string($item) || is_numeric($item) || is_bool($item));
        }, true);

        if ($allSimple) {
            $displayValues = array_map(function($item) {
                return is_bool($item) ? ($item ? 'Yes' : 'No') : (string) $item;
            }, $value);
            return implode(', ', $displayValues);
        }

        // For complex nested structures, show count and first few items
        if ($currentDepth < $maxDepth - 1) {
            $displayItems = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count >= 3) break; // Show max 3 items
                
                $displayKey = is_numeric($key) ? '' : $key . ': ';
                $displayValue = self::displayValue($item, $maxDepth, $currentDepth + 1);
                $displayItems[] = $displayKey . $displayValue;
                $count++;
            }
            
            $result = implode(', ', $displayItems);
            if (count($value) > 3) {
                $result .= ' and ' . (count($value) - 3) . ' more...';
            }
            return $result;
        }

        return '[' . count($value) . ' items]';
    }

    /**
     * Check if array contains TIK keywords with term/score structure
     */
    private static function isTikKeywordsArray(array $value): bool
    {
        if (empty($value)) return false;
        
        $firstItem = reset($value);
        return is_array($firstItem) && isset($firstItem['term']) && isset($firstItem['score']);
    }

    /**
     * Display TIK keywords array
     */
    private static function displayTikKeywords(array $keywords): string
    {
        $terms = array_map(function($keyword) {
            if (is_array($keyword) && isset($keyword['term'])) {
                $score = isset($keyword['score']) ? " ({$keyword['score']})" : '';
                return $keyword['term'] . $score;
            }
            return is_string($keyword) ? $keyword : '[Invalid keyword]';
        }, $keywords);

        return implode(', ', array_slice($terms, 0, 5)) . 
               (count($terms) > 5 ? ' and ' . (count($terms) - 5) . ' more...' : '');
    }

    /**
     * Check if array is found_keywords from tik_summary
     */
    private static function isFoundKeywordsArray(array $value): bool
    {
        if (empty($value)) return false;
        
        $firstItem = reset($value);
        return is_array($firstItem) && 
               isset($firstItem['term']) && 
               isset($firstItem['score']) && 
               isset($firstItem['category']);
    }

    /**
     * Display found_keywords from tik_summary
     */
    private static function displayFoundKeywords(array $keywords): string
    {
        $terms = array_map(function($keyword) {
            return $keyword['term'] ?? '[Invalid term]';
        }, $keywords);

        return implode(', ', array_slice($terms, 0, 5)) . 
               (count($terms) > 5 ? ' and ' . (count($terms) - 5) . ' more...' : '');
    }

    /**
     * Get displayable metadata excluding complex nested structures
     */
    public static function getDisplayableMetadata(array $metadata): array
    {
        $excluded = ['tik_summary', 'found_keywords', 'tik_classification'];
        $displayable = [];

        foreach ($metadata as $key => $value) {
            if (in_array($key, $excluded)) {
                continue;
            }

            // Skip very complex nested structures
            if (is_array($value) && self::isComplexNestedArray($value)) {
                continue;
            }

            $displayable[$key] = $value;
        }

        return $displayable;
    }

    /**
     * Check if array is too complex for simple display
     */
    private static function isComplexNestedArray(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                foreach ($item as $subItem) {
                    if (is_array($subItem) || is_object($subItem)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}