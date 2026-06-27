<?php

namespace App\Services;

class FuzzyMatcherService
{
    /**
     * Calculate similarity score between two strings (0-100).
     */
    public function getScore(string $str1, string $str2): float
    {
        $str1 = $this->normalize($str1);
        $str2 = $this->normalize($str2);

        if ($str1 === $str2) {
            return 100.0;
        }

        $levScore = $this->levenshteinScore($str1, $str2);
        $jaroScore = $this->jaroWinklerScore($str1, $str2);

        // Average of both algorithms for better accuracy
        return ($levScore + $jaroScore) / 2;
    }

    public function normalize(string $str): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $str)));
    }

    private function levenshteinScore(string $str1, string $str2): float
    {
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 100.0;
        }

        $lev = levenshtein($str1, $str2);

        return (1 - ($lev / $maxLen)) * 100;
    }

    private function jaroWinklerScore(string $str1, string $str2): float
    {
        $jaro = $this->jaroDistance($str1, $str2);
        $prefixLen = 0;
        $maxPrefix = 4;

        for ($i = 0; $i < min(strlen($str1), strlen($str2), $maxPrefix); $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefixLen++;
            } else {
                break;
            }
        }

        return ($jaro + ($prefixLen * 0.1 * (1 - $jaro))) * 100;
    }

    private function jaroDistance(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 == 0 && $len2 == 0) {
            return 1.0;
        }
        if ($len1 == 0 || $len2 == 0) {
            return 0.0;
        }

        $matchDistance = (int) (max($len1, $len2) / 2) - 1;

        $matches1 = array_fill(0, $len1, false);
        $matches2 = array_fill(0, $len2, false);

        $matches = 0;
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($matches2[$j]) {
                    continue;
                }
                if ($str1[$i] !== $str2[$j]) {
                    continue;
                }

                $matches1[$i] = true;
                $matches2[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches == 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (! $matches1[$i]) {
                continue;
            }
            while (! $matches2[$k]) {
                $k++;
            }
            if ($str1[$i] !== $str2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        $transpositions /= 2;

        return (($matches / $len1) + ($matches / $len2) + (($matches - $transpositions) / $matches)) / 3;
    }
}
