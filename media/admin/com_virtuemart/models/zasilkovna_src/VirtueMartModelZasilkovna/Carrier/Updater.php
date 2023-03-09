<?php

namespace VirtueMartModelZasilkovna\Carrier;

/**
 *
 */
class Updater
{
    /**
     * @var Downloader
     */
    private $downloader;

    /**
     * @var Repository
     */
    private $carrierRepository;

    /**
     * Updater constructor.
     * @param string $apiKey
     * @param Repository $carrierRepository
     */
    public function __construct($apiKey, Repository $carrierRepository)
    {
        $this->carrierRepository = $carrierRepository;
        $this->downloader = new Downloader($apiKey);
    }

    /**
     * @param string $language
     * @return bool
     * @throws DownloadException
     */
    public function run($language)
    {
        $carrierSettings = $this->downloader->fetchAsArray($language);

        return $this->saveCarrierSettingsToDb($carrierSettings);
    }

    /**
     * @param CarrierSetting[] $carrierSettings
     * @return bool
     */
    private function saveCarrierSettingsToDb(array $carrierSettings)
    {

        $carrierIdsToDelete = $this->carrierRepository->getAllActiveCarrierIds();

        foreach ($carrierSettings as $carrierSetting) {
            unset($carrierIdsToDelete[(string)$carrierSetting->getId()]);

            $data = $carrierSetting->mapToDbArray() + ['deleted' => false];

            $this->carrierRepository->insertUpdateCarrier($data);
        }

        $this->carrierRepository->setCarriersDeleted($carrierIdsToDelete);

        return true;
    }
}
