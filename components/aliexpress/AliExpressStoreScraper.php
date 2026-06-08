<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use app\models\Store;

/**
 * Enumerates a store's product catalogue via the mtop gateway, paging until exhausted.
 * Returns product stubs ['external_id', 'title', 'image'] for the importer to enrich.
 *
 * NOTE (live-verification point — plan Task 14): the exact mtop api id + request payload +
 * response shape for store product listing must be confirmed against a real store page's
 * network calls. STORE_PRODUCTS_API below is the current best guess; the extract* helpers use
 * tolerant recursive search so they survive minor shape changes. If fetchProductStubs returns
 * empty for a known-good store, inspect the live JSON and adjust STORE_PRODUCTS_API / payload.
 */
final class AliExpressStoreScraper
{
    private const STORE_PRODUCTS_API = 'mtop.aliexpress.seller.product.list';

    public function __construct(private readonly AliExpressMtopSession $session = new AliExpressMtopSession())
    {
    }

    /**
     * @return array<int, array{external_id:string, title:?string, image:?string}>
     */
    public function fetchProductStubs(Store $store, int $maxPages = 50, int $pageSize = 20): array
    {
        $this->session->bootstrapForStore($store->url);

        $stubs = [];
        $seen = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $decoded = $this->session->call(self::STORE_PRODUCTS_API, [
                'sellerAdminSeq' => (string)($store->seller_admin_seq ?? ''),
                'storeId'        => $store->external_store_id,
                'page'           => $page,
                'pageSize'       => $pageSize,
                'clientType'     => 'pc',
            ]);

            $items = $this->extractItems($decoded);
            if ($items === []) {
                break;
            }

            $newOnPage = 0;
            foreach ($items as $item) {
                $externalId = $this->extractExternalId($item);
                if ($externalId === null || isset($seen[$externalId])) {
                    continue;
                }
                $seen[$externalId] = true;
                $newOnPage++;
                $stubs[] = [
                    'external_id' => $externalId,
                    'title'       => $this->extractTitle($item),
                    'image'       => $this->extractImage($item),
                ];
            }

            if (count($items) < $pageSize || $newOnPage === 0) {
                break; // last page (or no progress)
            }

            sleep(random_int(2, 4)); // rate limit between pages
        }

        return $stubs;
    }

    /**
     * Recursively locate the products list: the first array whose entries look like product maps
     * (contain a product-id-ish key).
     */
    private function extractItems(array $decoded): array
    {
        $list = $this->findProductList($decoded);

        return $list ?? [];
    }

    private function findProductList(array $node): ?array
    {
        // Is this node itself a list of product-like maps?
        if (array_is_list($node) && $node !== []) {
            $looksLikeProducts = true;
            foreach ($node as $entry) {
                if (!is_array($entry) || $this->extractExternalId($entry) === null) {
                    $looksLikeProducts = false;
                    break;
                }
            }
            if ($looksLikeProducts) {
                return $node;
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = $this->findProductList($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function extractExternalId(array $item): ?string
    {
        foreach (['productId', 'product_id', 'itemId', 'item_id', 'id'] as $key) {
            if (!isset($item[$key]) || !is_scalar($item[$key])) {
                continue;
            }
            $candidate = trim((string)$item[$key]);
            if (preg_match('~^\d{6,}$~', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractTitle(array $item): ?string
    {
        foreach (['subject', 'title', 'name', 'productTitle'] as $key) {
            if (isset($item[$key]) && is_scalar($item[$key])) {
                $value = trim((string)$item[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractImage(array $item): ?string
    {
        foreach (['image', 'imageUrl', 'productImage', 'imgUrl', 'mainImage'] as $key) {
            if (!isset($item[$key]) || !is_scalar($item[$key])) {
                continue;
            }
            $value = trim((string)$item[$key]);
            if ($value === '') {
                continue;
            }
            if (str_starts_with($value, '//')) {
                return 'https:' . $value;
            }

            return $value;
        }

        return null;
    }
}
