<?php
/**
 * A stand-in for the Anthropic Messages API, for tests/support/support_test.php.
 * Run with: php -S 127.0.0.1:8765 tests/support/fake_claude.php
 * The scenario is picked from a keyword in the latest customer question.
 */
$req = json_decode(file_get_contents('php://input'), true) ?: [];
$msgs = $req['messages'] ?? [];
$last = end($msgs);
$firstUser = '';
foreach ($msgs as $m) {
    if ($m['role'] === 'user' && is_string($m['content'])) {
        $firstUser = $m['content']; // the latest plain question
    }
}
$toolResults = (is_array($last['content'] ?? null)) ? array_values(array_filter($last['content'], function ($b) { return ($b['type'] ?? '') === 'tool_result'; })) : [];

function reply($content, $stop) {
    header('Content-Type: application/json');
    echo json_encode(['id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'fake-claude', 'content' => $content,
        'stop_reason' => $stop, 'usage' => ['input_tokens' => 100, 'output_tokens' => 20]]);
    exit;
}
function toolUse($name, $input) { reply([['type' => 'tool_use', 'id' => 'tu_' . $name, 'name' => $name, 'input' => (object) $input]], 'tool_use'); }

if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== 'test-key') {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'bad key']]);
    exit;
}
// Copilot / plain completions: no tools in the request.
if (empty($req['tools'])) {
    $sys = is_string($req['system'] ?? null) ? $req['system'] : '';
    if (strpos($sys, 'Classify') !== false) {
        reply([['type' => 'text', 'text' => '{"language":"fr","language_confidence":0.93,"category":"billing","tags":["billing","made-up-tag"],"priority":"normal","sentiment":"neutral","reason":"Invoice question"}']], 'end_turn');
    }
    reply([['type' => 'text', 'text' => 'COPILOT: ' . substr($sys, 0, 40)]], 'end_turn');
}
if ($toolResults) {
    reply([['type' => 'text', 'text' => 'RESULT ' . $toolResults[0]['content']]], 'end_turn');
}
if (strpos($firstUser, 'HELP-ARTICLE') !== false) {
    toolUse('search_help', ['query' => 'storage fees']);
}
if (strpos($firstUser, 'PERSON-PLEASE') !== false) {
    toolUse('talk_to_person', ['reason' => 'Wants a person', 'summary' => 'Customer asked for a human', 'priority' => 'high', 'category' => 'shipment']);
}
if (preg_match('/OTHER-RECORD (\S+)/', $firstUser, $m)) {
    toolUse('get_record', ['tracking' => $m[1]]);
}
if (strpos($firstUser, 'RACE') !== false) {
    // A person takes the chat while "King" is thinking.
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . getenv('CS_TEST_DB'), getenv('CS_TEST_USER'), getenv('CS_TEST_PASS'));
    $pdo->exec("UPDATE cdb_cs_conversations SET owner_state = 'human_active', owner_seq = owner_seq + 1 ORDER BY id DESC LIMIT 1");
}
reply([['type' => 'text', 'text' => 'Hello from fake King']], 'end_turn');
