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

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $user = (string)($this->config['neo4j_user'] ?? '');
        $password = (string)($this->config['neo4j_password'] ?? '');
        if ($user !== '' || $password !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($user . ':' . $password);
        }

        $response = tekg_agent_http_request($url, 'POST', $headers, $payload, 45, (bool)($this->config['ssl_verify'] ?? false));
        $decoded = json_decode((string)$response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Neo4j returned invalid JSON.');
        }
        if ((int)$response['status'] >= 400) {
            throw new RuntimeException('Neo4j returned HTTP ' . (int)$response['status']);
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
