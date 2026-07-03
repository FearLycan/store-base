#!/usr/bin/env node
// @ts-check
/**
 * AliExpress store link scraper.
 *
 * Podajesz TYLKO link do sklepu. Skrypt:
 *   1. otwiera przeglądarkę (widoczną — możesz ręcznie rozwiązać captcha, jeśli się pojawi),
 *   2. przechodzi na listę "wszystkie produkty" sklepu,
 *   3. przeklikuje całą paginację, zbierając linki produktów,
 *   4. normalizuje je do czystej postaci  https://www.aliexpress.com/item/<id>.html,
 *   5. zapisuje do pliku  <nazwa-sklepu>.txt  (jeden link w linii).
 *
 * Użycie:
 *   node scrape-store.mjs "https://www.aliexpress.com/store/1101234567"
 *
 * Opcje:
 *   --headless        uruchom bez widocznego okna (ryzyko captcha bez możliwości ręcznego rozwiązania)
 *   --out <katalog>   katalog na plik wynikowy (domyślnie ./output)
 *   --max-pages <n>   twardy limit stron paginacji (domyślnie 300)
 *   --page-timeout <ms>  ile czekać na produkty na stronie (domyślnie 45000)
 */

import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// ---------------------------------------------------------------------------
// Parsowanie argumentów
// ---------------------------------------------------------------------------
function parseArgs(argv) {
  const opts = {
    url: null,
    headless: false,
    out: path.join(__dirname, 'output'),
    maxPages: 300,
    pageTimeout: 45000,
  };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--headless') opts.headless = true;
    else if (a === '--out') opts.out = argv[++i];
    else if (a === '--max-pages') opts.maxPages = Number(argv[++i]) || opts.maxPages;
    else if (a === '--page-timeout') opts.pageTimeout = Number(argv[++i]) || opts.pageTimeout;
    else if (!a.startsWith('--') && !opts.url) opts.url = a;
  }
  return opts;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rand = (min, max) => min + Math.floor(Math.random() * (max - min + 1));

// ---------------------------------------------------------------------------
// Pomocnicze: URL sklepu -> id + host + adres listy "wszystkie produkty"
// ---------------------------------------------------------------------------
function resolveStore(rawUrl) {
  let u;
  try {
    u = new URL(rawUrl.trim());
  } catch {
    throw new Error(`Nieprawidłowy URL sklepu: "${rawUrl}"`);
  }
  if (!/aliexpress\./i.test(u.hostname)) {
    throw new Error(`To nie wygląda na adres AliExpress: ${u.hostname}`);
  }
  const idMatch = u.pathname.match(/\/store\/(\d{4,})/);
  const storeId = idMatch ? idMatch[1] : null;
  // Zachowaj host podany przez użytkownika (np. pl.aliexpress.com) jeśli to AE.
  const host = u.hostname;

  let allItems;
  if (/\/pages\/all-items/i.test(u.pathname)) {
    // Użytkownik podał już listę "wszystkie produkty" — użyj jej 1:1 (z parametrami!).
    allItems = rawUrl.trim();
  } else if (storeId) {
    // Zbuduj listę i dołóż domyślne sortowanie, którego oczekuje front sklepu.
    allItems = `${u.protocol}//${host}/store/${storeId}/pages/all-items.html?shop_sortType=bestmatch_sort`;
  } else {
    allItems = rawUrl.trim();
  }
  return { storeId, host, allItems, original: rawUrl.trim() };
}

function sanitizeFilename(name) {
  return (
    name
      .normalize('NFKD')
      .replace(/[^\w\s.-]+/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .slice(0, 80) || 'store'
  );
}

// Wyciąga id produktu z dowolnego linku AE i zwraca czysty adres.
function cleanItemUrl(href) {
  if (!href) return null;
  const m = href.match(/\/item\/(?:[\w-]+\/)?(\d{6,})\.html/);
  if (!m) return null;
  return `https://www.aliexpress.com/item/${m[1]}.html`;
}

// ---------------------------------------------------------------------------
// Wykrywanie przeszkód: captcha / blokada / strona logowania
// ---------------------------------------------------------------------------
// Zwraca 'captcha' | 'login' | null.
async function detectObstacle(page) {
  const url = page.url();
  if (/punish|_____tmd_____|x5secdata|captcha|nc_ret/i.test(url)) return 'captcha';
  if (/login\.aliexpress|passport\.aliexpress|\/login\.htm|acs\/login|\/ilogin\/|signin/i.test(url)) {
    return 'login';
  }
  return page
    .evaluate(() => {
      const html = document.body ? document.body.innerHTML : '';
      if (
        /punish|x5secdata|__baxia__|slidetounlock|nc_1_n1z|Please slide to verify|Przesuń, aby/i.test(html) ||
        document.querySelector('.nc-container, .baxia-dialog, #baxia-punish')
      ) {
        return 'captcha';
      }
      if (
        document.querySelector(
          '#fm-login-id, input[name="loginId"], .login-container, #batman-dialog, iframe#alibaba-login-box, iframe[src*="login"]',
        )
      ) {
        return 'login';
      }
      return null;
    })
    .catch(() => null);
}

async function hasItems(page) {
  return (await page.locator('a[href*="/item/"]').count().catch(() => 0)) > 0;
}

// Czeka aż użytkownik ręcznie usunie przeszkodę (captcha/logowanie) w oknie przeglądarki,
// po czym wraca na listę produktów. Poll do pojawienia się produktów.
async function waitForManualAction(page, obstacle, allItems, pageTimeout) {
  if (obstacle === 'login') {
    console.log('\n🔐 AliExpress wymaga logowania.');
    console.log('    Zaloguj się ręcznie w otwartym oknie przeglądarki.');
  } else {
    console.log('\n⚠️  Wykryto captcha / stronę blokady AliExpress.');
    console.log('    Rozwiąż ją ręcznie w otwartym oknie przeglądarki.');
  }
  console.log('    Sesja zapisze się w .userdata — kolejne uruchomienia jej nie wymagają.');
  console.log('    Czekam aż pojawi się lista produktów...\n');

  const deadline = Date.now() + Math.max(pageTimeout, 180000);
  let renavigated = false;
  while (Date.now() < deadline) {
    await sleep(2500);
    const still = await detectObstacle(page);
    if (still) continue; // wciąż captcha/login — czekaj dalej
    if (await hasItems(page)) return true;
    // Przeszkoda zniknęła, ale nie ma produktów (np. login zrzucił nas na inną stronę)
    // — jednorazowo wróć na listę produktów.
    if (!renavigated) {
      renavigated = true;
      await page.goto(allItems, { waitUntil: 'domcontentloaded' }).catch(() => {});
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    }
  }
  return false;
}

// page.evaluate odporny na przejściową nawigację/re-render AE ("Execution context
// was destroyed") — ponawia po krótkim odczekaniu zamiast wywalać cały skrypt.
async function safeEvaluate(page, fn, arg, tries = 3) {
  for (let i = 0; i < tries; i++) {
    try {
      return await page.evaluate(fn, arg);
    } catch (e) {
      const transient = /Execution context was destroyed|context was destroyed|navigation|Target closed|frame was detached/i.test(
        e.message || '',
      );
      if (transient && i < tries - 1) {
        await page.waitForLoadState('domcontentloaded').catch(() => {});
        await sleep(1000);
        continue;
      }
      throw e;
    }
  }
}

// ---------------------------------------------------------------------------
// Zbieranie linków z aktualnie wyświetlonej strony (z lazy-scrollem)
// ---------------------------------------------------------------------------
async function harvestCurrentPage(page) {
  // Powolne przewijanie, by doładować leniwie renderowane karty.
  await safeEvaluate(page, async () => {
    await new Promise((resolve) => {
      let y = 0;
      const step = 600;
      const timer = setInterval(() => {
        window.scrollBy(0, step);
        y += step;
        if (y >= document.body.scrollHeight - window.innerHeight - 200) {
          clearInterval(timer);
          setTimeout(resolve, 400);
        }
      }, 200);
    });
  });
  await sleep(rand(600, 1200));

  const hrefs = await safeEvaluate(page, () =>
    [...document.querySelectorAll('a[href*="/item/"]')].map((a) => a.getAttribute('href') || ''),
  );
  const ids = new Set();
  for (const h of hrefs) {
    const clean = h.startsWith('http') ? h : `https:${h.startsWith('//') ? '' : '//'}${h}`;
    const item = clean.match(/\/item\/(?:[\w-]+\/)?(\d{6,})\.html/);
    if (item) ids.add(item[1]);
  }
  return ids;
}

// ---------------------------------------------------------------------------
// Przejście do następnej strony paginacji. Zwraca true, jeśli udało się przejść.
// ---------------------------------------------------------------------------

// Odczyt stanu paginacji z kontenera AE: <div currentpage totalpage totalcount>.
async function readPagination(page) {
  return page
    .evaluate(() => {
      const el = document.querySelector('div[currentpage][totalpage]');
      if (!el) return null;
      const toInt = (v) => {
        const n = parseInt(v || '', 10);
        return Number.isFinite(n) ? n : null;
      };
      return {
        current: toInt(el.getAttribute('currentpage')),
        total: toInt(el.getAttribute('totalpage')),
        totalCount: toInt(el.getAttribute('totalcount')),
      };
    })
    .catch(() => null);
}

async function goToNextPage(page) {
  // 1) Paginacja AliExpress oparta na atrybutach (bezklasowe divy inline-style).
  const info = await readPagination(page);
  if (info && info.current != null && info.total != null) {
    if (info.current >= info.total) return false; // ostatnia strona
    const target = String(info.current + 1);

    // 1a) Kliknij komórkę z numerem = current+1 (zawsze widoczna obok bieżącej).
    const numClicked = await page
      .evaluate((t) => {
        const container = document.querySelector('div[currentpage][totalpage]');
        if (!container) return false;
        for (const div of Array.from(container.querySelectorAll('div'))) {
          if ((div.textContent || '').trim() === t) {
            const a = div.querySelector('a');
            (a || div).click(); // klik na najgłębszym elemencie z numerem
            return true;
          }
        }
        return false;
      }, target)
      .catch(() => false);
    if (numClicked) return true;

    // 1b) Fallback: strzałka "następna" = ostatni bezpośredni div z background-image.
    const arrowClicked = await page
      .evaluate(() => {
        const container = document.querySelector('div[currentpage][totalpage]');
        if (!container) return false;
        const kids = Array.from(container.children).filter((k) => k.tagName === 'DIV');
        const next = kids[kids.length - 1];
        if (next && /background-image/.test(next.getAttribute('style') || '')) {
          next.click();
          return true;
        }
        return false;
      })
      .catch(() => false);
    if (arrowClicked) return true;
  }

  // 2) Fallback dla innych layoutów: strzałka "następna" wg selektorów design-systemów AE.
  const nextSelectors = [
    'li.comet-pagination-next:not(.comet-pagination-disabled) > *',
    'button.comet-pagination-item-link[aria-label="Next Page"]',
    '[class*="pagination"] [class*="next"]:not([class*="disabled"])',
    'a[aria-label="Next"]:not([aria-disabled="true"])',
    'button[aria-label="Next"]:not([disabled])',
    '.next-pagination-item.next:not([disabled])',
  ];
  for (const sel of nextSelectors) {
    const el = page.locator(sel).first();
    if ((await el.count()) > 0 && (await el.isVisible().catch(() => false))) {
      const disabled = await el
        .evaluate((n) => {
          const li = n.closest('li') || n;
          return (
            n.hasAttribute('disabled') ||
            n.getAttribute('aria-disabled') === 'true' ||
            /disabled/.test(li.className || '')
          );
        })
        .catch(() => false);
      if (!disabled) {
        await el.scrollIntoViewIfNeeded().catch(() => {});
        await el.click({ timeout: 5000 }).catch(() => {});
        return true;
      }
    }
  }
  return false;
}

// Odczyt numeru aktualnie aktywnej strony (do wykrywania faktycznej zmiany).
async function activePageNumber(page) {
  return page
    .evaluate(() => {
      // Preferuj atrybut currentpage z kontenera AE.
      const cont = document.querySelector('div[currentpage][totalpage]');
      if (cont) {
        const n = parseInt(cont.getAttribute('currentpage') || '', 10);
        if (Number.isFinite(n)) return n;
      }
      const active = document.querySelector(
        '.comet-pagination-item-active, [class*="pagination"] [class*="active"], .next-current',
      );
      const n = active ? parseInt(active.textContent || '', 10) : NaN;
      return Number.isFinite(n) ? n : null;
    })
    .catch(() => null);
}

async function readStoreName(page, fallback) {
  const name = await page
    .evaluate(() => {
      const clean = (s) => (s || '').replace(/\s+/g, ' ').trim();
      // Odrzuć generyczne/za krótkie nazwy (np. sam "AliExpress" z <title>).
      const bad = (s) => !s || s.length < 2 || /^aliexpress$/i.test(s);

      // 1) Dedykowane elementy z nazwą sklepu.
      const sel = [
        '[class*="store-name"]',
        '[class*="storeName"]',
        '[class*="shop-name"]',
        '[class*="shopName"]',
        'a[href*="/store/"] [class*="name"]',
      ];
      for (const s of sel) {
        const el = document.querySelector(s);
        const v = el ? clean(el.textContent) : '';
        if (!bad(v)) return v;
      }

      // 2) og:title / <title>: "Nazwa Sklepu - AliExpress" — weź pierwszy nie-generyczny segment.
      const candidates = [];
      const og = document.querySelector('meta[property="og:title"]');
      if (og) candidates.push(og.getAttribute('content'));
      candidates.push(document.title);
      for (const c of candidates) {
        for (const part of clean(c).split(/[-|–—]/)) {
          const v = part.trim();
          if (!bad(v)) return v;
        }
      }
      return '';
    })
    .catch(() => '');
  return name || fallback;
}

// ---------------------------------------------------------------------------
// Główna logika
// ---------------------------------------------------------------------------
async function main() {
  const opts = parseArgs(process.argv.slice(2));
  if (!opts.url) {
    console.error('Użycie: node scrape-store.mjs "<link-do-sklepu-aliexpress>" [--headless] [--out <katalog>]');
    process.exit(1);
  }

  const store = resolveStore(opts.url);
  console.log(`Sklep: ${store.original}`);
  console.log(`Store ID: ${store.storeId ?? '(nie wykryto z URL — użyję podanej strony)'}`);
  console.log(`Lista produktów: ${store.allItems}`);

  const userDataDir = path.join(__dirname, '.userdata');
  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: opts.headless,
    viewport: { width: 1366, height: 900 },
    locale: 'pl-PL',
    userAgent:
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    args: ['--disable-blink-features=AutomationControlled'],
  });

  const page = context.pages()[0] || (await context.newPage());
  const allIds = new Set();

  try {
    await page.goto(store.allItems, { waitUntil: 'domcontentloaded', timeout: 60000 });
    // AE potrafi jednorazowo przeładować/re-renderować stronę po wejściu — daj się ustabilizować.
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

    // Captcha / logowanie? Daj szansę na ręczne rozwiązanie (headed).
    let obstacle = await detectObstacle(page);
    if (!obstacle && !(await hasItems(page))) {
      // Brak przeszkody i brak produktów — czasem to ukryta strona logowania; potraktuj jak login.
      obstacle = 'login';
    }
    if (obstacle) {
      if (opts.headless) {
        throw new Error(
          `Wykryto: ${obstacle}. Uruchom BEZ --headless i rozwiąż ręcznie (zaloguj się / przejdź captcha).`,
        );
      }
      const ok = await waitForManualAction(page, obstacle, store.allItems, opts.pageTimeout);
      if (!ok) {
        throw new Error('Nie udało się załadować listy produktów (captcha/logowanie nierozwiązane w czasie).');
      }
    }

    // Poczekaj na pierwsze produkty.
    await page
      .waitForSelector('a[href*="/item/"]', { timeout: opts.pageTimeout })
      .catch(() => console.log('⚠️  Nie znalazłem od razu linków produktów — próbuję dalej...'));

    const storeName = sanitizeFilename(
      await readStoreName(page, store.storeId ? `store-${store.storeId}` : 'store'),
    );

    let stallPages = 0;
    for (let p = 1; p <= opts.maxPages; p++) {
      const ob = await detectObstacle(page);
      if (ob) {
        await waitForManualAction(page, ob, store.allItems, opts.pageTimeout);
      }

      const before = allIds.size;
      const pageIds = await harvestCurrentPage(page);
      for (const id of pageIds) allIds.add(id);
      const added = allIds.size - before;
      console.log(`Strona ${p}: +${added} (łącznie ${allIds.size})`);

      // Zabezpieczenie: jeśli kilka stron z rzędu nic nie dodaje — kończymy.
      stallPages = added === 0 ? stallPages + 1 : 0;
      if (stallPages >= 3) {
        console.log('Brak nowych produktów przez 3 strony — kończę.');
        break;
      }

      const prevActive = await activePageNumber(page);
      const moved = await goToNextPage(page);
      if (!moved) {
        console.log('Brak przycisku "następna strona" — to była ostatnia strona.');
        break;
      }

      // Poczekaj aż numer strony się zmieni (currentpage) lub siatka się przeładuje.
      await page
        .waitForFunction(
          (prev) => {
            const cont = document.querySelector('div[currentpage][totalpage]');
            if (cont) {
              const n = parseInt(cont.getAttribute('currentpage') || '', 10);
              return prev == null || (Number.isFinite(n) && n !== prev);
            }
            const active = document.querySelector(
              '.comet-pagination-item-active, [class*="pagination"] [class*="active"], .next-current',
            );
            const n = active ? parseInt(active.textContent || '', 10) : null;
            return prev == null || n == null || n !== prev;
          },
          prevActive,
          { timeout: opts.pageTimeout },
        )
        .catch(() => {});
      await sleep(rand(1500, 3500)); // rate-limit między stronami
    }

    // Zapis wyniku
    await mkdir(opts.out, { recursive: true });
    const links = [...allIds].sort().map((id) => `https://www.aliexpress.com/item/${id}.html`);
    const outFile = path.join(opts.out, `${storeName}.txt`);
    await writeFile(outFile, links.join('\n') + (links.length ? '\n' : ''), 'utf8');

    console.log(`\n✅ Zapisano ${links.length} unikalnych linków do:\n   ${outFile}`);
  } finally {
    await context.close();
  }
}

main().catch((err) => {
  console.error('\n❌ Błąd:', err.message);
  process.exit(1);
});
