## UMOWA NR {{umowa.numer}} O ŚWIADCZENIE USŁUG UTRZYMANIA OPROGRAMOWANIA

zawarta w dniu {{umowa.data}} pomiędzy:

**{{firma.nazwa}}**, działającą pod marką ELOK, z siedzibą w {{firma.siedziba}}, adres: {{firma.adres}}, KRS {{firma.krs}}, NIP {{firma.nip}}, REGON {{firma.regon}}, reprezentowaną przez {{firma.reprezentant}}, zwaną dalej **„Wykonawcą”**,

a

**{{klient.nazwa}}**, z siedzibą w {{klient.siedziba}}, adres: {{klient.adres}}, {{klient.rejestr}}, NIP {{klient.nip}}, reprezentowaną przez {{klient.reprezentant}}, zwaną dalej **„Zamawiającym”**.

Zamawiający oświadcza, że zawiera Umowę w związku z prowadzoną działalnością gospodarczą i ma ona dla niego charakter zawodowy.

### § 1. Definicje

Pojęcia pisane wielką literą, niezdefiniowane poniżej, mają znaczenie nadane im w Umowie nr {{umowa.powiazana|Numer umowy wdrożeniowej|}} o wykonanie i wdrożenie oprogramowania („Umowa wdrożeniowa”).

1. **Usługi** – usługi utrzymania Aplikacji opisane w § 2.
2. **Okres rozliczeniowy** – miesiąc kalendarzowy.
3. **Awaria** – sytuacja, w której Aplikacja nie działa albo nie wykonuje kluczowej funkcji wymienionej w Specyfikacji.
4. **Zgłoszenie** – wiadomość e-mail Zamawiającego na adres {{firma.email}} z opisem problemu.
5. **Godziny wsparcia** – Dni robocze w godzinach {{godziny|Godziny wsparcia|9:00–17:00}}.

### § 2. Zakres Usług

1. W ramach abonamentu Wykonawca świadczy:
    1. utrzymanie Aplikacji na serwerze Wykonawcy wraz z adresem dostępowym,
    2. kopie zapasowe bazy danych Aplikacji wykonywane nie rzadziej niż raz w tygodniu i przechowywane przez 14 dni,
    3. monitorowanie działania Aplikacji i usuwanie Błędów,
    4. aktualizacje bezpieczeństwa i aktualizacje techniczne Aplikacji,
    5. dostosowanie Aplikacji do zmian po stronie Źródeł danych lub platformy sklepowej w wymiarze do {{godziny_zmian|Limit godzin na dostosowania (miesięcznie)|2}} godzin pracy w Okresie rozliczeniowym; niewykorzystane godziny nie przechodzą na kolejne okresy,
    6. aktualizację cen, dostępności i nowych produktów uruchamianą przez Zamawiającego w panelu albo automatycznie z częstotliwością ustaloną przez Strony, nie częściej niż {{czestotliwosc|Częstotliwość aktualizacji|raz w miesiącu}},
    7. generowanie opisów nowych produktów do {{limit_opisow|Limit opisów miesięcznie|500}} produktów w Okresie rozliczeniowym,
    8. wsparcie e-mailowe w Godzinach wsparcia.
2. Usługi nie obejmują: nowych funkcji, prac powyżej limitów z ust. 1, przebudowy Aplikacji w razie zmiany platformy Sklepu lub zasadniczej zmiany Źródła danych, dodania nowych Źródeł danych, usuwania skutków działań Zamawiającego lub osób trzecich. Takie prace Wykonawca wykonuje po akceptacji wyceny w formie dokumentowej, według stawki {{stawka_h|Stawka za prace dodatkowe (zł netto/h)|150}} zł netto za godzinę.
3. Do Umowy stosuje się odpowiednio przepisy o umowie zlecenia (art. 750 Kodeksu cywilnego). Wykonawca zobowiązuje się do starannego działania i nie gwarantuje nieprzerwanego działania Aplikacji, Źródeł danych ani Sklepu.

### § 3. Czasy reakcji

| Rodzaj zgłoszenia | Reakcja Wykonawcy | Cel usunięcia |
| --- | --- | --- |
| Awaria | do 8 godzin w Godzinach wsparcia | do 2 Dni roboczych |
| Błąd zwykły | do 2 Dni roboczych | w kolejnej aktualizacji, nie później niż w 10 Dni roboczych |
| Pytanie lub konsultacja | do 2 Dni roboczych | nie dotyczy |

1. Czas liczy się od otrzymania Zgłoszenia w Godzinach wsparcia. Zgłoszenie otrzymane poza nimi uważa się za otrzymane o początku najbliższych Godzin wsparcia.
2. Jeżeli usunięcie problemu zależy od podmiotu trzeciego (Źródło danych, platforma sklepowa, dostawca hostingu), czas usunięcia biegnie od przywrócenia działania usługi tego podmiotu.
3. Prace planowane, które mogą przerwać działanie Aplikacji, Wykonawca zapowiada e-mailem z wyprzedzeniem co najmniej 24 godzin i wykonuje w miarę możliwości poza Godzinami wsparcia.

### § 4. Wynagrodzenie

1. Za Usługi Zamawiający płaci abonament w wysokości {{abonament|Abonament (zł netto/mies.)|40,00}} zł netto miesięcznie, powiększony o podatek VAT według obowiązującej stawki.
2. Abonament jest naliczany od {{start_abonamentu|Początek naliczania|pierwszego dnia miesiąca następującego po zakończeniu Okresu poprawek z Umowy wdrożeniowej}} i płatny z góry, na podstawie faktury wystawianej na początku Okresu rozliczeniowego, w terminie {{platnosc_dni|Termin płatności (dni)|7}} dni od jej wystawienia. Faktury wystawiane są w formie elektronicznej, w tym za pośrednictwem Krajowego Systemu e-Faktur, jeżeli wymagają tego przepisy.
3. Prace dodatkowe z § 2 ust. 2 są rozliczane po ich wykonaniu, na podstawie odrębnej faktury.
4. Wykonawca może zmienić wysokość abonamentu nie częściej niż raz na 12 miesięcy, zawiadamiając Zamawiającego e-mailem co najmniej 30 dni przed zmianą. Zamawiający może wtedy wypowiedzieć Umowę ze skutkiem na dzień wejścia zmiany w życie.
5. W razie opóźnienia w płatności Wykonawcy przysługują odsetki ustawowe za opóźnienie w transakcjach handlowych oraz rekompensata za koszty odzyskiwania należności, na zasadach ustawy z dnia 8 marca 2013 r. o przeciwdziałaniu nadmiernym opóźnieniom w transakcjach handlowych.

### § 5. Zawieszenie Usług

1. Jeżeli Zamawiający zalega z zapłatą ponad 14 dni, Wykonawca może, po uprzedzeniu e-mailem i upływie dodatkowych 7 dni, zawiesić świadczenie Usług oraz dostęp do Aplikacji.
2. W czasie zawieszenia Wykonawca przechowuje dane Zamawiającego. Usługi i dostęp są przywracane w ciągu 2 Dni roboczych od zapłaty zaległości.
3. Zawieszenie nie zwalnia Zamawiającego z obowiązku zapłaty abonamentu.

### § 6. Obowiązki Zamawiającego

1. Zamawiający zgłasza problemy wyłącznie w formie Zgłoszenia, z opisem pozwalającym je odtworzyć.
2. Zamawiający utrzymuje ważny dostęp do Sklepu potrzebny do działania Aplikacji i informuje Wykonawcę z wyprzedzeniem o planowanych zmianach w Sklepie, które mogą wpłynąć na Aplikację.
3. Zamawiający nie ingeruje w kod Aplikacji ani w jej serwer.

### § 7. Odpowiedzialność

1. Całkowita odpowiedzialność Wykonawcy z tytułu Umowy jest ograniczona do sumy abonamentów netto zapłaconych przez Zamawiającego w ostatnich 12 miesiącach przed zdarzeniem powodującym szkodę.
2. Wykonawca nie odpowiada za utracone korzyści Zamawiającego ani za przerwy i błędy spowodowane przez Źródła danych, Sklep, platformę sklepową, dostawców internetu lub hostingu albo osoby trzecie.
3. W razie utraty danych obowiązek Wykonawcy ogranicza się do odtworzenia danych z ostatniej kopii zapasowej.
4. Ograniczenia z ust. 1–3 nie dotyczą szkody wyrządzonej umyślnie.

### § 8. Powiązanie z Umową wdrożeniową

1. Do Usług stosuje się odpowiednio postanowienia Umowy wdrożeniowej dotyczące Źródeł danych i treści w Sklepie (§ 4), praw do Aplikacji i licencji (§ 8), poufności (§ 11) oraz danych osobowych (§ 12).
2. Wygaśnięcie Umowy wdrożeniowej w wyniku odstąpienia przed Odbiorem powoduje wygaśnięcie niniejszej Umowy.

### § 9. Czas trwania i rozwiązanie

1. Umowa zostaje zawarta na czas nieokreślony.
2. Każda Strona może wypowiedzieć Umowę z zachowaniem {{wypowiedzenie|Okres wypowiedzenia|jednomiesięcznego}} okresu wypowiedzenia, ze skutkiem na koniec miesiąca kalendarzowego, w formie dokumentowej.
3. Wykonawca może wypowiedzieć Umowę bez zachowania okresu wypowiedzenia, jeżeli Zamawiający zalega z zapłatą ponad 30 dni albo narusza warunki licencji z Umowy wdrożeniowej.
4. Zamawiający może wypowiedzieć Umowę bez zachowania okresu wypowiedzenia, jeżeli Wykonawca w sposób rażący narusza Umowę i nie usuwa naruszenia w terminie 14 dni od wezwania w formie dokumentowej.
5. Po rozwiązaniu Umowy Wykonawca w terminie 14 dni przekaże Zamawiającemu eksport jego danych w pliku CSV, a następnie po upływie 30 dni usunie dane Zamawiającego z serwera. Dostęp do Aplikacji kończy się z dniem rozwiązania Umowy, zgodnie z § 8 ust. 6 Umowy wdrożeniowej.

### § 10. Postanowienia końcowe

1. Bieżąca komunikacja odbywa się e-mailem na adresy: Wykonawca – {{firma.email}}, Zamawiający – {{klient.email}}.
2. Zmiany Umowy wymagają formy dokumentowej pod rygorem nieważności, z wyjątkiem zmiany abonamentu w trybie § 4 ust. 4.
3. W sprawach nieuregulowanych stosuje się prawo polskie, w szczególności Kodeks cywilny.
4. Spory rozstrzyga sąd powszechny właściwy dla siedziby Wykonawcy.
5. Umowa zostaje zawarta z chwilą jej podpisania przez obie Strony kwalifikowanym podpisem elektronicznym albo z chwilą jej akceptacji przez obie Strony w panelu Wykonawcy na zasadach określonych w Załączniku nr 3 do Umowy wdrożeniowej, które stosuje się odpowiednio. Każda Strona otrzymuje plik PDF z treścią Umowy.
