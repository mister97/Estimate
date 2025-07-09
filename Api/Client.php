<?php

/**
 * FOSSBilling Estimate Plugin - Client API
 */

namespace Box\Mod\Estimate\Api;

class Client extends \Api_Abstract
{
    /**
     * Get client's estimates
     */
    public function get_list($data): array
    {
        $client = $this->getIdentity();
        $service = $this->di['mod_service']('Estimate');
        
        $data['client_id'] = $client->id;
        return $service->getEstimatesList($data);
    }

    /**
     * Get estimate details for client
     */
    public function get($data): array
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();
        $service = $this->di['mod_service']('Estimate');
        
        $estimate = $service->getEstimate($data['id']);
        
        if ($estimate->client_id != $client->id) {
            throw new \FOSSBilling\InformationException('Estimate not found', [], 404);
        }

        return $service->toApiArray($estimate);
    }

    /**
     * Client accepts estimate
     */
    public function accept($data): array
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();
        $service = $this->di['mod_service']('Estimate');
        
        $estimate = $service->getEstimate($data['id']);
        
        if ($estimate->client_id != $client->id) {
            throw new \FOSSBilling\InformationException('Estimate not found', [], 404);
        }

        return $service->acceptEstimate($data['id'], $data);
    }

    /**
     * Client rejects estimate
     */
    public function reject($data): bool
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();
        $service = $this->di['mod_service']('Estimate');
        
        $estimate = $service->getEstimate($data['id']);
        
        if ($estimate->client_id != $client->id) {
            throw new \FOSSBilling\InformationException('Estimate not found', [], 404);
        }

        return $service->rejectEstimate($data['id'], $data['reason'] ?? '');
    }

    /**
     * Get estimate PDF
     */
    public function pdf($data): string
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();
        $service = $this->di['mod_service']('Estimate');
        
        $estimate = $service->getEstimate($data['id']);
        
        if ($estimate->client_id != $client->id) {
            throw new \FOSSBilling\InformationException('Estimate not found', [], 404);
        }

        return $service->generatePdf($data['id']);
    }
}
