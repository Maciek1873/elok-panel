# Moduł „Umowy” w panelu ELOK – opis do zbudowania

Cel: w panelu ELOK trzymam wszystkie umowy z klientami. Tworzę je z gotowych szablonów, wypełniam, mam ładny podgląd, wysyłam klientowi do podpisu online i nic mi nie ginie.

Stack jak cały panel: PHP 7.4 + MySQL + vanilla JS, hosting LH.pl. Bez natywnych okienek alert/confirm – tylko własne okna w stylu panelu. Bez emoji.

## Szablony (w załączonych plikach .md)

- `1-wdrozenie.md` – Umowa o wykonanie i wdrożenie oprogramowania (z Załącznikiem nr 1 Specyfikacja i nr 3 Zasady zawarcia online)
- `2-utrzymanie.md` – Umowa o świadczenie usług utrzymania (subskrypcja miesięczna)
- `3-protokol.md` – Protokół odbioru

Format szablonu: zwykły markdown (`##`, `###`, `**pogrubienie**`, listy `1.` z podpunktami wciętymi 4 spacjami, tabele `| a | b |`, `---` jako separator).

Pola do wypełnienia: `{{klucz|Etykieta|wartość domyślna}}`.
- Pola wbudowane wstawiają się same, bez wpisywania:
  - `{{firma.nazwa}}`, `{{firma.siedziba}}`, `{{firma.adres}}`, `{{firma.krs}}`, `{{firma.nip}}`, `{{firma.regon}}`, `{{firma.reprezentant}}`, `{{firma.email}}` – z Ustawień,
  - `{{klient.nazwa}}`, `{{klient.siedziba}}`, `{{klient.adres}}`, `{{klient.rejestr}}` (np. „KRS 0000…” albo „wpis do CEIDG”), `{{klient.nip}}`, `{{klient.reprezentant}}`, `{{klient.email}}`, `{{klient.kontakt}}` – z karty klienta,
  - `{{umowa.numer}}`, `{{umowa.data}}` – nadawane automatycznie.
- Pozostałe pola (np. `{{kwota|Wynagrodzenie (zł netto)|2000,00}}`) panel sam wykrywa w szablonie i robi z nich formularz z etykietą i wartością domyślną.
- Uwaga: pola mogą stać w komórkach tabel i zawierają znak `|` – podmieniać pola PRZED parsowaniem tabel.

## Co ma umieć moduł

**Klienci**
- lista, dodawanie, edycja: nazwa firmy, siedziba, adres, NIP, KRS/CEIDG, reprezentant (imię, nazwisko, funkcja), e-mail, telefon, osoba kontaktowa, notatki,
- karta klienta pokazuje wszystkie jego umowy i ma przycisk „Nowa umowa dla klienta”.

**Szablony**
- lista, edycja treści (pole tekstowe + podgląd na żywo + lista wykrytych pól), nowy szablon, duplikowanie,
- prefiks numeracji na szablon, np. `ELOK/W/001/2026`, `ELOK/S/001/2026`, `ELOK/P/001/2026`.

**Umowy**
- lista z filtrami statusu i wyszukiwarką; u góry liczniki: szkice, czeka na podpis, zawarte, suma abonamentów miesięcznie,
- nowa umowa: wybór klienta → wybór szablonu → opcjonalnie powiązanie z inną umową (utrzymanie i protokół wskazują umowę wdrożeniową),
- edytor: po lewej formularz pól, po prawej podgląd jak kartka A4 (ładny: nagłówek ELOK, czytelna typografia, wypełnione pola podświetlone w szkicu),
- treść szablonu kopiowana do umowy przy tworzeniu – późniejsza edycja szablonu nie zmienia istniejących umów,
- statusy: Szkic → Wysłana do podpisu → Zawarta; oraz Anulowana, Rozwiązana,
- historia zdarzeń przy każdej umowie (utworzona, wysłana, otwarta przez klienta, kod wysłany, podpisana, PDF wysłany itd.),
- PDF (np. dompdf, czcionka DejaVu Sans dla polskich znaków) ze stopką: numer umowy, strona X z Y,
- duplikowanie umowy, usuwanie szkicu, rozwiązanie umowy (data + powód).

## Podpis online (najważniejsze)

To forma dokumentowa (art. 77² KC) – wystarcza dla tych umów, bo kod zostaje u ELOK, a klient ma licencję niewyłączną. Musi dać się udowodnić, kto, kiedy i jaką treść zaakceptował.

1. „Wyślij do podpisu”: ELOK potwierdza swoją akceptację (imię, nazwisko, funkcja – domyślnie reprezentant z Ustawień). Panel zamraża treść: renderuje HTML, liczy SHA-256, zapisuje. Od tej chwili treści nie da się edytować. Zmiana = „Cofnij do szkicu”, a potem nowa wysyłka z nowym skrótem.
2. Klient dostaje e-mail z jednorazowym linkiem (losowy token, ważny 7 dni, ustawiane).
3. Strona podpisu (bez logowania): pełna treść, przycisk pobrania PDF, pola imię, nazwisko i funkcja, dwa osobne pola wyboru: „Jestem uprawniony do reprezentowania [firma]” i „Zapoznałem się z treścią umowy i ją akceptuję”. Przycisk aktywny dopiero po przewinięciu do końca.
4. „Wyślij kod” → 6-cyfrowy kod na e-mail klienta z umowy, ważny 15 minut, maksymalnie 5 prób; w bazie trzymać tylko hash kodu.
5. Po wpisaniu kodu: status „Zawarta”. Zapis: data i godzina, IP, user agent, e-mail, dane osoby, skrót SHA-256, numer wersji. Logów nie da się edytować ani usunąć z panelu.
6. Panel dokleja do PDF „Kartę akceptacji” (obie strony: osoba, funkcja, data i godzina, IP, e-mail, SHA-256) i wysyła PDF obu stronom.
7. Alternatywa: przy umowie można wgrać PDF podpisany kwalifikowanym podpisem → status „Zawarta”, plik zapisany bez zmian.
8. Przechowywanie umów, kart i logów co najmniej 6 lat, z kopią zapasową poza głównym serwerem.

## Ustawienia

Dane firmy: Carbonmiledesign sp. z o.o. (marka ELOK), siedziba, adres, KRS, NIP, REGON, reprezentant (imię, nazwisko, funkcja), e-mail do umów. Ważność linku do podpisu (dni). Adres nadawcy maili. Przycisk „Wyślij testowy e-mail”.

## Maile

PHP `mail()` (na LH.pl nie działa bezpośrednie SMTP), HTML w tabelach (pod Gmaila), jasne, krótkie, z przyciskiem „Przejdź do umowy”. Załącznik PDF przy zawartej umowie.

## Bezpieczeństwo

Moduł za logowaniem panelu. Strona podpisu tylko przez token. Pliki z umowami poza publicznym dostępem (np. folder z `.htaccess` deny, pobieranie przez skrypt).

Wzory umów to szablony przygotowane z AI – przed pierwszym użyciem do przejrzenia przez prawnika.
