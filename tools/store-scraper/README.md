# AliExpress store link scraper

Samodzielne narzędzie (Playwright / Node). Podajesz **tylko link do sklepu**, a skrypt
przeklikuje całą paginację i zapisuje **czyste linki do produktów** do pliku `.txt`
nazwanego jak sklep.

## Instalacja (raz)

```bash
cd tools/store-scraper
npm install          # pobiera playwright + chromium (postinstall)
```

## Użycie

```bash
node scrape-store.mjs "https://www.aliexpress.com/store/1101234567"
```

Wynik: `output/<nazwa-sklepu>.txt`, jeden link w linii:

```
https://www.aliexpress.com/item/1005006123456789.html
https://www.aliexpress.com/item/1005006987654321.html
```

## Opcje

| Opcja | Opis |
|-------|------|
| `--headless` | uruchom bez okna (nie zalecane — captcha nie do rozwiązania ręcznie) |
| `--out <katalog>` | katalog na plik wynikowy (domyślnie `./output`) |
| `--max-pages <n>` | limit stron paginacji (domyślnie 300) |
| `--page-timeout <ms>` | ile czekać na produkty na stronie (domyślnie 45000) |

## Captcha

AliExpress często pokazuje captcha / stronę blokady. Dlatego domyślnie okno jest
**widoczne** — gdy skrypt wykryje blokadę, wypisze komunikat i poczeka, aż rozwiążesz
ją ręcznie w oknie przeglądarki. Sesja jest zapamiętywana w `.userdata/`, więc kolejne
uruchomienia zwykle nie wymagają ponownej weryfikacji.

## Uwagi

- Linki są normalizowane do postaci `https://www.aliexpress.com/item/<id>.html`
  (bez parametrów śledzących) i deduplikowane.
- `node_modules/`, `.userdata/` i `output/` są ignorowane przez git.
```
