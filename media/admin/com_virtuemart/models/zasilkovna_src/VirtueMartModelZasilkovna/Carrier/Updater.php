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
        $carriers = $this->downloader->fetchAsArray($language);

        return $this->saveCarriersToDb($carriers);
    }

    /**
     * @param ApiCarrier[] $carriers
     * @return bool
     */
    private function saveCarriersToDb(array $carriers)
    {

        $carrierIdsToDelete = $this->carrierRepository->getAllActiveCarrierIds();

        foreach ($carriers as $carrier) {
            unset($carrierIdsToDelete[(string)$carrier->getId()]);

            $data = $carrier->mapToDbArray() + ['deleted' => false];

            $this->carrierRepository->insertUpdateCarrier($data);
        }

        $this->carrierRepository->setCarriersDeleted($carrierIdsToDelete);

        return true;
    }
}
