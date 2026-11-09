<?php

namespace APP\plugins\generic\dataverse;

use PKP\form\Form;
use PKP\plugins\Plugin;
use APP\core\Application;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\validation\FormValidatorUrl;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use APP\plugins\generic\dataverse\classes\DataEncryption;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DefaultAdditionalInstructions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;

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
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.dataverseUrlRequired'
        ));
        $this->addCheck(new FormValidator(
            $this,
            'apiToken',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.tokenRequired'
        ));
        $this->addCheck(new FormValidatorCustom(
            $this,
            'apiToken',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.dataverseUrlNotValid',
            [$this, 'validateConfiguration']
        ));
        $this->addCheck(new FormValidator(
            $this,
            'termsOfUse',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.termsOfUseRequired'
        ));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));

        if (Application::get()->getName() == 'ojs2') {
            $this->addCheck(new FormValidator(
                $this,
                'datasetPublish',
                FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
                'plugins.generic.dataverse.settings.datasetPublishRequired'
            ));
        }
    }

    public function initData(): void
    {
        $configuration = $this->getStoredConfiguration();
        $data = $configuration->getAllData();
        unset($data['apiToken']);
        foreach ($data as $name => $value) {
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
        $this->flushCache();
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

    private function flushCache(): void
    {
        $cache = $this->getCache('dataverse_required_metadata');
        $cache->flush();
    }

    private function getCache(string $cacheId)
    {
        $cacheManager = CacheManager::getManager();
        $cache = $cacheManager->getFileCache(
            $this->contextId,
            $cacheId,
            function (FileCache $cache) {
                $cache->setEntireCache([]);
                return [];
            }
        );
        return $cache;
    }
}
