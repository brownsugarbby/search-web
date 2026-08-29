<?php

namespace App\Services;

/**
 * Turns free text into the canonical form used for keyword matching.
 *
 * Both sides of the comparison run through here: the query a visitor types,
 * and the keyword an admin saves (stored as keywords.keyword_normalized).
 * Keeping that in one class is the point - if the two ever normalised
 * differently, exact-match lookups would silently miss and every search would
 * fall through to the fuzzier tiers.
 */
class QueryNormalizer
{
    public function normalize(?string $value): string
    {
        $value = (string) $value;

        // Unicode-aware lowercase, so "BERITA" and "Berita" collapse together.
        $value = mb_strtolower($value, 'UTF-8');

        // Strip anything that is not a letter, digit, space or hyphen. Keeps
        // "e-commerce" intact while discarding punctuation people type by
        // accident ("tokopedia!!!").
        $value = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $value) ?? '';

        // Collapse runs of whitespace (and stray hyphens) into single spaces.
        $value = preg_replace('/[\s]+/u', ' ', $value) ?? '';
        $value = preg_replace('/-{2,}/u', '-', $value) ?? '';

        $value = trim($value, " \t\n\r\0\x0B-");

        // The keywords.keyword_normalized column is a plain string(255); a
        // longer query cannot match anything stored, so cap it here rather
        // than letting a huge string reach the database.
        return mb_substr($value, 0, 255, 'UTF-8');
    }

    /**
     * Individual terms, for building the FULLTEXT expression.
     *
     * @return array<int, string>
     */
    public function terms(?string $value): array
    {
        $normalized = $this->normalize($value);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }
}
