<?php

namespace APP\plugins\generic\dataverse\classes;

use APP\core\Application;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;

class DataverseMetadata
{
    private $dataverseLicenses;

    public function getDataverseSubjects(): array
    {
        return [
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.agriculturalSciences'),
                'value' => 'Agricultural Sciences',
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.artsAndHumanities'),
                'value' => 'Arts and Humanities'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.astronomyAndAstrophysics'),
                'value' => 'Astronomy and Astrophysics'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.businessAndManagement'),
                'value' => 'Business and Management'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.chemistry'),
                'value' => 'Chemistry'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.computerAndInformationScience'),
                'value' => 'Computer and Information Science'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.earthAndEnvironmentalSciences'),
                'value' => 'Earth and Environmental Sciences'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.Engineering'),
                'value' => 'Engineering'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.Law'),
                'value' => 'Law'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.mathematicalSciences'),
                'value' => 'Mathematical Sciences'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.medicineHealthAndLifeSciences'),
                'value' => 'Medicine, Health and Life Sciences'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.Physics'),
                'value' => 'Physics'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.socialSciences'),
                'value' => 'Social Sciences'
            ],
            [
                'label' => __('plugins.generic.dataverse.metadataForm.subject.Other'),
                'value' => 'Other'
            ],
        ];
    }

    public function getDataverseLicenses(): ?array
    {
        try {
            $dataverseClient = new DataverseClient();
            $this->dataverseLicenses = $dataverseClient->getDataverseCollectionActions()->getLicenses();

            return $this->dataverseLicenses;
        } catch (DataverseException $e) {
            error_log('Dataverse API error (licenses): ' . $e->getMessage());
            return [];
        }
    }

    public function getDefaultLicense(): ?string
    {
        if (empty($this->dataverseLicenses)) {
            $this->getDataverseLicenses();
        }

        foreach ($this->dataverseLicenses as $license) {
            if ($license['isDefault']) {
                return $license['name'];
            }
        }

        return null;
    }

    private function getDataverseConfiguration(): DataverseConfiguration
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $configurationDAO = DAORegistry::getDAO('DataverseConfigurationDAO');
        $configuration = $configurationDAO->get($context->getId());

        return $configuration;
    }
}
