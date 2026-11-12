<?php
// ELOK – gotowe szablony maili dla branż (używane przy instalacji i "Przywróć gotowy szablon")
if (!defined('ELOK')) { http_response_code(403); exit; }

function default_case_study() {
    return "**Przykład z wdrożenia: sklep Tanie Oryginały (części BMW i MINI)**\n\n"
        . "- Pozyskiwanie warsztatów | Mailing B2B z przypomnieniem po 5 dniach – zgłoszenia od razu na telefon.\n"
        . "- Konta partnerskie | Rabat przypisany do firmy, z bezpiecznikiem marży i wygaszaniem po 60 dniach bez zakupu.\n"
        . "- Zamówienia bez przepisywania | Zamawianie u dostawcy przez API, etykiety kurierskie, paragony, faktury i PZ w wFirma.\n"
        . "- Allegro w jednym panelu | Zamówienia z Allegro i automatyczne wystawianie ofert z ceną liczoną z prowizją.";
}

// Zwraca: nazwa branży => array(typ => array(temat, bloki[], nadtytul, naglowek, tresc, przycisk))
// Styl: krótko, po ludzku, od Maćka + ramka "Ostatnia realizacja" + przycisk do formularza.
function default_templates() {
    $ws = array('t' => 'ramka', 'title' => 'Ostatnia realizacja – warsztat ServiceMile',
        'text' => 'Elektroniczny katalog części połączony z cenami, ofertami i fakturami. Jedno kliknięcie w katalogu tworzy ofertę dla klienta, a po akceptacji faktura wystawia się sama.');
    $to = array('t' => 'ramka', 'title' => 'Ostatnia realizacja – sklep z częściami Tanie Oryginały',
        'text' => 'Nowa, w pełni zautomatyzowana strona: ceny i stany u dostawcy na żywo, duże wyceny przez wklejenie listy numerów, panel zamówień z aplikacją na telefon. Jedno kliknięcie zamawia części u dostawcy, a faktury wystawiają się same.');
    $end = array('t' => 'tekst', 'text' => 'Chętnie w 15 minut pokażę, jak to działa. Rozmowa jest bezpłatna i do niczego nie zobowiązuje – nawet jeśli nie podejmiemy współpracy, może jakaś rada się przyda.');
    $nolink = array('t' => 'tekst', 'text' => 'Wolą Państwo nie klikać w linki? Wystarczy odpisać na tego maila albo zadzwonić: {moj_telefon}.');
    $btn = array('t' => 'przycisk', 'text' => 'Umów bezpłatną rozmowę', 'sub' => 'Krótki formularz – zajmie 30 sekund. Odezwę się w ciągu 24 godzin.');
    $mk = function ($temat, $blocks) { return array('temat' => $temat, 'bloki' => $blocks, 'nadtytul' => '', 'naglowek' => '', 'tresc' => '', 'przycisk' => ''); };
    $first = function ($intro, $box) use ($mk, $end, $btn, $nolink) {
        return $mk('Pytanie do {firma}', array(array('t' => 'tekst', 'text' => "Dzień dobry,\n\n" . $intro), $box, $end, $btn, $nolink));
    };
    // pierwszy mail z listą korzyści: $intro (tekst), $kor (linie "Tytuł | opis"), $box (ramka)
    $firstK = function ($intro, $kor, $box) use ($mk, $end, $btn, $nolink) {
        return $mk('Pytanie do {firma}', array(array('t' => 'tekst', 'text' => "Dzień dobry,\n\n" . $intro), array('t' => 'korzysci', 'text' => $kor), $box, $end, $btn, $nolink));
    };
    $remind = function ($o_czym) use ($mk, $btn, $nolink) {
        $b = $btn; $b['text'] = 'Tak, chcę porozmawiać'; $b['sub'] = '';
        return $mk('Wracam z krótkim pytaniem', array(
            array('t' => 'tekst', 'text' => "Dzień dobry,\n\nkilka dni temu pisałem w sprawie " . $o_czym . ". Wiem, że skrzynka szybko się zapełnia, więc tylko krótko przypominam.\n\nJeśli temat jest ciekawy, wystarczy jedno kliknięcie – w krótkiej, 15-minutowej rozmowie pokażę, co da się usprawnić u Państwa. Rozmowa jest bezpłatna."),
            $b,
            $nolink,
            array('t' => 'tekst', 'text' => 'Jeśli to nie jest temat dla Państwa, proszę po prostu zignorować tę wiadomość.'),
        ));
    };
    // $o_czym = "w sprawie ...", $co = co mogą chcieć usprawnić
    $last = function ($o_czym, $co = 'coś uprościć w codziennej pracy') use ($mk, $btn) {
        $b = $btn; $b['text'] = 'Chętnie porozmawiam'; $b['sub'] = '';
        return $mk('Ostatnia wiadomość ode mnie', array(
            array('t' => 'tekst', 'text' => "Dzień dobry,\n\nto już ostatnia wiadomość ode mnie w sprawie " . $o_czym . " – nie chcę zaśmiecać skrzynki.\n\nJeśli kiedyś będą Państwo chcieli " . $co . ", wystarczy kliknąć niżej. Rozmowa jest bezpłatna i do niczego nie zobowiązuje."),
            $b,
            array('t' => 'tekst', 'text' => "Wolą Państwo nie klikać w linki? Wystarczy odpisać na tego maila albo zadzwonić: {moj_telefon}.\n\nTak czy inaczej – dziękuję za uwagę i powodzenia!"),
        ));
    };
    $confirm = $mk('Dziękuję – zadzwonię', array(
        array('t' => 'tekst', 'text' => "Dzień dobry,\n\ndziękuję za zgłoszenie! Odezwę się w ciągu 24 godzin albo w terminie, który Państwo wybrali."),
        array('t' => 'korzysci', 'text' => "Ok. 15 minut | Telefonicznie albo online – jak Państwu wygodniej.\nBezpłatnie i bez zobowiązań | Opowiedzą Państwo, jak pracujecie, a ja powiem, co da się uprościć.\nKonkretnie | Dostaną Państwo propozycję z zakresem i ceną."),
        array('t' => 'tekst', 'text' => 'Gdyby było pilniej, proszę dzwonić: {moj_telefon}'),
    ));
    return array_merge(ask_sets($mk, $confirm), array(
        'Warsztaty samochodowe' => array(
            'pierwszy' => $first("mam na imię {imie}, w ELOK robimy automatyzacje dla warsztatów. Sam też prowadzę warsztat, więc wiem, ile czasu schodzi na wyceny, telefony i papiery.", $ws),
            'przypomnienie' => $remind('automatyzacji wycen i faktur w warsztacie'),
            'przypomnienie2' => $last('usprawnień w warsztacie', 'skrócić wyceny, zautomatyzować przypomnienia dla klientów albo ograniczyć papiery'),
            'potwierdzenie' => $confirm,
        ),
        'Hurtownie części' => array(
            'pierwszy' => $first("mam na imię {imie}, w ELOK robimy automatyzacje dla firm z branży motoryzacyjnej – od zamówień od warsztatów po faktury.", $to),
            'przypomnienie' => $remind('automatyzacji zamówień od warsztatów'),
            'przypomnienie2' => $last('automatyzacji zamówień od warsztatów'),
            'potwierdzenie' => $confirm,
        ),
        'Sklepy WooCommerce / Shopify' => array(
            'pierwszy' => $first("mam na imię {imie}, w ELOK robimy automatyzacje dla sklepów internetowych – od zamówień i wycen po faktury i wysyłki.", $to),
            'przypomnienie' => $remind('automatyzacji zamówień w sklepie'),
            'przypomnienie2' => $last('automatyzacji zamówień w sklepie'),
            'potwierdzenie' => $confirm,
        ),
        'Sprzedawcy Allegro' => array(
            'pierwszy' => $first("mam na imię {imie}, w ELOK robimy automatyzacje dla sprzedawców internetowych – zamówienia, wysyłki i faktury bez ręcznego przepisywania.", $to),
            'przypomnienie' => $remind('automatyzacji sprzedaży internetowej'),
            'przypomnienie2' => $last('automatyzacji sprzedaży internetowej'),
            'potwierdzenie' => $confirm,
        ),
        'Stacje kontroli pojazdów' => array(
            'pierwszy' => $mk('Pytanie do {firma}', array(
                array('t' => 'tekst', 'text' => "Dzień dobry,\n\nsami wywodzimy się z motoryzacji i w ELOK robimy automatyzacje dla firm z branży – także dla stacji kontroli pojazdów."),
                array('t' => 'korzysci', 'text' => "Przypomnienia o badaniu | Klient dostaje e-mail na kilka tygodni przed końcem ważności badania technicznego – i wraca do Państwa, a nie do konkurencji.\nRezerwacja terminu online | Prosty kalendarz na stronie – klient sam wybiera dzień i godzinę, a Państwo widzą wszystkie wizyty w jednym miejscu.\nMniej telefonów | Potwierdzenie i przypomnienie o wizycie wysyłają się same."),
                array('t' => 'ramka', 'title' => 'Jak to działa w praktyce', 'text' => 'Po badaniu klient zostawia e-mail. Po 11 miesiącach system sam wysyła mu przypomnienie z linkiem do rezerwacji – klient wybiera godzinę, a Państwo dostają powiadomienie.'),
                array('t' => 'tekst', 'text' => 'Chętnie w 10 minut pokażę, jak to działa. Rozmowa jest bezpłatna i do niczego nie zobowiązuje – warto chociaż posłuchać.'),
                $btn,
            )),
            'przypomnienie' => $remind('przypomnień o badaniu technicznym i rezerwacji terminów online'),
            'przypomnienie2' => $last('przypomnień o badaniu technicznym i rezerwacji terminów online', 'żeby klienci sami wracali na badanie i rezerwowali termin online'),
            'potwierdzenie' => $confirm,
        ),
        'Sklepy z częściami samochodowymi' => array(
            'pierwszy' => $firstK("sami wywodzimy się z motoryzacji i wiemy, ile czasu w sklepie z częściami zjadają zamówienia u dostawców, wyceny dla klientów i papiery.\n\nW ELOK robimy proste automatyzacje dla sklepów z częściami – od jednej małej rzeczy po cały system zamówień.",
                "Zamówienia u hurtowni same | Zamówienie klienta od razu trafia do dostawcy – bez przepisywania.\nEtykiety i faktury bez klikania | Kurier, faktura i paragon wystawiają się automatycznie.\nWycena z linkiem do płatności | Klient dostaje ofertę, płaci online, a zamówienie leci dalej samo.",
                array('t' => 'ramka', 'title' => 'Ostatnia realizacja – sklep z oryginalnymi częściami BMW i MINI', 'text' => 'Cały system zamówień z CRM: ceny i stany u dostawcy na żywo, automatyczne zamówienia w hurtowni, etykiety, faktury i oferty z płatnością online – w jednym panelu, także na telefonie.')),
            'przypomnienie' => $remind('automatyzacji zamówień w sklepie z częściami'),
            'przypomnienie2' => $last('automatyzacji sklepu', 'skrócić obsługę zamówień, wyceny albo wystawianie faktur'),
            'potwierdzenie' => $confirm,
        ),
        'Sklepy internetowe' => array(
            'pierwszy' => $firstK("prowadzenie sklepu internetowego to dziś w dużej części klikanie: zamówienia, wysyłki, faktury, odpowiedzi klientom.\n\nW ELOK robimy proste automatyzacje dla sklepów internetowych – dopasowane do tego, jak Państwo pracują, bez zmiany całego sklepu.",
                "Zamówienia bez przepisywania | Zamówienie idzie prosto do dostawcy albo magazynu.\nWysyłki i faktury same | Etykieta kurierska, faktura i mail do klienta – automatycznie.\nWszystko w jednym panelu | Zamówienia, klienci i płatności w jednym miejscu, także na telefonie.",
                array('t' => 'ramka', 'title' => 'Przykład z naszej realizacji – sklep z częściami samochodowymi premium', 'text' => 'Automatyczne zamówienia w hurtowni, etykiety, faktury i oferty z płatnością online w jednym panelu. Podobne rozwiązania robimy dla sklepów z każdej branży.')),
            'przypomnienie' => $remind('automatyzacji zamówień w sklepie internetowym'),
            'przypomnienie2' => $last('automatyzacji sklepu', 'mniej klikać przy zamówieniach, wysyłkach i fakturach'),
            'potwierdzenie' => $confirm,
        ),
        'UK – Garages' => uk_templates($mk, $btn),
        'Inne firmy' => array(
            'pierwszy' => $first("mam na imię {imie}, w ELOK robimy automatyzacje sprzedaży i obsługi klientów – oferty, zamówienia, faktury, przypomnienia.", $to),
            'przypomnienie' => $remind('automatyzacji w Waszej firmie'),
            'przypomnienie2' => $last('automatyzacji w Państwa firmie'),
            'potwierdzenie' => $confirm,
        ),
    ));
}

// zapis szablonu (upsert) z blokami
function save_template($branza_id, $typ, $t) {
    q('INSERT INTO szablony(branza_id,typ,temat,nadtytul,naglowek,tresc,przycisk,bloki,updated_at) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE temat=VALUES(temat),nadtytul=VALUES(nadtytul),naglowek=VALUES(naglowek),tresc=VALUES(tresc),przycisk=VALUES(przycisk),bloki=VALUES(bloki),updated_at=VALUES(updated_at)',
        array((int)$branza_id, $typ, $t['temat'], isset($t['nadtytul']) ? $t['nadtytul'] : '', isset($t['naglowek']) ? $t['naglowek'] : '', isset($t['tresc']) ? $t['tresc'] : '', isset($t['przycisk']) ? $t['przycisk'] : '',
            json_encode(isset($t['bloki']) ? array_values($t['bloki']) : array(), JSON_UNESCAPED_UNICODE), now()));
}

// Szablon domyślny dla danej nazwy branży (dopasowanie po nazwie, inaczej "Inne firmy")
function default_template_for($branza_nazwa, $typ) {
    $all = default_templates();
    $n = mb_strtolower(trim($branza_nazwa));
    foreach ($all as $name => $set) if (mb_strtolower($name) === $n) return $set[$typ];
    if (mb_strpos($n, 'uk ') === 0 || mb_strpos($n, 'uk–') === 0 || mb_strpos($n, 'uk -') === 0 || mb_strpos($n, '(uk)') !== false) return $all['UK – Garages'][$typ];
    $keys = array('remont' => 'Firmy remontowe i instalatorzy', 'instal' => 'Firmy remontowe i instalatorzy', 'budow' => 'Firmy remontowe i instalatorzy', 'szkoł' => 'Szkoły jazdy', 'jazd' => 'Szkoły jazdy', 'stolar' => 'Stolarnie i meble na wymiar', 'mebl' => 'Stolarnie i meble na wymiar', 'salon' => 'Salony beauty i fryzjerskie', 'fryzj' => 'Salony beauty i fryzjerskie', 'kosmet' => 'Salony beauty i fryzjerskie', 'beauty' => 'Salony beauty i fryzjerskie', 'fizjo' => 'Gabinety fizjoterapii', 'rachunk' => 'Biura rachunkowe', 'księg' => 'Biura rachunkowe', 'hurt' => 'Hurtownie części', 'częśc' => 'Sklepy z częściami samochodowymi', 'czesc' => 'Sklepy z częściami samochodowymi', 'internet' => 'Sklepy internetowe', 'stacj' => 'Stacje kontroli pojazdów', 'skp' => 'Stacje kontroli pojazdów', 'diagnost' => 'Stacje kontroli pojazdów', 'warsztat' => 'Warsztaty samochodowe', 'hurt' => 'Hurtownie części', 'sklep' => 'Sklepy WooCommerce / Shopify', 'woo' => 'Sklepy WooCommerce / Shopify', 'shopify' => 'Sklepy WooCommerce / Shopify', 'allegro' => 'Sprzedawcy Allegro');
    foreach ($keys as $k => $name) if (mb_strpos($n, $k) !== false) return $all[$name][$typ];
    return $all['Inne firmy'][$typ];
}

// Jednorazowo dodaje branżę "Stacje kontroli pojazdów" z gotowymi mailami (jeśli jej nie ma)
function skp_migrate() {
    if (setting('skp_dodane') === '1') return;
    $jest = false;
    foreach (all('SELECT nazwa FROM branze') as $b) { $n = mb_strtolower($b['nazwa']); if (mb_strpos($n, 'stacj') !== false || mb_strpos($n, 'skp') !== false) $jest = true; }
    if (!$jest) {
        q('INSERT INTO branze(nazwa,aktywna,kolejnosc) VALUES(?,1,?)', array('Stacje kontroli pojazdów', (int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze')));
        $id = (int)db()->lastInsertId();
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie') as $t) save_template($id, $t, default_template_for('Stacje kontroli pojazdów', $t));
    }
    setting_set('skp_dodane', '1');
}

// Jednorazowo dodaje branże "Sklepy z częściami samochodowymi" (PKD 4532Z) i "Sklepy internetowe" (PKD 4791Z) z gotowymi mailami
function sklepy_migrate() {
    if (setting('sklepy_dodane') === '1') return;
    $have = array(); foreach (all('SELECT nazwa FROM branze') as $b) $have[mb_strtolower($b['nazwa'])] = 1;
    foreach (array('Sklepy z częściami samochodowymi', 'Sklepy internetowe') as $n) {
        if (isset($have[mb_strtolower($n)])) continue;
        q('INSERT INTO branze(nazwa,aktywna,kolejnosc) VALUES(?,1,?)', array($n, (int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze')));
        $id = (int)db()->lastInsertId();
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie') as $t) save_template($id, $t, default_template_for($n, $t));
    }
    setting_set('sklepy_dodane', '1');
}

// Angielskie maile dla brytyjskich warsztatów (branża z językiem "en")
function uk_templates($mk, $btn) {
    $b = $btn; $b['text'] = 'Book a free call'; $b['sub'] = "Short form – takes 30 seconds. I'll get back to you within 24 hours.";
    $nolink = array('t' => 'tekst', 'text' => 'Prefer not to click links? Just reply to this email.');
    $first = $mk('Quick question for {firma}', array(
        array('t' => 'tekst', 'text' => "Hi,\n\nwe come from the automotive trade, so we know how much of a garage's day goes on admin – looking up parts, building quotes, chasing customers and paperwork.\n\nAt ELOK we build simple automations for independent garages – from one small fix that saves an hour a day to a complete job management system."),
        array('t' => 'korzysci', 'text' => "Quotes in a few clicks | Parts catalogue linked to your supplier's prices – no retyping.\nAutomatic reminders | MOT, service and booking reminders go out to customers on their own.\nWorks with what you use | We connect to your current garage software instead of replacing it."),
        array('t' => 'ramka', 'title' => 'Recent project – independent BMW specialist', 'text' => 'Electronic parts catalogue (BMW ETK) linked to prices, quotes and invoicing. One click in the catalogue creates a quote for the customer, and once it is approved the invoice is generated automatically.'),
        array('t' => 'tekst', 'text' => "I'd be happy to show you how it works in a 15-minute call. It's free and there's no obligation – even if we don't end up working together, you might pick up a useful idea or two."),
        $b, $nolink,
    ));
    $r = $btn; $r['text'] = "Yes, let's talk"; $r['sub'] = '';
    $remind = $mk('Following up on my last email', array(
        array('t' => 'tekst', 'text' => "Hi,\n\na few days ago I wrote about automating quotes and paperwork in your garage. I know inboxes fill up fast, so just a quick nudge.\n\nIf it sounds useful, one click is all it takes – in a short 15-minute call I'll show you what could be simplified in your business. The call is free."),
        $r, $nolink,
        array('t' => 'tekst', 'text' => "If it's not relevant, please just ignore this email."),
    ));
    $l = $btn; $l['text'] = 'Happy to chat'; $l['sub'] = '';
    $last = $mk('Last email from me', array(
        array('t' => 'tekst', 'text' => "Hi,\n\nthis is my last email about automating your garage – I don't want to clutter your inbox.\n\nIf you ever want to speed up quotes, automate customer reminders or cut down on paperwork, just click below. The call is free and there's no obligation."),
        $l,
        array('t' => 'tekst', 'text' => "Prefer not to click links? Just reply to this email.\n\nEither way – thanks for your time and all the best!"),
    ));
    $confirm = $mk("Thank you – I'll be in touch", array(
        array('t' => 'tekst', 'text' => "Hi,\n\nthank you for your request! I'll get back to you within 24 hours or at the time you chose."),
        array('t' => 'korzysci', 'text' => "About 15 minutes | By phone or video call – whatever suits you.\nFree, no obligation | You tell me how you work, and I'll suggest what can be simplified.\nStraight to the point | You'll get a proposal with scope and price."),
        array('t' => 'tekst', 'text' => "If it's urgent, call me on {moj_telefon}."),
    ));
    return array('pierwszy' => $first, 'przypomnienie' => $remind, 'przypomnienie2' => $last, 'potwierdzenie' => $confirm);
}
// Jednorazowo dodaje branżę "UK – Garages" (język angielski, na start limit 20/dzień)
function uk_migrate() {
    if (setting('uk_dodane') === '1') return;
    add_col('branze', 'jezyk', "VARCHAR(2) NOT NULL DEFAULT 'pl'");
    add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');
    if (!val("SELECT id FROM branze WHERE nazwa='UK – Garages'")) {
        q("INSERT INTO branze(nazwa,aktywna,kolejnosc,jezyk,limit_dzienny) VALUES('UK – Garages',1,?,'en',20)", array((int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze')));
        $id = (int)db()->lastInsertId();
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie') as $t) save_template($id, $t, default_template_for('UK – Garages', $t));
    }
    setting_set('uk_dodane', '1');
}

// Krótkie maile-pytania (bez oferty, bez przycisku): prosimy o zgodę na przesłanie informacji, klient odpisuje „tak” albo „nie”.
// Zawsze: co robimy (automatyzacje, strony, marketing) + co konkretnie widzimy u nich do zautomatyzowania.
function ask_templates($mk, $confirm, $temat, $intro, $o_czym) {
    $t = function ($x) { return array('t' => 'tekst', 'text' => $x); };
    return array(
        'pierwszy' => $mk($temat, array($t("Dzień dobry,\n\nmam na imię {imie}, jestem z ELOK. Robimy dla firm automatyzacje, strony internetowe i marketing.\n\n" . $intro . "\n\nCzy mogę przesłać kilka zdań, jak to mogłoby wyglądać u Państwa i ile kosztuje? Wystarczy odpisać „tak”.\n\nJeśli to nie temat dla Państwa, proszę odpisać „nie” – więcej nie napiszę."))),
        'przypomnienie' => $mk('Re: ' . $temat, array($t("Dzień dobry,\n\nwracam krótko do pytania sprzed kilku dni w sprawie " . $o_czym . ".\n\nCzy mogę przesłać kilka zdań, jak to mogłoby wyglądać u Państwa i ile kosztuje? Wystarczy odpisać „tak” albo „nie”."))),
        'przypomnienie2' => $mk('Ostatnia wiadomość ode mnie', array($t("Dzień dobry,\n\nto ostatnia wiadomość ode mnie w sprawie " . $o_czym . ", nie chcę zaśmiecać skrzynki.\n\nGdyby temat był kiedyś aktualny, wystarczy odpisać na tego maila albo zadzwonić: {moj_telefon}.\n\nPozdrawiam i powodzenia!"))),
        'potwierdzenie' => $confirm,
    );
}
// Zestaw z gotowych tekstów: pierwszy + 2 przypomnienia + potwierdzenie
function set3($mk, $confirm, $temat, $first, $r1, $r2) {
    $t = function ($x) { return array(array('t' => 'tekst', 'text' => $x)); };
    return array('pierwszy' => $mk($temat, $t($first)), 'przypomnienie' => $mk('Re: ' . $temat, $t($r1)), 'przypomnienie2' => $mk('Ostatnia wiadomość ode mnie', $t($r2)), 'potwierdzenie' => $confirm);
}
// Test 4 stylów maila (każda branża ma inny styl):
// A „Pytanie wprost” – biura rachunkowe, B „Pytanie o problem” – firmy remontowe,
// C „Od przedsiębiorcy” – szkoły jazdy, D „Bardzo krótki” – stolarnie
function ask_sets($mk, $confirm) {
    $nie = "Jeśli to nie temat dla Państwa, proszę odpisać „nie” – więcej nie napiszę.";
    $ost = function ($o) { return "Dzień dobry,\n\nto ostatnia wiadomość ode mnie w sprawie " . $o . ", nie chcę zaśmiecać skrzynki.\n\nGdyby temat był kiedyś aktualny, wystarczy odpisać na tego maila albo zadzwonić: {moj_telefon}.\n\nPozdrawiam i powodzenia!"; };
    return array(
        'Salony beauty i fryzjerskie' => ask_templates($mk, $confirm, 'Krótkie pytanie do {firma}',
            "W salonie od razu widzimy kilka rzeczy, które mogą dziać się same: rezerwacje online, przypomnienia o wizycie dla klientów, zaproszenie na kolejną wizytę po kilku tygodniach i reklamy, które przyprowadzają klientów z okolicy.",
            'automatyzacji w salonie'),
        'Gabinety fizjoterapii' => ask_templates($mk, $confirm, 'Krótkie pytanie do {firma}',
            "W gabinecie od razu widzimy kilka rzeczy, które mogą dziać się same: zapisy online, przypomnienia o wizycie dla pacjentów, zaproszenie na kontrolę po zakończonej terapii i strona, z której pacjenci z okolicy łatwo Państwa znajdą.",
            'automatyzacji w gabinecie'),
        // A – pytanie wprost
        'Biura rachunkowe' => ask_templates($mk, $confirm, 'Krótkie pytanie do {firma}',
            "W biurze rachunkowym od razu widzimy kilka rzeczy, które mogą dziać się same: przypomnienia dla klientów o dokumentach, zbieranie faktur w jednym miejscu, przygotowanie danych pod KSeF i miesięczne podsumowania dla klientów.",
            'automatyzacji w biurze rachunkowym'),
        // B – pytanie o problem
        'Firmy remontowe i instalatorzy' => set3($mk, $confirm, 'Zapytania o wycenę w {firma}',
            "Dzień dobry,\n\nile zapytań o wycenę ucieka Państwu, bo telefon dzwoni akurat wtedy, kiedy jesteście na budowie?\n\nJestem {imie} z ELOK. Robimy dla firm automatyzacje, strony internetowe i marketing. U firm remontowych i instalatorów najczęściej widzimy do zrobienia: stronę z formularzem wyceny (klient opisuje zlecenie i dodaje zdjęcia, a Państwo dostają to od razu na telefon), przypomnienia o terminach dla klientów i faktury bez przepisywania.\n\nCzy mogę przesłać kilka zdań, jak to mogłoby wyglądać u Państwa i ile kosztuje? Wystarczy odpisać „tak”.\n\n" . $nie,
            "Dzień dobry,\n\nwracam krótko do pytania o zapytania o wycenę, które uciekają w trakcie pracy.\n\nCzy mogę przesłać kilka zdań, jak to mogłoby wyglądać u Państwa i ile kosztuje? Wystarczy odpisać „tak” albo „nie”.",
            $ost('automatyzacji i strony dla Państwa firmy')),
        // C – od przedsiębiorcy do przedsiębiorcy
        'Szkoły jazdy' => set3($mk, $confirm, 'Zapisy i przypomnienia w {firma}',
            "Dzień dobry,\n\nnazywam się {imie}. Sam prowadzę firmę usługową, warsztat samochodowy, i wiem, ile dnia zjadają telefony, terminy i przypominanie klientom.\n\nW ELOK robimy dla firm automatyzacje, strony internetowe i marketing. W szkole jazdy widzimy np.: zapisy na kurs przez stronę, przypomnienia o jazdach i płatnościach dla kursantów, grafik instruktorów w jednym miejscu i reklamy, które przyprowadzają kursantów z okolicy.\n\nCzy mogę przesłać kilka zdań, jak to mogłoby wyglądać u Państwa i ile kosztuje? Wystarczy odpisać „tak”.\n\n" . $nie,
            "Dzień dobry,\n\npisałem kilka dni temu jako przedsiębiorca do przedsiębiorcy, w sprawie zapisów online i przypomnień dla kursantów.\n\nCzy mogę przesłać kilka zdań, jak to mogłoby wyglądać u Państwa i ile kosztuje? Wystarczy odpisać „tak” albo „nie”.",
            $ost('automatyzacji w szkole jazdy')),
        // D – bardzo krótki
        'Stolarnie i meble na wymiar' => set3($mk, $confirm, 'Wyceny mebli',
            "Dzień dobry,\n\nczy wyceny mebli robią Państwo ręcznie, po kilku telefonach i zdjęciach od klienta?\n\nW ELOK robimy automatyzacje, strony internetowe i marketing. U Państwa zaczęlibyśmy od formularza wyceny na stronie (wymiary, zdjęcia i budżet prosto na telefon) i ofert wysyłanych do klienta automatycznie.\n\nPrzesłać kilka zdań, jak to mogłoby wyglądać i ile kosztuje? Wystarczy odpisać „tak” albo „nie”.",
            "Dzień dobry,\n\nkrótko wracam do wycen mebli. Przesłać kilka zdań? Wystarczy „tak” albo „nie”.",
            $ost('automatyzacji wycen mebli')),
    );
}
// Jednorazowo dodaje branże do testu 4 stylów (po 40 maili dziennie, razem z przypomnieniami).
// Salony i fizjoterapia dodają się wyłączone – można je włączyć w panelu.
function test4_migrate() {
    if (setting('test4_dodane') === '1') return;
    add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');
    $have = array(); foreach (all('SELECT nazwa FROM branze') as $b) $have[mb_strtolower($b['nazwa'])] = 1;
    $list = array('Biura rachunkowe' => 1, 'Firmy remontowe i instalatorzy' => 1, 'Szkoły jazdy' => 1, 'Stolarnie i meble na wymiar' => 1, 'Salony beauty i fryzjerskie' => 0, 'Gabinety fizjoterapii' => 0);
    foreach ($list as $n => $on) {
        if (isset($have[mb_strtolower($n)])) { if ($on) q('UPDATE branze SET aktywna=1, limit_dzienny=40 WHERE nazwa=?', array($n)); continue; }
        q('INSERT INTO branze(nazwa,aktywna,kolejnosc,limit_dzienny) VALUES(?,?,?,?)', array($n, $on, (int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze'), $on ? 40 : 0));
        $id = (int)db()->lastInsertId();
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie') as $t) save_template($id, $t, default_template_for($n, $t));
    }
    setting_set('test4_dodane', '1');
}
// Jednorazowo nadpisuje teksty maili w branżach testowych (wersja 2: automatyzacje + strony + marketing)
function test4_teksty2() {
    if (setting('test4_teksty2') === '1') return;
    foreach (array('Biura rachunkowe', 'Firmy remontowe i instalatorzy', 'Szkoły jazdy', 'Stolarnie i meble na wymiar', 'Salony beauty i fryzjerskie', 'Gabinety fizjoterapii') as $n) {
        $id = (int)val('SELECT id FROM branze WHERE nazwa=?', array($n));
        if ($id) foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2') as $t) save_template($id, $t, default_template_for($n, $t));
    }
    setting_set('test4_teksty2', '1');
}

// Jednorazowo dodaje branże: salony beauty, fizjoterapia, biura rachunkowe (z krótkimi mailami-pytaniami)
function gabinety_migrate() {
    if (setting('gabinety_dodane') === '1') return;
    $have = array(); foreach (all('SELECT nazwa FROM branze') as $b) $have[mb_strtolower($b['nazwa'])] = 1;
    foreach (array('Salony beauty i fryzjerskie', 'Gabinety fizjoterapii', 'Biura rachunkowe') as $n) {
        if (isset($have[mb_strtolower($n)])) continue;
        q('INSERT INTO branze(nazwa,aktywna,kolejnosc) VALUES(?,1,?)', array($n, (int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze')));
        $id = (int)db()->lastInsertId();
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie') as $t) save_template($id, $t, default_template_for($n, $t));
    }
    setting_set('gabinety_dodane', '1');
}
