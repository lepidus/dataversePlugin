<?php

import('plugins.generic.dataverse.classes.exception.DataverseException');

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataverse.classes.DataEncryption');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfigurationDAO');
import('plugins.generic.dataverse.dataverseAPI.actions.DataverseCollectionActions');
import('plugins.generic.dataverse.settings.DefaultAdditionalInstructions');

class DataverseSettingsForm extends Form
{
    private $plugin;
    private $contextId;
    private const CONFIG_VARS = [
        'dataverseUrl' => 'string',
        'apiToken' => 'string',
        'termsOfUse' => 'object',
        'additionalInstructions' => 'object',
        'datasetPublish' => 'int'
    ];

    public function __construct(Plugin $plugin, int $contextId)
    {
        $encryption = new DataEncryption();
        $template = $encryption->secretConfigExists() ? 'dataverseConfigurationForm.tpl' : 'emptySecretKey.tpl';
        parent::__construct($plugin->getTemplateResource($template));

        $this->plugin = $plugin;
        $this->contextId = $contextId;

        $this->addCheck(new FormValidatorUrl(
            $this,
            'dataverseUrl',
            FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.dataverseUrlRequired'
        ));
        $this->addCheck(new FormValidator(
            $this,
            'apiToken',
            FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.tokenRequired'
        ));
        $this->addCheck(new FormValidatorCustom(
            $this,
            'apiToken',
            FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.dataverseUrlNotValid',
            [$this, 'validateConfiguration']
        ));
        $this->addCheck(new FormValidator(
            $this,
            'termsOfUse',
            FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.termsOfUseRequired'
        ));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));

        if (Application::get()->getName() === 'ojs2') {
            $this->addCheck(new FormValidator(
                $this,
                'datasetPublish',
                FORM_VALIDATOR_REQUIRED_VALUE,
                'plugins.generic.dataverse.settings.datasetPublishRequired'
            ));
        }
    }

    public function initData(): void
    {
        $configuration = $this->getStoredConfiguration();
        $configurationData = $configuration->getAllData();
        unset($configurationData['apiToken']);

        foreach ($configurationData as $name => $value) {
            $this->setData($name, $value);
        }

        $this->setData('additionalInstructions', $configuration->getAdditionalInstructions());
    }

    public function readInputData(): void
    {
        $this->readUserVars(array_keys(self::CONFIG_VARS));
        $this->setData('dataverseUrl', $this->normalizeURI($this->getData('dataverseUrl')));

        if (empty($this->getData('apiToken'))) {
            $this->setData('apiToken', $this->getStoredApiTokenForSameServer());
        }
    }

    private function getStoredApiTokenForSameServer(): string
    {
        $storedConfiguration = $this->getStoredConfiguration();
        if (empty($storedConfiguration->getData('dataverseUrl')) || empty($storedConfiguration->getData('apiToken'))) {
            return '';
        }

        $submittedConfiguration = new DataverseConfiguration();
        $submittedConfiguration->setDataverseUrl((string) $this->getData('dataverseUrl'));
        if (
            !$this->hasHttpsServer($submittedConfiguration)
            || !$this->hasHttpsServer($storedConfiguration)
            || $submittedConfiguration->getDataverseServerUrl() !== $storedConfiguration->getDataverseServerUrl()
        ) {
            return '';
        }

        return $storedConfiguration->getAPIToken();
    }

    private function hasHttpsServer(DataverseConfiguration $configuration): bool
    {
        return preg_match('/^https:\/\/[^\/]+\//', $configuration->getDataverseUrl()) === 1;
    }

    private function getStoredConfiguration(): DataverseConfiguration
    {
        return DAORegistry::getDAO('DataverseConfigurationDAO')->get($this->contextId);
    }

    private function normalizeURI(string $uri): string
    {
        return preg_replace("/\/+$/", '', $uri);
    }

    public function fetch($request, $template = null, $display = false)
    {
        $configuration = new DataverseConfiguration();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('application', Application::get()->getName());
        $templateMgr->assign('datasetPublishOptions', $configuration->getDatasetPublishOptions());
        $hasStoredApiToken = !empty($this->getStoredConfiguration()->getData('apiToken'));
        $templateMgr->assign('hasStoredApiToken', $hasStoredApiToken);
        if ($hasStoredApiToken) {
            $this->cssValidation['apiToken'] = [];
        }
        return parent::fetch($request);
    }

    public function execute(...$functionArgs)
    {
        $this->setDefaultAdditionalInstructions();
        $this->encryptApiToken();
        foreach (self::CONFIG_VARS as $configVar => $type) {
            $this->plugin->updateSetting($this->contextId, $configVar, $this->getData($configVar), $type);
        }
        parent::execute(...$functionArgs);
    }

    /**
     * Form::validate() runs every check, so validateConfiguration() would contact the
     * submitted server even when the POST or CSRF check fails. Reject those requests first.
     */
    public function validate($callHooks = true)
    {
        foreach ([new FormValidatorPost($this), new FormValidatorCSRF($this)] as $requestCheck) {
            if (!$requestCheck->isValid()) {
                $this->addError($requestCheck->getField(), $requestCheck->getMessage());
                $this->addErrorField($requestCheck->getField());
                return false;
            }
        }

        return parent::validate($callHooks);
    }

    public function validateConfiguration(): bool
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl($this->getData('dataverseUrl'));
        $configuration->setApiToken($this->getData('apiToken'));

        $dataverseCollectionActions = new DataverseCollectionActions($configuration);

        try {
            $dataverseCollectionActions->get();
        } catch (DataverseException $e) {
            $message = __('plugins.generic.dataverse.settings.dataverseUrlNotValid', ['msg' => $e->getMessage()]);
            error_log("Dataverse Error - $message");
            $this->addError('apiToken', $message);

            return false;
        }

        return true;
    }

    private function setDefaultAdditionalInstructions(): void
    {
        $defaultAdditionalInstructions = new DefaultAdditionalInstructions();
        $defaultInstructions = $defaultAdditionalInstructions->getDefaultInstructions();

        $additionalInstructions = $this->getData('additionalInstructions');
        foreach ($defaultInstructions as $locale => $instructions) {
            if (empty($additionalInstructions[$locale])) {
                $additionalInstructions[$locale] = $instructions;
            }
        }

        $this->setData('additionalInstructions', $additionalInstructions);
    }

    private function encryptApiToken(): void
    {
        $encryption = new DataEncryption();
        $apiToken = $this->getData('apiToken');

        if (!$encryption->textIsEncrypted($apiToken)) {
            $encryptedToken = $encryption->encryptString($apiToken);
            $this->setData('apiToken', $encryptedToken);
        }
    }
}
