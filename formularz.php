<?php
// ELOK – strona formularza zgłoszenia (edytowalna w panelu: Maile → Formularz)
if (!defined('ELOK')) { http_response_code(403); exit; }

function form_defaults() {
    return array(
        'kicker' => 'Bezpłatna konsultacja',
        'tytul' => 'Umówmy krótką rozmowę',
        'opis' => 'Zostaw numer – oddzwonię i w 10 minut pokażę, co da się u Was zautomatyzować i ile by to kosztowało.',
        'perks' => "Bezpłatnie\nok. 10 minut\nBez zobowiązań",
        'pola' => array(
            'firma' => array('label' => 'Firma', 'ph' => '', 'show' => 1, 'req' => 1),
            'osoba' => array('label' => 'Imię i nazwisko', 'ph' => '', 'show' => 1, 'req' => 0),
            'telefon' => array('label' => 'Telefon', 'ph' => '', 'show' => 1, 'req' => 1),
            'email' => array('label' => 'E-mail', 'ph' => '', 'show' => 1, 'req' => 1),
            'czym' => array('label' => 'Czym się zajmujecie?', 'ph' => 'np. warsztat, 3 stanowiska, BMW i Audi', 'show' => 1, 'req' => 0),
            'co' => array('label' => 'Co chcielibyście usprawnić?', 'ph' => 'np. wyceny, przypomnienia dla klientów, zamówienia, faktury', 'show' => 1, 'req' => 0),
            'dzien' => array('label' => 'Dzień', 'ph' => '', 'show' => 1, 'req' => 0),
            'kiedy' => array('label' => 'Godzina', 'ph' => '', 'show' => 1, 'req' => 0),
        ),
        'dzien_opcje' => "Obojętnie\nPoniedziałek\nWtorek\nŚroda\nCzwartek\nPiątek",
        'kiedy_opcje' => "Obojętnie, w godzinach pracy\nRano (8:00–10:00)\nPrzed południem (10:00–12:00)\nPo południu (12:00–15:00)\nPóźnym popołudniem (15:00–18:00)",
        'przycisk' => 'Wyślij – odezwę się w ciągu 24 godzin',
        'nota' => 'Dane wykorzystamy tylko do kontaktu w sprawie tej rozmowy. Wymagane pola oznaczone *.',
        'podpis' => 1,
        'dzieki_tytul' => 'Dziękuję!',
        'dzieki_tekst' => "Zgłoszenie dotarło. Odezwę się w ciągu 24 godzin.\n\nPotwierdzenie wysłałem na {email}.",
    );
}
function form_defaults_en() {
    return array(
        'kicker' => 'Free consultation',
        'tytul' => "Let's have a quick chat",
        'opis' => "Leave your number – I'll call you back and show you what we could automate for your business.",
        'perks' => "Free\nabout 15 minutes\nNo obligation",
        'pola' => array(
            'firma' => array('label' => 'Company', 'ph' => '', 'show' => 1, 'req' => 1),
            'osoba' => array('label' => 'Your name', 'ph' => '', 'show' => 1, 'req' => 0),
            'telefon' => array('label' => 'Phone', 'ph' => '', 'show' => 1, 'req' => 1),
            'email' => array('label' => 'Email', 'ph' => '', 'show' => 1, 'req' => 1),
            'czym' => array('label' => 'What does your business do?', 'ph' => 'e.g. independent garage, 3 bays, German cars', 'show' => 1, 'req' => 0),
            'co' => array('label' => 'What would you like to improve?', 'ph' => 'e.g. quotes, customer reminders, parts ordering, invoices', 'show' => 1, 'req' => 0),
            'dzien' => array('label' => 'Day', 'ph' => '', 'show' => 1, 'req' => 0),
            'kiedy' => array('label' => 'Time (UK)', 'ph' => '', 'show' => 1, 'req' => 0),
        ),
        'dzien_opcje' => "Any day\nMonday\nTuesday\nWednesday\nThursday\nFriday",
        'kiedy_opcje' => "Any time during working hours\nMorning (8–10am)\nLate morning (10am–12pm)\nAfternoon (12–3pm)\nLate afternoon (3–6pm)",
        'przycisk' => "Send – I'll get back to you within 24 hours",
        'nota' => 'We will only use your details to contact you about this call. Fields marked * are required.',
        'podpis' => 1,
        'dzieki_tytul' => 'Thank you!',
        'dzieki_tekst' => "Your request has arrived. I'll get back to you within 24 hours.\n\nA confirmation has been sent to {email}.",
    );
}
function form_cfg($override = null, $lang = 'pl') {
    $d = $lang === 'en' ? form_defaults_en() : form_defaults();
    $c = $override !== null ? $override : json_decode((string)setting($lang === 'en' ? 'formularz_en' : 'formularz'), true);
    if (!is_array($c)) return $d;
    foreach ($d as $k => $v) {
        if ($k === 'pola') {
            foreach ($v as $f => $pd) {
                $pc = isset($c['pola'][$f]) && is_array($c['pola'][$f]) ? $c['pola'][$f] : array();
                foreach ($pd as $pk => $pv) $d['pola'][$f][$pk] = isset($pc[$pk]) ? (is_int($pv) ? ($pc[$pk] ? 1 : 0) : mb_substr((string)$pc[$pk], 0, 300)) : $pv;
            }
        } elseif (isset($c[$k])) {
            $d[$k] = is_int($v) ? ($c[$k] ? 1 : 0) : mb_substr((string)$c[$k], 0, 2000);
        }
    }
    // firma i e-mail zawsze widoczne i wymagane (potrzebne do potwierdzenia)
    foreach (array('firma', 'email') as $f) { $d['pola'][$f]['show'] = 1; $d['pola'][$f]['req'] = 1; }
    return $d;
}

function pub_html($title, $body) {
    return '<!DOCTYPE html><html lang="' . lang() . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($title) . '</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;800&display=swap" rel="stylesheet">'
        . '<style>
*{box-sizing:border-box}body{margin:0;background:#F5F4F0;color:#151515;font-family:Inter,Arial,sans-serif;font-size:15px;line-height:1.55;-webkit-font-smoothing:antialiased}
.w{max-width:540px;margin:0 auto;padding:36px 16px 60px}.logo{font-family:Outfit,Arial;font-weight:800;font-size:26px;letter-spacing:-.5px;margin-bottom:22px}.logo span{color:#C5A059}
.c{background:#fff;border:1px solid #E7E4DC;border-radius:18px;padding:28px 24px;box-shadow:0 10px 30px rgba(20,18,10,.05)}
h1{font-family:Outfit,Arial;font-weight:600;font-size:25px;line-height:1.2;margin:0 0 8px}p{margin:0 0 14px;color:#4A4740}
label{display:block;font-size:12.5px;font-weight:600;color:#5E5A51;margin:14px 0 6px}
input,textarea,select{width:100%;padding:12px 14px;border:1px solid #DEDAD0;border-radius:11px;font:inherit;background:#FCFBF9;color:#151515}
select{-webkit-appearance:none;appearance:none;background-image:linear-gradient(45deg,transparent 50%,#8A867C 50%),linear-gradient(135deg,#8A867C 50%,transparent 50%);background-position:calc(100% - 20px) 52%,calc(100% - 14px) 52%;background-size:6px 6px;background-repeat:no-repeat;padding-right:40px}
input:focus,textarea:focus,select:focus{outline:0;border-color:#C5A059;background:#fff;box-shadow:0 0 0 3px rgba(197,160,89,.18)}
textarea{min-height:88px;resize:vertical}.row{display:flex;gap:12px}.row>div{flex:1;min-width:0}
button{margin-top:22px;width:100%;padding:15px;border:0;border-radius:12px;background:#151515;color:#fff;font:600 15px Inter,Arial;cursor:pointer}
button:hover{background:#000}.ok{width:54px;height:54px;border-radius:50%;background:#EEF6F0;color:#2E7D4F;display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:16px}
.s{font-size:12px;color:#9A968C;margin-top:14px}
.kick{font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:#8B6B30;margin-bottom:8px}
.perks{display:flex;gap:8px;flex-wrap:wrap;margin:4px 0 8px}.perks span{background:#F7F1E3;color:#7A5C24;border-radius:20px;padding:6px 12px;font-size:12.5px;font-weight:600}
.who{margin-top:22px;padding-top:16px;border-top:1px solid #EFEDE7;display:flex;flex-direction:column;gap:2px;font-size:13px;color:#77746C}
.who b{color:#151515;font-size:14px}.who a{color:#151515;font-weight:600;text-decoration:none;margin-top:2px}.hp{position:absolute;left:-9999px}
.kiedy-t{font-weight:600;font-size:14px;margin:16px 0 -4px}.kiedy-t span{font-weight:400;color:#8A867C}
@media(max-width:480px){.row{display:block}}
</style></head><body><div class="w"><div class="logo">ELOK<span>.</span></div><div class="c">' . $body . '</div></div></body></html>';
}

function who_html() {
    $en = lang() === 'en'; $rola = $en ? setting('podpis_rola_en') : setting('podpis_rola'); $tel = my_tel();
    $nm = $en && setting('podpis_imie_en') !== '' ? setting('podpis_imie_en') : setting('podpis_imie');
    return '<div class="who"><b>' . h($nm) . '</b>' . ($rola !== '' ? '<span>' . h($rola) . '</span>' : '')
        . ($tel !== '' ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $tel)) . '">' . h($tel) . '</a>' : '') . '</div>';
}

// $pre = array(pole => wartość), $err = komunikat
function form_body($c, $pre, $err) {
    $p = $c['pola'];
    $fld = function ($k, $type) use ($p, $pre) {
        if (!$p[$k]['show']) return '';
        $req = $p[$k]['req'] ? ' required' : '';
        $lab = '<label>' . h($p[$k]['label']) . ($p[$k]['req'] ? ' *' : '') . '</label>';
        $v = isset($pre[$k]) ? h($pre[$k]) : '';
        $ph = $p[$k]['ph'] !== '' ? ' placeholder="' . h($p[$k]['ph']) . '"' : '';
        if ($type === 'ta') return $lab . '<textarea name="' . $k . '"' . $req . $ph . '>' . $v . '</textarea>';
        return $lab . '<input name="' . $k . '" type="' . $type . '"' . $req . $ph . ' value="' . $v . '">';
    };
    $perks = '';
    foreach (explode("\n", $c['perks']) as $pk) if (trim($pk) !== '') $perks .= '<span>✓ ' . h(trim($pk)) . '</span>';
    $osoba = $fld('osoba', 'text'); $tel = $fld('telefon', 'tel');
    if ($p['dzien']['show'] && $p['kiedy']['label'] === 'Kiedy najlepiej zadzwonić?') $p['kiedy']['label'] = 'Godzina'; // stara nazwa pola
    $sel = function ($k, $optKey) use ($p, $c, $pre) {
        if (!$p[$k]['show']) return '';
        $opts = array_values(array_filter(array_map('trim', explode("\n", (string)$c[$optKey])), 'strlen'));
        $cur = isset($pre[$k]) ? $pre[$k] : '';
        $o = $p[$k]['req'] ? '<option value="">' . (lang() === 'en' ? '– choose –' : '– wybierz –') . '</option>' : '';
        foreach ($opts as $op) $o .= '<option' . ($cur === $op ? ' selected' : '') . '>' . h($op) . '</option>';
        return '<label>' . h($p[$k]['label']) . ($p[$k]['req'] ? ' *' : '') . '</label><select name="' . $k . '"' . ($p[$k]['req'] ? ' required' : '') . '>' . $o . '</select>';
    };
    $sd = $sel('dzien', 'dzien_opcje'); $sk = $sel('kiedy', 'kiedy_opcje');
    $sel_kiedy = ($sd && $sk) ? '<div class="kiedy-t">' . (lang() === 'en' ? 'When is the best time to call? <span>(optional)</span>' : 'Kiedy najlepiej zadzwonić? <span>(opcjonalnie)</span>') . '</div><div class="row"><div>' . $sd . '</div><div>' . $sk . '</div></div>' : $sd . $sk;
    $pair = ($osoba && $tel) ? '<div class="row"><div>' . $osoba . '</div><div>' . $tel . '</div></div>' : $osoba . $tel;
    return ($c['kicker'] !== '' ? '<div class="kick">' . h($c['kicker']) . '</div>' : '') . '<h1>' . h($c['tytul']) . '</h1>'
        . ($c['opis'] !== '' ? '<p>' . nl2br(h($c['opis']), false) . '</p>' : '')
        . ($perks ? '<div class="perks">' . $perks . '</div>' : '')
        . ($err ? '<p style="color:#B3261E"><b>' . h($err) . '</b></p>' : '')
        . '<form method="post"><input type="hidden" name="ts" value="' . time() . '"><input class="hp" name="www2" tabindex="-1" autocomplete="off">'
        . $fld('firma', 'text') . $pair . $fld('email', 'email') . $fld('czym', 'text') . $fld('co', 'ta') . $sel_kiedy
        . '<button>' . h($c['przycisk']) . '</button>' . ($c['nota'] !== '' ? '<p class="s">' . h($c['nota']) . '</p>' : '') . '</form>'
        . ($c['podpis'] ? who_html() : '');
}
function thanks_body($c, $email) {
    $t = '';
    foreach (preg_split("/\n\s*\n/", trim($c['dzieki_tekst'])) as $par) {
        if (trim($par) === '') continue;
        $t .= '<p>' . str_replace('{email}', '<b>' . h($email) . '</b>', nl2br(h($par), false)) . '</p>';
    }
    return '<div class="ok">&#10003;</div><h1>' . h($c['dzieki_tytul']) . '</h1>' . $t . ($c['podpis'] ? who_html() : '');
}
