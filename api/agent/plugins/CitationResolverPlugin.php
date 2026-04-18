<?php
declare(strict_types=1);

final class TekgAgentCitationResolverPlugin implements TekgAgentPluginInterface
{
    public function __construct(private readonly TekgAgentCitationResolver $resolver)
    {
    }

    public function getName(): string
    {
        return 'Citation Resolver';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $pluginResults = is_array($context['plugin_results'] ?? null) ? $context['plugin_results'] : [];
        $all = [];
        foreach ($pluginResults as $pluginName => $result) {
            if ($pluginName === $this->getName()) {
                continue;
            }
            $all = array_merge($all, is_array($result['citations'] ?? null) ? $result['citations'] : []);
        }

        $citations = $this->resolver->normalizeMany($all);
        $counts = $this->resolver->summarize($citations);
        $summary = $counts['total'] > 0
            ? 'I unified ' . $counts['total'] . ' citations across the toolchain, with ' . $counts['pmid'] . ' directly traceable PMID-backed records.'
            : 'No stable citations were available to normalize in this round.';

        return [
            'plugin_name' => $this->getName(),
            'status' => $counts['total'] > 0 ? 'ok' : 'empty',
            'query_summary' => 'Normalized, deduplicated, and formatted citations from the previous tools.',
            'results' => [
                'citations' => $citations,
            ],
            'display_label' => 'Formatted ' . $counts['total'] . ' citations',
            'display_summary' => $summary,
            'display_details' => [
                'summary' => $summary,
                'preview_items' => array_map(
                    static fn(array $citation): array => [
                        'title' => (string)($citation['title'] ?: ('PMID ' . ($citation['pmid'] ?? ''))),
                        'meta' => trim(implode(' | ', array_filter([
                            (string)($citation['source'] ?? ''),
                            (string)($citation['journal'] ?? ''),
                            (string)($citation['year'] ?? ''),
                            ($citation['pmid'] ?? '') !== '' ? 'PMID ' . (string)$citation['pmid'] : '',
                        ]))),
                        'url' => (string)($citation['url'] ?? ''),
                    ],
                    array_slice($citations, 0, 8)
                ),
                'evidence_items' => [$summary],
                'citations' => $citations,
                'raw_preview' => ['citations' => $citations],
                'result_message' => $counts['total'] > 0
                    ? 'These normalized citation records will be reused by the final answer and the front-end reference UI.'
                    : 'There was nothing to normalize because the upstream tools did not contribute usable citation records.',
            ],
            'result_counts' => $counts,
            'evidence_items' => [$summary],
            'citations' => $citations,
            'errors' => [],
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }
}
