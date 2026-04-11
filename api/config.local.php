<?php
return [
    'dashscope_key' => getenv('DASHSCOPE_API_KEY_BIOLOGY') ?: getenv('DASHSCOPE_API_KEY') ?: '',
    'dashscope_model' => 'qwen3.5-flash',
    'dashscope_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
    'ssl_verify' => false,
    'llm_relay_url' => 'http://127.0.0.1:18087/chat',
    'neo4j_url' => 'http://127.0.0.1:7474/db/tekg2/tx/commit',
    'neo4j_user' => 'neo4j',
    'neo4j_password' => 'xjss9577',
    'key_node_threshold' => 3,
    'key_node_expand_limit' => 20,
];
