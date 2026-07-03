<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Customer;
use Validate;

class CustomerContextSegmentBuilder
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public function build(?Customer $customer, array $overrides = []): array
    {
        $isLoadedCustomer = $customer instanceof Customer && Validate::isLoadedObject($customer);

        return array_merge([
            'id' => $isLoadedCustomer ? (int) $customer->id : 0,
            'name' => $isLoadedCustomer ? trim((string) $customer->firstname . ' ' . (string) $customer->lastname) : '',
            'firstname' => $isLoadedCustomer ? (string) $customer->firstname : '',
            'lastname' => $isLoadedCustomer ? (string) $customer->lastname : '',
            'email' => $isLoadedCustomer ? (string) $customer->email : '',
        ], $overrides);
    }
}
