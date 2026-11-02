<?php
// ELOK – zmiany z wersji testowej → zatwierdzenie jednym kliknięciem.
// Lista zmian = commity na gałęzi „test”, których nie ma jeszcze na „main” (GitHub).
// „Zatwierdź” = scalenie test → main; GitHub sam wgrywa wtedy kod na prawdziwy panel / stronę.
define('ELOK', 1);
require __DIR__ . '/lib.php';
start_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function ak_out($a) { $a['ok'] = true; echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function ak_fail($m) { echo json_encode(array('ok' => false, 'error' => $m), JSON_UNESCAPED_UNICODE); exit; }
if (!logged_in()) { echo json_encode(array('ok' => false, 'login' => true)); exit; }
$hdr = isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '';
if (!hash_equals($_SESSION['csrf'], $hdr)) ak_fail('Sesja wygasła – odśwież stronę');

$OWNER = 'Maciek1873';
$REPOS = array(
    'elok-panel' => array('Panel', 'https://test-panel.elok-agency.pl', 'https://panel.elok-agency.pl'),
    'elok-strona' => array('Strona www', 'https://test.elok-agency.pl', 'https://elok-agency.pl'),
);
function ak_gh($method, $path, $body = null) {
    $tok = trim(setting('gh_token'));
    if ($tok === '') return array('_err' => 'Brak tokenu GitHub.');
    $c = curl_init('https://api.github.com' . $path);
    $h = array('Authorization: Bearer ' . $tok, 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: ELOK-Panel');
    if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($body)); }
    curl_setopt_array($c, array(CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25));
    $raw = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
    if ($raw === false) return array('_err' => 'Brak połączenia z GitHub: ' . $err);
    $j = $raw === '' ? array() : (json_decode($raw, true) ?: array());
    $j['_code'] = $code;
    if ($code === 401) $j['_err'] = 'GitHub odrzucił token (zły albo wygasł) – wpisz nowy.';
    elseif ($code === 403 || $code === 404) $j['_err'] = 'Token nie ma dostępu do repozytorium (' . $code . '). Sprawdź uprawnienia tokenu.';
    elseif ($code >= 400 && $code !== 409) $j['_err'] = 'GitHub: błąd ' . $code . (isset($j['message']) ? ' – ' . $j['message'] : '');
    return $j;
}
function ak_run($repo, $branch) {
    global $OWNER;
    $r = ak_gh('GET', '/repos/' . $OWNER . '/' . $repo . '/actions/runs?branch=' . $branch . '&per_page=1');
    if (empty($r['workflow_runs'][0])) return null;
    $x = $r['workflow_runs'][0];
    return array('status' => $x['status'], 'ok' => $x['conclusion'] === 'success', 'concl' => (string)$x['conclusion'], 'kiedy' => date('d.m H:i', strtotime($x['updated_at'])), 'url' => $x['html_url']);
}

$in = json_decode(file_get_contents('php://input'), true); if (!is_array($in)) $in = array();
$a = isset($_GET['a']) ? $_GET['a'] : '';

if ($a === 'token') {
    $t = trim((string)(isset($in['token']) ? $in['token'] : ''));
    if (!preg_match('/^(ghp_|github_pat_)[A-Za-z0-9_]{20,}$/', $t)) ak_fail('To nie wygląda na token GitHub (zaczyna się od github_pat_ albo ghp_).');
    setting_set('gh_token', $t); ak_out(array());
}
if ($a === 'lista') {
    $out = array();
    if (trim(setting('gh_token')) === '') ak_out(array('brak_tokenu' => true, 'test' => ELOK_TEST));
    foreach ($REPOS as $repo => $x) {
        $c = ak_gh('GET', '/repos/' . $OWNER . '/' . $repo . '/compare/main...test');
        $list = array(); $err = isset($c['_err']) ? $c['_err'] : '';
        if (!$err) foreach (array_reverse((array)(isset($c['commits']) ? $c['commits'] : array())) as $cm) {
            $msg = trim(strtok((string)$cm['commit']['message'], "\n"));
            if ($msg === '' || stripos($msg, 'Merge ') === 0 || stripos($msg, 'Panel: ') === 0 || stripos($msg, 'Synchronizacja') === 0 || stripos($msg, 'Zatwierdzone w panelu') === 0) continue;
            $list[] = array('msg' => $msg, 'kiedy' => date('d.m H:i', strtotime($cm['commit']['author']['date'])));
        }
        $out[] = array('repo' => $repo, 'nazwa' => $x[0], 'test_url' => $x[1], 'url' => $x[2], 'err' => $err, 'zmiany' => $list,
            'run_main' => $err ? null : ak_run($repo, 'main'), 'run_test' => $err ? null : ak_run($repo, 'test'));
    }
    ak_out(array('repos' => $out, 'test' => ELOK_TEST));
}
if ($a === 'zatwierdz') {
    if (ELOK_TEST) ak_fail('Zatwierdzasz w prawdziwym panelu, nie w testowym.');
    $repo = (string)(isset($in['repo']) ? $in['repo'] : '');
    if (!isset($REPOS[$repo])) ak_fail('Nieznane repozytorium.');
    $m = ak_gh('POST', '/repos/' . $OWNER . '/' . $repo . '/merges', array('base' => 'main', 'head' => 'test', 'commit_message' => 'Zatwierdzone w panelu: wersja testowa → prawdziwa'));
    if (!empty($m['_err'])) ak_fail($m['_err']);
    if ($m['_code'] === 409) ak_fail('Zmiany z testu kłócą się ze zmianami zrobionymi w międzyczasie (np. w edytorze). Napisz do Claude – połączy je.');
    // test dostaje to, co jest na main (np. zmiany z edytora), żeby obie wersje były równe
    ak_gh('POST', '/repos/' . $OWNER . '/' . $repo . '/merges', array('base' => 'test', 'head' => 'main', 'commit_message' => 'Synchronizacja test z main'));
    logi(null, 'aktualizacja', 'Zatwierdzono wersję testową: ' . $repo);
    ak_out(array('nic' => $m['_code'] === 204));
}
ak_fail('Nieznana akcja');
