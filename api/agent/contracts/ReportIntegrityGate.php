<?php
declare(strict_types=1);

final class ReportIntegrityGate
{
    /**
     * @return array{ok:bool,errors:array<int,string>,warnings:array<int,string>,cited_pmids:array<int,string>,linked_urls:array<int,string>,unsupported_markers:array<int,string>}
     */
    public static function check(string $report, array $evidencePackage, array $evidenceWalk = [], array $reportPlan = []): array
    {
        $errors = [];
        $warnings = [];
        $unsupportedMarkers = [];

        $citedPmids = self::extractPmids($report);
        $linkedUrls = self::extractUrls($report);
        $citationIds = self::extractIds((array)($evidencePackage['citation_map'] ?? []));
        $routeIds = self::extractIds((array)($evidencePackage['route_map'] ?? []));
        $allowedPmids = self::allowedPmids($evidencePackage);
        $allowedUrls = self::allowedUrls($evidencePackage);

        foreach ($citedPmids as $pmid) {
            if (!isset($allowedPmids[$pmid])) {
                $errors[] = "PMID {$pmid} is not present in evidence_package citation_map.";
            }
        }

        foreach ($linkedUrls as $url) {
            if (!isset($allowedUrls[$url])) {
                $errors[] = "URL {$url} is not present in evidence_package citations or routes.";
            }
        }

        if (self::claimsAreEmpty($evidencePackage) && self::containsStrongConclusion($report)) {
            $errors[] = 'Report uses a strong conclusion word while evidence_package claims are empty.';
        }

        foreach (self::extractMarkers($report) as $marker) {
            $type = $marker['type'];
            $id = $marker['id'];
            $exists = $type === 'citation_id' ? isset($citationIds[$id]) : isset($routeIds[$id]);
            if (!$exists) {
                $label = "{$type}: {$id}";
                $unsupportedMarkers[] = $label;
                $errors[] = "{$label} is not present in evidence_package.";
            }
        }

        foreach (self::requiredSectionTitles($reportPlan) as $title) {
            if (!self::containsNormalizedText($report, $title)) {
                $warnings[] = "Report may be missing planned section: {$title}.";
            }
        }

        foreach (self::walkClaimTexts($evidenceWalk) as $claimText) {
            if (!self::containsNormalizedText($report, $claimText)) {
                $warnings[] = "Report does not explicitly mention evidence walk claim: {$claimText}.";
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'cited_pmids' => $citedPmids,
            'linked_urls' => $linkedUrls,
            'unsupported_markers' => self::uniqueValues($unsupportedMarkers),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function extractPmids(string $report): array
    {
        if (!preg_match_all('/\bPMID\s*(\d+)\b/i', $report, $matches)) {
            return [];
        }
        return self::uniqueValues(array_map('strval', $matches[1]));
    }

    /**
     * @return array<int,string>
     */
    private static function extractUrls(string $report): array
    {
        $urls = [];
        if (preg_match_all('/\[[^\]]+\]\(([^)\s]+)\)/', $report, $markdownMatches)) {
            foreach ($markdownMatches[1] as $url) {
                $urls[] = self::cleanUrl((string)$url);
            }
        }
        if (preg_match_all('/https?:\/\/[^\s<>"\']+/i', $report, $httpMatches)) {
            foreach ($httpMatches[0] as $url) {
                $urls[] = self::cleanUrl((string)$url);
            }
        }
        return self::uniqueValues(array_values(array_filter($urls, static fn (string $url): bool => $url !== '')));
    }

    /**
     * @return array<int,array{type:string,id:string}>
     */
    private static function extractMarkers(string $report): array
    {
        if (!preg_match_all('/\b(citation_id|route_id)\s*[:=]\s*([A-Za-z0-9_-]+)/i', $report, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $markers = [];
        foreach ($matches as $match) {
            $markers[] = [
                'type' => strtolower($match[1]),
                'id' => $match[2],
            ];
        }
        return $markers;
    }

    /**
     * @return array<string,true>
     */
    private static function allowedPmids(array $evidencePackage): array
    {
        $pmids = [];
        foreach ((array)($evidencePackage['citation_map'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $citation = $item['citation'] ?? null;
            if (!is_array($citation) || !isset($citation['pmid'])) {
                continue;
            }
            $pmid = trim((string)$citation['pmid']);
            if ($pmid !== '') {
                $pmids[$pmid] = true;
            }
        }
        return $pmids;
    }

    /**
     * @return array<string,true>
     */
    private static function allowedUrls(array $evidencePackage): array
    {
        $urls = [];
        foreach ((array)($evidencePackage['citation_map'] ?? []) as $item) {
            if (!is_array($item) || !is_array($item['citation'] ?? null)) {
                continue;
            }
            self::addUrl($urls, $item['citation']['url'] ?? null);
        }

        foreach ((array)($evidencePackage['route_map'] ?? []) as $item) {
            if (!is_array($item) || !is_array($item['route'] ?? null)) {
                continue;
            }
            self::addRouteUrl($urls, $item['route']['url'] ?? null);
            self::addRouteUrl($urls, $item['route']['href'] ?? null);
        }

        return $urls;
    }

    /**
     * @return array<string,true>
     */
    private static function extractIds(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string)($item['id'] ?? ''));
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        return $ids;
    }

    private static function claimsAreEmpty(array $evidencePackage): bool
    {
        return count((array)($evidencePackage['claims'] ?? [])) === 0;
    }

    private static function containsStrongConclusion(string $report): bool
    {
        return preg_match('/\b(demonstrates|proves|establishes)\b|明确证明|证实/iu', $report) === 1;
    }

    /**
     * @return array<int,string>
     */
    private static function requiredSectionTitles(array $reportPlan): array
    {
        $titles = [];
        foreach ((array)($reportPlan['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $title = trim((string)($section['title'] ?? ''));
            if ($title !== '') {
                $titles[] = $title;
            }
        }
        return self::uniqueValues($titles);
    }

    /**
     * @return array<int,string>
     */
    private static function walkClaimTexts(array $evidenceWalk): array
    {
        $claims = [];
        foreach ((array)($evidenceWalk['claim_nodes'] ?? []) as $claimNode) {
            if (!is_array($claimNode)) {
                continue;
            }
            $text = trim((string)($claimNode['text'] ?? ''));
            if ($text !== '') {
                $claims[] = $text;
            }
        }
        return self::uniqueValues($claims);
    }

    private static function containsNormalizedText(string $haystack, string $needle): bool
    {
        $normalizedHaystack = self::normalizeForContainment($haystack);
        $normalizedNeedle = self::normalizeForContainment($needle);
        if ($normalizedNeedle === '') {
            return true;
        }
        return str_contains($normalizedHaystack, $normalizedNeedle);
    }

    private static function normalizeForContainment(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    /**
     * @param array<string,true> $urls
     */
    private static function addUrl(array &$urls, mixed $value): void
    {
        if (!is_scalar($value)) {
            return;
        }
        $url = self::cleanUrl((string)$value);
        if ($url !== '') {
            $urls[$url] = true;
        }
    }

    /**
     * @param array<string,true> $urls
     */
    private static function addRouteUrl(array &$urls, mixed $value): void
    {
        if (!is_scalar($value)) {
            return;
        }
        $url = self::cleanUrl((string)$value);
        if ($url === '') {
            return;
        }
        $urls[$url] = true;

        $fragmentPos = strpos($url, '#');
        if ($fragmentPos !== false) {
            $withoutFragment = substr($url, 0, $fragmentPos);
            if ($withoutFragment !== '') {
                $urls[$withoutFragment] = true;
            }
        }
    }

    private static function cleanUrl(string $url): string
    {
        $url = trim($url);
        $markdownFragmentPos = strpos($url, '](');
        if ($markdownFragmentPos !== false) {
            $url = substr($url, 0, $markdownFragmentPos);
        }
        return rtrim($url, ".,;:)`*");
    }

    /**
     * @param array<int,string> $values
     * @return array<int,string>
     */
    private static function uniqueValues(array $values): array
    {
        $seen = [];
        $unique = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $unique[] = $value;
        }
        return $unique;
    }
}
