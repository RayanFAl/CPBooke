<?php

namespace App\Modules\Admin\Content\Http\Requests\Concerns;

use App\Modules\Content\Support\ContentPageCatalog;

trait NormalizesContentPagePayload
{
    protected function prepareForValidation(): void
    {
        $category = is_string($this->category) ? trim($this->category) : $this->category;
        $product = is_string($this->product) ? trim($this->product) : $this->product;

        if ($category !== ContentPageCatalog::CATEGORY_PRODUCT_POLICY || $product === '') {
            $product = null;
        }

        $url = is_string($this->url) ? trim($this->url) : $this->url;
        $slug = is_string($this->slug) ? strtolower(trim($this->slug)) : $this->slug;

        if ($category === ContentPageCatalog::CATEGORY_PRODUCT_POLICY && is_string($product) && $product !== '') {
            $slug = ContentPageCatalog::slugForProduct($product) ?? $slug;
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'slug' => $slug,
            'category' => $category,
            'product' => $product,
            'url' => $url === '' ? null : $url,
        ]);
    }
}
