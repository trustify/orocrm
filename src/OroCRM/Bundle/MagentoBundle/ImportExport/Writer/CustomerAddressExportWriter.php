<?php

namespace OroCRM\Bundle\MagentoBundle\ImportExport\Writer;

use Oro\Bundle\IntegrationBundle\Exception\TransportException;
use OroCRM\Bundle\MagentoBundle\Entity\Address;
use OroCRM\Bundle\MagentoBundle\Entity\Repository\RegionRepository;

class CustomerAddressExportWriter extends AbstractExportWriter
{
    const CUSTOMER_ADDRESS_ID_KEY = 'customer_address_id';
    const CUSTOMER_ID_KEY = 'customer_id';
    const FAULT_CODE_NOT_EXISTS = '102';
    const CONTEXT_CUSTOMER_ADDRESS_POST_PROCESS = 'postProcessCustomerAddress';

    /**
     * @var string
     */
    protected $magentoRegionClass;

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        /** @var Address $entity */
        $entity = $this->getEntity();
        if ($this->getStateManager()->isInState($entity->getSyncState(), Address::MAGENTO_REMOVED)) {
            return;
        }

        $item = reset($items);
        if (!empty($item['region_id'])) {
            $item['region_id'] = $this->getMagentoRegionIdByCombinedCode($item['region_id']);
            if (empty($item['region_id'])) {
                $item['region'] = $entity->getRegionName();
            }
        }

        if (!$item) {
            $this->logger->error('Wrong Customer Address data', (array)$item);

            return;
        }

        $this->transport->init($this->getChannel()->getTransport());
        if (empty($item[self::CUSTOMER_ADDRESS_ID_KEY])) {
            $this->writeNewItem($item);
        } else {
            $this->writeExistingItem($item);
        }

        parent::write([$entity]);
    }

    /**
     * @param array $item
     */
    protected function writeNewItem(array $item)
    {
        /** @var Address $entity */
        $entity = $this->getEntity();

        try {
            $customerId = $item[self::CUSTOMER_ID_KEY];
            $customerAddressId = $this->transport->createCustomerAddress($customerId, $item);
            $entity->setOriginId($customerAddressId);
            $this->markSynced($entity);

            $this->logger->info(
                sprintf(
                    'Customer address with id %s for customer %s successfully created with data %s',
                    $customerAddressId,
                    $customerId,
                    json_encode($item)
                )
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return;
        }
    }

    /**
     * @param array $item
     */
    protected function writeExistingItem(array $item)
    {
        $customerAddressId = $item[self::CUSTOMER_ADDRESS_ID_KEY];

        /** @var Address $entity */
        $entity = $this->getEntity();

        try {
            $remoteData = $this->transport->getCustomerAddressInfo($customerAddressId);
            $item = $this->getStrategy()->merge(
                $this->getEntityChangeSet(),
                $item,
                $remoteData,
                $this->getTwoWaySyncStrategy()
            );

            $this->stepExecution->getJobExecution()
                ->getExecutionContext()
                ->put(self::CONTEXT_CUSTOMER_ADDRESS_POST_PROCESS, [$item]);

            $result = $this->transport->updateCustomerAddress($customerAddressId, $item);

            if ($result) {
                $this->markSynced($entity);

                $this->logger->info(
                    sprintf(
                        'Customer address with id %s successfully updated with data %s',
                        $customerAddressId,
                        json_encode($item)
                    )
                );
            } else {
                $this->logger->error(sprintf('Customer address with id %s was not updated', $customerAddressId));
            }
        } catch (TransportException $e) {
            if ($e->getFaultCode() === self::FAULT_CODE_NOT_EXISTS) {
                $this->markRemoved($entity);
            }

            $this->logger->error($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return;
        }
    }

    /**
     * @param string $magentoRegionClass
     * @return CustomerAddressExportWriter
     */
    public function setMagentoRegionClass($magentoRegionClass)
    {
        $this->magentoRegionClass = $magentoRegionClass;

        return $this;
    }

    /**
     * @param string $combinedCode
     * @return int
     */
    protected function getMagentoRegionIdByCombinedCode($combinedCode)
    {
        /** @var RegionRepository $magentoRegionRepository */
        $magentoRegionRepository = $this->registry->getRepository($this->magentoRegionClass);

        return $magentoRegionRepository->getMagentoRegionIdByCombinedCode($combinedCode);
    }

    /**
     * @param Address $address
     */
    protected function markRemoved(Address $address)
    {
        $this->getStateManager()->addState($address, 'syncState', Address::MAGENTO_REMOVED);
    }

    /**
     * @param Address $address
     */
    protected function markSynced(Address $address)
    {
        $this->getStateManager()->removeState($address, 'syncState', Address::SYNC_TO_MAGENTO);
    }
}
