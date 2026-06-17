<?php

namespace Velox\MailSendVx\Form;

use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

class ConfigurationFormDataProvider implements FormDataProviderInterface
{
    /**
     * @var ConfigurationDataConfiguration
     */
    private $dataConfiguration;

    public function __construct(ConfigurationDataConfiguration $dataConfiguration)
    {
        $this->dataConfiguration = $dataConfiguration;
    }

    public function getData()
    {
        return [
            'mailsendvx_configuration' => $this->dataConfiguration->getConfiguration(),
        ];
    }

    public function setData(array $data)
    {
        return $this->dataConfiguration->updateConfiguration($data['mailsendvx_configuration'] ?? []);
    }
}
