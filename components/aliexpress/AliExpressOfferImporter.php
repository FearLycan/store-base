<?php

namespace app\common\components\aliexpress;

use common\models\Set;
use common\models\SetOffer;
use common\models\Store;
use RuntimeException;

final class AliExpressOfferImporter
{
    public function __construct(
        private readonly AliExpressLinkResolver $linkResolver = new AliExpressLinkResolver(),
        private readonly AliExpressApiClient    $apiClient = new AliExpressApiClient(),
    )
    {
    }

    public function importByUrl(Set $set, string $inputUrl): SetOffer
    {
        $resolvedUrl = $this->linkResolver->resolve($inputUrl);
        if ($resolvedUrl === '') {
            throw new RuntimeException('Invalid AliExpress URL.');
        }

        $itemId = $this->linkResolver->extractItemId($resolvedUrl);
        if ($itemId === null) {
            throw new RuntimeException('Could not extract product ID from URL.');
        }

        $product = $this->apiClient->fetchProductByItemId($itemId);

        $store = Store::getOrCreate('ALIEXPRESS', 'AliExpress');
        if (($store->url === null || $store->url === '') && preg_match('~^https?://[^/]+~i', $resolvedUrl, $matches) === 1) {
            $store->url = $matches[0];
            $store->save(false, ['url']);
        }

        $offer = SetOffer::findOne([
            'set_id'      => $set->id,
            'store_id'    => $store->id,
            'external_id' => (string)$itemId,
        ]);

        if (!$offer) {
            $offer = new SetOffer();
            $offer->set_id = $set->id;
            $offer->store_id = $store->id;
            $offer->external_id = (string)$itemId;
        }

        $offer->name = $set->name;
        $offer->url = $product['url'] ?? $resolvedUrl;
        $offer->image = $product['image'] ?? $set->getDisplayMainImageUrl();
        $offer->currency_code = strtoupper((string)($product['currency_code'] ?? 'USD'));
        if ($offer->currency_code === '') {
            $offer->currency_code = 'USD';
        }
        $offer->price = isset($product['price_cents']) && is_numeric($product['price_cents']) ? (int)$product['price_cents'] : null;
        $offer->availability = $product['availability'] ?? null;
        $offer->rating_value = isset($product['rating_value']) && is_numeric($product['rating_value']) ? (float)$product['rating_value'] : null;
        $offer->rating_scale_max = isset($product['rating_scale_max']) && is_numeric($product['rating_scale_max']) ? (float)$product['rating_scale_max'] : null;
        $offer->review_count = isset($product['review_count']) && is_numeric($product['review_count']) ? (int)$product['review_count'] : 0;
        $offer->source = 'aliexpress_api';
        $offer->is_manual_override = 0;
        $offer->synced_at = date('Y-m-d H:i:s');

        if (!$offer->save()) {
            $errorSummary = implode('; ', $offer->getFirstErrors());
            throw new RuntimeException('Failed to save offer: ' . $errorSummary);
        }

        return $offer;
    }
}
