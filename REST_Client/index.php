<?php
// Start the session to store request history. This must be the very first thing.
session_start();

// Initialize session history array if it doesn't exist
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

// --- CONFIGURATION ---
define('HISTORY_LIMIT', 10); // Max number of history items to keep

// --- VARIABLE INITIALIZATION ---
$response_status = null;
$response_headers_raw = '';
$response_body_raw = '';
$error_message = '';
$request_time = 0;
$response_size = 0;

// --- UTILITY FUNCTIONS ---

/**
 * Pretty-prints a JSON string with syntax highlighting.
 */
function highlight_json_string(string $json_string): string {
    $json_obj = json_decode($json_string);
    if ($json_obj === null) {
        return '<pre class="text-white">' . htmlspecialchars($json_string) . '</pre>';
    }
    $pretty_json = json_encode($json_obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $pretty_json = htmlspecialchars($pretty_json);
    $pretty_json = preg_replace('/"([^"]+)"\s*:\s*/', '<span class="text-pink-400">"$1"</span>:', $pretty_json);
    $pretty_json = preg_replace('/: \s*"(.*?)"/', ': <span class="text-green-400">"$1"</span>', $pretty_json);
    $pretty_json = preg_replace('/: \s*(\d+\.?\d*)/', ': <span class="text-cyan-400">$1</span>', $pretty_json);
    $pretty_json = preg_replace('/: \s*(true|false)/', ': <span class="text-orange-400">$1</span>', $pretty_json);
    $pretty_json = preg_replace('/: \s*(null)/', ': <span class="text-gray-500">$1</span>', $pretty_json);
    return '<pre><code class="language-json">' . $pretty_json . '</code></pre>';
}

/**
 * Parses raw HTTP headers into an associative array.
 */
function parse_headers(string $header_string): array {
    $headers = [];
    foreach (explode("\r\n", $header_string) as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
    }
    return $headers;
}

// --- FORM PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = filter_var(trim($_POST['url']), FILTER_SANITIZE_URL);
    $method = strtoupper($_POST['method'] ?? 'GET');

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error_message = "Invalid URL provided.";
    } else {
        $ch = curl_init();
        $headers = [];
        if (!empty($_POST['header_keys'])) {
            foreach ($_POST['header_keys'] as $index => $key) {
                if (!empty(trim($key))) {
                    $headers[] = trim($key) . ": " . trim($_POST['header_values'][$index] ?? '');
                }
            }
        }

        if ($method === 'GET' && !empty($_POST['param_keys'])) {
            $params = [];
            foreach ($_POST['param_keys'] as $index => $key) {
                if (!empty(trim($key))) {
                    $params[trim($key)] = trim($_POST['param_values'][$index] ?? '');
                }
            }
            if (!empty($params)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
            }
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Simple-PHP-REST-Client/1.0');

        if ($method === 'POST') {
            $post_body = $_POST['post_body'] ?? '';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
            $is_json = json_decode($post_body) !== null;
            $has_content_type = false;
            foreach ($headers as $h) {
                if (stripos($h, 'Content-Type:') === 0) {
                    $has_content_type = true;
                    break;
                }
            }
            if ($is_json && !$has_content_type) {
                $headers[] = 'Content-Type: application/json';
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $start_time = microtime(true);
        $response = curl_exec($ch);
        $end_time = microtime(true);
        $request_time = round(($end_time - $start_time) * 1000);

        if (curl_errno($ch)) {
            $error_message = 'cURL Error: ' . curl_error($ch);
        } else {
            $response_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $response_headers_raw = substr($response, 0, $header_size);
            $response_body_raw = substr($response, $header_size);
            $response_size = strlen($response_body_raw);
            $history_item = ['method' => $method, 'url' => $url, 'status' => $response_status];
            array_unshift($_SESSION['history'], $history_item);
            $_SESSION['history'] = array_slice(array_unique(array_map('json_encode', $_SESSION['history']), SORT_REGULAR), 0, HISTORY_LIMIT);
            $_SESSION['history'] = array_map('json_decode', $_SESSION['history'], array_fill(0, count($_SESSION['history']), true));
        }
        curl_close($ch);
    }
} else if (isset($_GET['clear_history'])) {
    $_SESSION['history'] = [];
    header('Location: ' . strtok($_SERVER["PHP_SELF"], '?'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple PHP REST Client</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Fira+Code&display=swap');
        .form-input, .form-select, .form-textarea { background-color: #374151; border-color: #4b5563; color: #d1d5db; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px #1e40af; }
        .tab-button.active, .response-tab-button.active { border-bottom: 2px solid #3b82f6; color: #fff; }
    </style>
</head>
<body class="bg-gray-900 text-gray-300">

    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- History Sidebar -->
        <aside class="w-full md:w-64 bg-gray-800 p-4 border-b md:border-b-0 md:border-r border-gray-700 flex-shrink-0">
            <h2 class="text-lg font-bold text-white mb-4">Request History</h2>
            <?php if (!empty($_SESSION['history'])): ?>
                <ul class="space-y-2">
                    <?php foreach ($_SESSION['history'] as $item): ?>
                        <li>
                            <a href="?method=<?php echo htmlspecialchars($item['method']); ?>&url=<?php echo urlencode($item['url']); ?>" class="block p-2 rounded-md hover:bg-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="font-semibold text-xs px-2 py-1 rounded-md text-white <?php echo $item['method'] === 'GET' ? 'bg-blue-600' : 'bg-green-600'; ?>"><?php echo htmlspecialchars($item['method']); ?></span>
                                    <span class="text-sm text-gray-400 truncate" title="<?php echo htmlspecialchars($item['url']); ?>"><?php echo htmlspecialchars($item['url']); ?></span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Status: <?php echo htmlspecialchars($item['status']); ?></div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="?clear_history=1" class="mt-4 inline-block text-sm text-red-400 hover:text-red-300">Clear History</a>
            <?php else: ?>
                <p class="text-sm text-gray-500">No requests yet.</p>
            <?php endif; ?>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-x-hidden">
            <h1 class="text-3xl font-bold text-white mb-6">Simple PHP REST Client</h1>

            <form id="request-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
                <div class="flex flex-col sm:flex-row gap-2">
                    <select name="method" id="method-select" class="form-select rounded-md w-full sm:w-32 flex-shrink-0">
                        <option value="GET" <?php echo ($_POST['method'] ?? 'GET') === 'GET' ? 'selected' : ''; ?>>GET</option>
                        <option value="POST" <?php echo ($_POST['method'] ?? '') === 'POST' ? 'selected' : ''; ?>>POST</option>
                    </select>
                    <input type="url" name="url" placeholder="https://api.example.com" value="<?php echo htmlspecialchars($_POST['url'] ?? $_GET['url'] ?? 'https://jsonplaceholder.typicode.com/posts/1'); ?>" required class="form-input rounded-md flex-1">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Send</button>
                </div>
                
                <div id="request-tabs" class="bg-gray-800 rounded-lg p-4">
                    <div class="flex border-b border-gray-700 mb-4">
                        <button type="button" class="tab-button active py-2 px-4" data-target="headers">Headers</button>
                        <button type="button" class="tab-button py-2 px-4 text-gray-400" data-target="params">Query Params</button>
                        <button type="button" class="tab-button py-2 px-4 text-gray-400" data-target="body">Body</button>
                    </div>

                    <div id="headers-content" class="tab-content space-y-2" data-keys='<?php echo htmlspecialchars(json_encode($_POST['header_keys'] ?? []), ENT_QUOTES, 'UTF-8'); ?>' data-values='<?php echo htmlspecialchars(json_encode($_POST['header_values'] ?? []), ENT_QUOTES, 'UTF-8'); ?>'>
                        <button type="button" class="add-pair-btn mt-2 text-sm text-blue-400 hover:text-blue-300">+ Add Header</button>
                    </div>
                    <div id="params-content" class="tab-content hidden space-y-2" data-keys='<?php echo htmlspecialchars(json_encode($_POST['param_keys'] ?? []), ENT_QUOTES, 'UTF-8'); ?>' data-values='<?php echo htmlspecialchars(json_encode($_POST['param_values'] ?? []), ENT_QUOTES, 'UTF-8'); ?>'>
                        <button type="button" class="add-pair-btn mt-2 text-sm text-blue-400 hover:text-blue-300">+ Add Param</button>
                    </div>
                    <div id="body-content" class="tab-content hidden">
                        <textarea name="post_body" class="form-textarea w-full h-48 rounded-md font-mono text-sm" placeholder='{ "key": "value" }'><?php echo htmlspecialchars($_POST['post_body'] ?? ''); ?></textarea>
                    </div>
                </div>
            </form>

            <?php if ($error_message): ?>
            <div class="mt-6 bg-red-900 border border-red-700 text-red-300 px-4 py-3 rounded-md" role="alert">
                <p><strong class="font-bold">Error:</strong> <?php echo htmlspecialchars($error_message); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($response_status !== null): ?>
            <div id="response-section" class="mt-8">
                <h2 class="text-2xl font-bold text-white mb-4">Response</h2>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mb-4 text-sm">
                    <div class="flex items-center">Status: <span class="ml-2 px-2 py-1 rounded-md text-white font-bold <?php echo ($response_status >= 200 && $response_status < 300) ? 'bg-green-600' : 'bg-red-600'; ?>"><?php echo htmlspecialchars($response_status); ?></span></div>
                    <div class="flex items-center">Time: <span class="ml-2 text-cyan-400"><?php echo htmlspecialchars($request_time); ?> ms</span></div>
                    <div class="flex items-center">Size: <span class="ml-2 text-cyan-400"><?php echo htmlspecialchars(round($response_size / 1024, 2)); ?> KB</span></div>
                </div>
                <div id="response-tabs" class="bg-gray-800 rounded-lg p-4">
                    <div class="flex border-b border-gray-700 mb-4">
                        <button type="button" class="response-tab-button active py-2 px-4" data-target="response-body">Body</button>
                        <button type="button" class="response-tab-button py-2 px-4 text-gray-400" data-target="response-headers">Headers</button>
                    </div>
                    <div id="response-body-content" class="response-tab-content"><?php echo highlight_json_string($response_body_raw); ?></div>
                    <div id="response-headers-content" class="response-tab-content hidden"><pre class="text-sm"><code><?php echo htmlspecialchars($response_headers_raw); ?></code></pre></div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- TAB SWITCHING LOGIC ---
    document.querySelectorAll('#request-tabs, #response-tabs').forEach(container => {
        container.addEventListener('click', e => {
            const button = e.target.closest('[data-target]');
            if (!button) return;

            const targetContent = document.getElementById(button.dataset.target + '-content');
            
            container.querySelectorAll('[data-target]').forEach(btn => {
                btn.classList.remove('active', 'text-white');
                btn.classList.add('text-gray-400');
            });
            button.classList.add('active', 'text-white');
            button.classList.remove('text-gray-400');
            
            container.querySelectorAll('.tab-content, .response-tab-content').forEach(panel => panel.classList.add('hidden'));
            if (targetContent) targetContent.classList.remove('hidden');
        });
    });

    // --- KEY-VALUE PAIR LOGIC ---
    function setupKeyValueSection(type) {
        const container = document.getElementById(type + 's-content');
        if (!container) return;
        
        const createRow = (key = '', value = '') => {
            const row = document.createElement('div');
            row.className = 'flex gap-2 items-center mb-2';
            row.innerHTML = `
                <input type="text" name="${type}_keys[]" placeholder="Key" class="form-input rounded-md flex-1" value="${key}">
                <input type="text" name="${type}_values[]" placeholder="Value" class="form-input rounded-md flex-1" value="${value}">
                <button type="button" class="remove-pair-btn text-white bg-red-600 w-8 h-8 rounded">&times;</button>
            `;
            row.querySelector('.remove-pair-btn').addEventListener('click', () => row.remove());
            return row;
        };

        container.addEventListener('click', e => {
            if (e.target.classList.contains('add-pair-btn')) {
                const addButton = e.target;
                container.insertBefore(createRow(), addButton);
            }
        });
        
        // Repopulate from data attributes
        try {
            const keys = JSON.parse(container.dataset.keys || '[]');
            const values = JSON.parse(container.dataset.values || '[]');
            const addButton = container.querySelector('.add-pair-btn');
            if (keys.length > 0) {
                keys.forEach((key, i) => container.insertBefore(createRow(key, values[i] || ''), addButton));
            } else {
                 container.insertBefore(createRow(), addButton);
            }
        } catch (e) {
            console.error('Failed to parse key-value data:', e);
            const addButton = container.querySelector('.add-pair-btn');
            if(addButton) container.insertBefore(createRow(), addButton);
        }
    }
    
    setupKeyValueSection('header');
    setupKeyValueSection('param');

    // --- METHOD SELECTOR LOGIC (GET/POST) ---
    const methodSelect = document.getElementById('method-select');
    const paramsTab = document.querySelector('[data-target="params"]');
    const bodyTab = document.querySelector('[data-target="body"]');
    
    function handleMethodChange() {
        const isGet = methodSelect.value === 'GET';
        paramsTab.style.display = isGet ? '' : 'none';
        bodyTab.style.display = isGet ? 'none' : '';
        
        const activeTab = document.querySelector('#request-tabs .tab-button.active');
        if (activeTab && activeTab.style.display === 'none') {
            document.querySelector('#request-tabs [data-target="headers"]').click();
        }
    }
    
    methodSelect.addEventListener('change', handleMethodChange);
    handleMethodChange(); // Initial check on page load
});
</script>
</body>
</html>
