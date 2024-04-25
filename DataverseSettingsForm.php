<?php

namespace APP\plugins\generic\dataverse;

use PKP\form\Form;
use PKP\plugins\Plugin;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\validation\FormValidatorUrl;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorPost;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;

class DataverseSettingsForm extends Form
{
    private $plugin;
    private $contextId;

    public function __construct(Plugin $plugin, int $contextId)
    {
        parent::__construct($plugin->getTemplateResource('dataverseConfigurationForm.tpl'));

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
            'termsOfUse',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.dataverse.settings.dataverseUrlNotValid',
            [$this, 'validateConfiguration']
        ));
        $this->addCheck(new FormValidatorPost($this));

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
        $configurationDAO = DAORegistry::getDAO('DataverseConfigurationDAO');
        $configuration = $configurationDAO->get($this->contextId);
        $data = $configuration->getAllData();
        foreach ($data as $name => $value) {
            $this->setData($name, $value);
        }
    }

    public function readInputData(): void
    {
        $this->readUserVars(['dataverseUrl', 'apiToken', 'termsOfUse', 'datasetPublish']);
        $this->setData('dataverseUrl', $this->normalizeURI($this->getData('dataverseUrl')));
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
        return parent::fetch($request);
    }

    public function execute(...$functionArgs)
    {
        $this->plugin->updateSetting($this->contextId, 'dataverseUrl', $this->getData('dataverseUrl'), 'string');
        $this->plugin->updateSetting($this->contextId, 'apiToken', $this->getData('apiToken'), 'string');
        $this->plugin->updateSetting($this->contextId, 'termsOfUse', $this->getData('termsOfUse'));
        $this->plugin->updateSetting($this->contextId, 'datasetPublish', (int) $this->getData('datasetPublish'));
        parent::execute(...$functionArgs);
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
            return false;
        }

        return true;
    }
}
