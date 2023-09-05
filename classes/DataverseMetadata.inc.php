<?php

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

    public function getDataverseLicenses(): array
    {
        $configuration = $this->getDataverseConfiguration();
        $licensesUrl = $configuration->getDataverseServerUrl() . '/api/licenses';
        $response = json_decode(file_get_contents($licensesUrl), true);
        $this->dataverseLicenses = $response['data'];

        return $this->dataverseLicenses;
    }

    public function getDefaultLicense(): int
    {
        if(is_null($this->dataverseLicenses)) {
            $this->getDataverseLicenses();
        }

        foreach($this->dataverseLicenses as $license) {
            if($license['isDefault']) {
                return $license['name'];
            }
        }
    }

    public function getLicenseUri(string $licenseName): string
    {
        if(is_null($this->dataverseLicenses)) {
            $this->getDataverseLicenses();
        }

        foreach($this->dataverseLicenses as $license) {
            if($license['name'] == $licenseName) {
                return $license['uri'];
            }
        }
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
