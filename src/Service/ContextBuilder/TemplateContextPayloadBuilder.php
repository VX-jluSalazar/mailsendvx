<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

class TemplateContextPayloadBuilder
{
    /**
     * @var array<string, mixed>
     */
    private $payload = [];

    /**
     * @param array<string, mixed> $event
     */
    public function withEvent(array $event): self
    {
        $this->payload['event'] = $event;

        return $this;
    }

    /**
     * @param array<string, mixed> $shop
     */
    public function withShop(array $shop): self
    {
        $this->payload['shop'] = $shop;

        return $this;
    }

    /**
     * @param array<string, mixed> $customer
     */
    public function withCustomer(array $customer): self
    {
        $this->payload['customer'] = $customer;

        return $this;
    }

    /**
     * @param array<string, mixed> $cart
     */
    public function withCart(array $cart): self
    {
        $this->payload['cart'] = $cart;

        return $this;
    }

    /**
     * @param array<string, mixed> $order
     */
    public function withOrder(array $order): self
    {
        $this->payload['order'] = $order;

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $relatedProducts
     */
    public function withRelatedProducts(array $relatedProducts): self
    {
        $this->payload['related_products'] = $relatedProducts;

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $reviews
     */
    public function withReviews(array $reviews): self
    {
        $this->payload['reviews'] = $reviews;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return $this->payload;
    }
}
