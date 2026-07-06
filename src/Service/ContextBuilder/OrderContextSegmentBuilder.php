<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Address;
use Carrier;
use Order;
use State;
use Tools;
use Validate;
use Country;
use Context;

class OrderContextSegmentBuilder
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<string, mixed> $state
     * @param array<string, mixed> $oldState
     *
     * @return array<string, mixed>
     */
    public function build(?Order $order, int $idLang, array $products, array $state = [], array $oldState = []): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return [
                'id' => 0,
                'reference' => '',
                'total' => 0.0,
                'date' => '',
                'formated_date' => '',
                'status' => '',
                'old_status' => '',
                'payment_method' => '',
                'shipping_method' => '',
                'state' => $this->normalizeState($state),
                'old_state' => $this->normalizeState($oldState),
                'totals' => $this->getEmptyTotals(),
                'billing_address' => $this->getEmptyAddressContext(),
                'shipping_address' => $this->getEmptyAddressContext(),
                'shipping' => $this->getEmptyShippingContext(),
                'products' => $products,
            ];
        }

        $shipping = $this->getShippingContext($order);

        return [
            'id' => (int) $order->id,
            'reference' => (string) $order->reference,
            'total' => (float) $order->total_paid,
            'date' => (string) $order->date_add,
            'formated_date' => (string) Tools::displayDate((string) $order->date_add, $idLang, true),
            'status' => (string) ($state['name'] ?? ''),
            'old_status' => (string) ($oldState['name'] ?? ''),
            'payment_method' => (string) ($order->payment ?? ''),
            'shipping_method' => (string) ($shipping['carrier_name'] ?? ''),
            'state' => $this->normalizeState($state),
            'old_state' => $this->normalizeState($oldState),
            'totals' => $this->getTotals($order),
            'billing_address' => $this->getAddressContext($order, 'id_address_invoice'),
            'shipping_address' => $this->getAddressContext($order, 'id_address_delivery'),
            'shipping' => $shipping,
            'products' => $products,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function getTotals(Order $order): array
    {
        return [
            'paid' => (float) $order->total_paid,
            'products' => (float) $order->total_products_wt,
            'shipping' => (float) $order->total_shipping_tax_incl,
            'discounts' => (float) $order->total_discounts_tax_incl,
            'tax' => (float) $order->total_paid_tax_incl - (float) $order->total_paid_tax_excl,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function getEmptyTotals(): array
    {
        return [
            'paid' => 0.0,
            'products' => 0.0,
            'shipping' => 0.0,
            'discounts' => 0.0,
            'tax' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeState(array $state): array
    {
        return [
            'id' => (int) ($state['id'] ?? 0),
            'key' => (string) ($state['key'] ?? ''),
            'name' => (string) ($state['name'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAddressContext(Order $order, string $property): array
    {
        if (empty($order->{$property})) {
            return $this->getEmptyAddressContext();
        }

        $address = new Address((int) $order->{$property});
        if (!Validate::isLoadedObject($address)) {
            return $this->getEmptyAddressContext();
        }

        $countryName = '';
        if (!empty($address->id_country)) {
            $country = new Country((int) $address->id_country, (int) $this->context->language->id);
            if (Validate::isLoadedObject($country)) {
                $countryName = (string) $country->name;
            }
        }

        $stateName = '';
        if (!empty($address->id_state)) {
            $state = new State((int) $address->id_state);
            if (Validate::isLoadedObject($state)) {
                $stateName = (string) $state->name;
            }
        }

        $fullName = trim((string) $address->firstname . ' ' . (string) $address->lastname);
        $lines = array_filter([
            $fullName,
            (string) $address->company,
            trim((string) $address->address1),
            trim((string) $address->address2),
            trim((string) $address->city . ' ' . (string) $address->postcode),
            $stateName,
            $countryName,
        ]);

        return [
            'firstname' => (string) $address->firstname,
            'lastname' => (string) $address->lastname,
            'full_name' => $fullName,
            'company' => (string) $address->company,
            'address1' => (string) $address->address1,
            'address2' => (string) $address->address2,
            'city' => (string) $address->city,
            'postcode' => (string) $address->postcode,
            'country' => $countryName,
            'state' => $stateName,
            'phone' => (string) $address->phone,
            'phone_mobile' => (string) $address->phone_mobile,
            'formatted' => implode("\n", $lines),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getEmptyAddressContext(): array
    {
        return [
            'firstname' => '',
            'lastname' => '',
            'full_name' => '',
            'company' => '',
            'address1' => '',
            'address2' => '',
            'city' => '',
            'postcode' => '',
            'country' => '',
            'state' => '',
            'phone' => '',
            'phone_mobile' => '',
            'formatted' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getShippingContext(Order $order): array
    {
        $carrierName = '';
        if (!empty($order->id_carrier)) {
            $carrier = new Carrier((int) $order->id_carrier);
            if (Validate::isLoadedObject($carrier)) {
                $carrierName = (string) $carrier->name;
            }
        }

        return [
            'carrier_name' => $carrierName,
            'cost' => (float) $order->total_shipping_tax_incl,
            'tracking_url' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getEmptyShippingContext(): array
    {
        return [
            'carrier_name' => '',
            'cost' => 0.0,
            'tracking_url' => '',
        ];
    }
}
