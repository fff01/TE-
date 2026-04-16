<?php
declare(strict_types=1);

final class TekgAgentNeo4jClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function run(string $cypher, array $params = []): array
    {
        $url = (string)($this->config['neo4j_url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('Neo4j URL is not configured for the academic agent.');
        }

        $payload = json_encode(['statements' => [[
            'statement' => $cypher,
            'parameters' => (object)$params,
            'resultDataContents' => ['row'],
        ]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERPWD => (string)($this->config['neo4j_user'] ?? '') . ':' . (string)($this->config['neo4j_password'] ?? ''),
        ]);
        if (($this->config['ssl_verify'] ?? false) !== true) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch) ?: 'Unknown Neo4j cURL failure';
            curl_close($ch);
            throw new RuntimeException('Neo4j query failed: ' . $error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Neo4j returned invalid JSON.');
        }
        if ($status >= 400) {
            throw new RuntimeException('Neo4j returned HTTP ' . $status);
        }
        if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
            $message = (string)($decoded['errors'][0]['message'] ?? 'Unknown Neo4j error');
            throw new RuntimeException('Neo4j error: ' . $message);
        }
        $results = $decoded['results'][0] ?? [];
        $columns = is_array($results['columns'] ?? null) ? $results['columns'] : [];
        $rows = [];
        foreach (($results['data'] ?? []) as $entry) {
            $rowValues = is_array($entry['row'] ?? null) ? $entry['row'] : [];
            $assoc = [];
            foreach ($columns as $index => $column) {
                $assoc[(string)$column] = $rowValues[$index] ?? null;
            }
            $rows[] = $assoc;
        }
        return $rows;
    }
}
