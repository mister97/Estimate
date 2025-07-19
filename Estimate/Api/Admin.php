<?php

/**
 * FOSSBilling Estimate Plugin - Admin API
 * 
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 */

namespace Box\Mod\Estimate\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of estimates
     */
    public function get_list($data): array
    {
        // Check permission
        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to view estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->getEstimatesList($data);
    }

    /**
     * Get estimate details
     */
    public function get($data): array
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to view estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->toApiArray($service->getEstimate($data['id']));
    }

    /**
     * Create new estimate
     */
    public function create($data): int
    {
        $required = [
            'client_id' => 'Client ID is required',
            'items' => 'Items are required'
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to create estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->createEstimate($data);
    }

    /**
     * Update estimate
     */
    public function update($data): bool
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to update estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->updateEstimate($data);
    }

    /**
     * Delete estimate
     */
    public function delete($data): bool
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to delete estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->deleteEstimate($data['id']);
    }

    /**
     * Send estimate to client
     */
    public function send($data): bool
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'send_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to send estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->sendEstimate($data['id']);
    }

    /**
     * Resend estimate to client
     */
    public function resend($data): bool
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'send_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to send estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->resendEstimate($data['id']);
    }

    /**
     * Admin accepts estimate on behalf of client
     */
    public function accept($data): array
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to accept estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->adminAcceptEstimate($data['id'], $data);
    }

    /**
     * Admin rejects estimate on behalf of client
     */
    public function reject($data): bool
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to reject estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->rejectEstimate($data['id'], $data['reason'] ?? 'Rejected by admin');
    }

    /**
     * Convert estimate to invoice
     */
    public function convert_to_invoice($data): int
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'convert_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to convert estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->convertToInvoice($data['id']);
    }

    /**
     * Get estimate PDF
     */
    public function pdf($data): string
    {
        $required = ['id' => 'Estimate ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to view estimates', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->generatePdf($data['id']);
    }

    /**
     * Get products for estimate items
     */
    public function get_products($data): array
    {
        $staff_service = $this->di['mod_service']('Staff');
        if (!$staff_service->hasPermission(null, 'estimate', 'manage_estimates')) {
            throw new \FOSSBilling\InformationException('You do not have permission to view products', [], 403);
        }

        $service = $this->di['mod_service']('Estimate');
        return $service->getProductsForEstimate($data);
    }
}