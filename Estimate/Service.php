<?php

/**
 * FOSSBilling Estimate Plugin - Service Class
 */

namespace Box\Mod\Estimate;

class Service
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    /**
     * Install plugin - create database tables
     */
    public function install()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS `estimate` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `client_id` int(11) NOT NULL,
            `estimate_number` varchar(50) NOT NULL,
            `title` varchar(255) NOT NULL DEFAULT 'Estimate',
            `notes` text,
            `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
            `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
            `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
            `total` decimal(10,2) NOT NULL DEFAULT '0.00',
            `currency` varchar(3) NOT NULL DEFAULT 'USD',
            `status` enum('draft','sent','accepted','rejected','converted','expired') NOT NULL DEFAULT 'draft',
            `valid_until` date NOT NULL,
            `invoice_id` int(11) DEFAULT NULL,
            `rejection_reason` text,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `estimate_number` (`estimate_number`),
            KEY `client_id` (`client_id`),
            KEY `status` (`status`),
            KEY `invoice_id` (`invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `estimate_item` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `estimate_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `description` text,
            `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
            `price` decimal(10,2) NOT NULL DEFAULT '0.00',
            `total` decimal(10,2) NOT NULL DEFAULT '0.00',
            PRIMARY KEY (`id`),
            KEY `estimate_id` (`estimate_id`),
            CONSTRAINT `estimate_item_ibfk_1` FOREIGN KEY (`estimate_id`) REFERENCES `estimate` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

        $this->di['db']->exec($sql);
        return true;
    }

    /**
     * Uninstall plugin - remove database tables
     */
    public function uninstall()
    {
        $this->di['db']->exec('DROP TABLE IF EXISTS `estimate_item`');
        $this->di['db']->exec('DROP TABLE IF EXISTS `estimate`');
        return true;
    }

    /**
     * Get estimates list
     */
    public function getEstimatesList($data): array
    {
        $sql = "SELECT e.*, c.first_name, c.last_name, c.company, c.email 
                FROM estimate e 
                LEFT JOIN client c ON e.client_id = c.id 
                WHERE 1=1";

        $params = [];

        if (isset($data['client_id'])) {
            $sql .= ' AND e.client_id = :client_id';
            $params[':client_id'] = $data['client_id'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND e.status = :status';
            $params[':status'] = $data['status'];
        }

        if (isset($data['search'])) {
            $sql .= ' AND (e.estimate_number LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR c.company LIKE :search)';
            $params[':search'] = '%' . $data['search'] . '%';
        }

        $sql .= ' ORDER BY e.created_at DESC';

        $estimates = $this->di['db']->getAll($sql, $params);

        return [
            'list' => $estimates,
            'total' => count($estimates)
        ];
    }

    /**
     * Get estimate by ID
     */
    public function getEstimate(int $id)
    {
        $estimate = $this->di['db']->findOne('estimate', 'id = ?', [$id]);

        if (!$estimate) {
            throw new \FOSSBilling\InformationException('Estimate not found', [], 404);
        }

        return $estimate;
    }

    /**
     * Convert estimate model to API array
     */
    public function toApiArray($estimate): array
    {
        $client = $this->di['db']->findOne('client', 'id = ?', [$estimate->client_id]);

        // Get estimate items
        $items = $this->di['db']->find('estimate_item', 'estimate_id = ?', [$estimate->id]);
        $itemsArray = [];
        foreach ($items as $item) {
            $itemsArray[] = [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total
            ];
        }

        return [
            'id' => $estimate->id,
            'client_id' => $estimate->client_id,
            'estimate_number' => $estimate->estimate_number,
            'title' => $estimate->title,
            'notes' => $estimate->notes,
            'subtotal' => $estimate->subtotal,
            'tax_rate' => $estimate->tax_rate,
            'tax_amount' => $estimate->tax_amount,
            'total' => $estimate->total,
            'currency' => $estimate->currency,
            'status' => $estimate->status,
            'valid_until' => $estimate->valid_until,
            'invoice_id' => $estimate->invoice_id,
            'rejection_reason' => $estimate->rejection_reason,
            'created_at' => $estimate->created_at,
            'updated_at' => $estimate->updated_at,
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'company' => $client->company,
                'email' => $client->email
            ],
            'items' => $itemsArray
        ];
    }

    /**
     * Create new estimate
     */
    public function createEstimate($data): int
    {
        try {
            // Create estimate
            $estimate = $this->di['db']->dispense('estimate');
            $estimate->client_id = $data['client_id'];
            $estimate->estimate_number = $this->generateEstimateNumber();
            $estimate->title = $data['title'] ?? 'Estimate';
            $estimate->notes = $data['notes'] ?? '';
            $estimate->subtotal = 0;
            $estimate->tax_rate = $data['tax_rate'] ?? 0;
            $estimate->tax_amount = 0;
            $estimate->total = 0;
            $estimate->currency = $data['currency'] ?? 'USD';
            $estimate->status = 'draft';
            $estimate->valid_until = $data['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
            $estimate->created_at = date('Y-m-d H:i:s');
            $estimate->updated_at = date('Y-m-d H:i:s');

            $estimate_id = $this->di['db']->store($estimate);

            // Add items
            $subtotal = $this->createEstimateItems($estimate_id, $data['items']);

            // Calculate totals
            $tax_amount = ($subtotal * $estimate->tax_rate) / 100;
            $total = $subtotal + $tax_amount;

            // Update estimate totals
            $estimate->subtotal = $subtotal;
            $estimate->tax_amount = $tax_amount;
            $estimate->total = $total;
            $this->di['db']->store($estimate);

            return $estimate_id;
        } catch (\Exception $e) {
            throw new \FOSSBilling\InformationException('Error creating estimate: ' . $e->getMessage());
        }
    }

    /**
     * Update estimate
     */
    public function updateEstimate($data): bool
    {
        $estimate = $this->getEstimate($data['id']);

        if ($estimate->status === 'converted') {
            throw new \FOSSBilling\InformationException('Cannot update converted estimate');
        }

        try {
            // Update estimate details
            if (isset($data['title'])) $estimate->title = $data['title'];
            if (isset($data['notes'])) $estimate->notes = $data['notes'];
            if (isset($data['valid_until'])) $estimate->valid_until = $data['valid_until'];
            if (isset($data['tax_rate'])) $estimate->tax_rate = $data['tax_rate'];

            $estimate->updated_at = date('Y-m-d H:i:s');

            // Update items if provided
            if (isset($data['items'])) {
                // Delete existing items
                $this->di['db']->exec('DELETE FROM estimate_item WHERE estimate_id = ?', [$data['id']]);

                // Add new items
                $subtotal = $this->createEstimateItems($data['id'], $data['items']);

                // Recalculate totals
                $tax_amount = ($subtotal * $estimate->tax_rate) / 100;
                $total = $subtotal + $tax_amount;

                $estimate->subtotal = $subtotal;
                $estimate->tax_amount = $tax_amount;
                $estimate->total = $total;
            }

            $this->di['db']->store($estimate);

            return true;
        } catch (\Exception $e) {
            throw new \FOSSBilling\InformationException('Error updating estimate: ' . $e->getMessage());
        }
    }

    /**
     * Delete estimate
     */
    public function deleteEstimate(int $id): bool
    {
        $estimate = $this->getEstimate($id);

        if ($estimate->status === 'converted') {
            throw new \FOSSBilling\InformationException('Cannot delete converted estimate');
        }

        try {
            // Delete items (handled by foreign key constraint)
            $this->di['db']->trash($estimate);

            return true;
        } catch (\Exception $e) {
            throw new \FOSSBilling\InformationException('Error deleting estimate: ' . $e->getMessage());
        }
    }

    /**
     * Send estimate to client (updated method)
     */
    public function sendEstimate(int $id): bool
    {
        $estimate = $this->getEstimate($id);
        $client = $this->di['db']->findOne('client', 'id = ?', [$estimate->client_id]);

        // Update status to sent
        $estimate->status = 'sent';
        $estimate->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($estimate);

        // Send email using FOSSBilling's template system
        try {
            $estimateData = $this->toApiArray($estimate);
            $api = $this->di['api_admin'];

            $email = [
                'to_client' => $client->id,
                'code' => 'mod_estimate_send', // This matches the email template file
                'estimate' => $estimateData,
                'client' => [
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'company' => $client->company
                ]
            ];

            $result = $api->email_template_send($email);

            if (!$result) {
                throw new \Exception('Email template send returned false');
            }
        } catch (\Exception $e) {
            error_log('Failed to send estimate email: ' . $e->getMessage());
            throw new \FOSSBilling\InformationException('Estimate email could not be sent: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Resend estimate to client (using FOSSBilling's email template system)
     */
    public function resendEstimate(int $id): bool
    {
        $estimate = $this->getEstimate($id);

        // Allow resending of sent, accepted, or rejected estimates
        if (!in_array($estimate->status, ['sent', 'accepted', 'rejected'])) {
            throw new \FOSSBilling\InformationException('Estimate cannot be resent in current status');
        }

        $client = $this->di['db']->findOne('client', 'id = ?', [$estimate->client_id]);

        // Don't change status - just resend
        $estimate->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($estimate);

        // Send email using FOSSBilling's template system
        try {
            $estimateData = $this->toApiArray($estimate);
            $api = $this->di['api_admin'];

            $email = [
                'to_client' => $client->id,
                'code' => 'mod_estimate_send', // This matches the email template file
                'estimate' => $estimateData,
                'client' => [
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'company' => $client->company
                ]
            ];

            $result = $api->email_template_send($email);

            if (!$result) {
                throw new \Exception('Email template send returned false');
            }
        } catch (\Exception $e) {
            error_log('Failed to resend estimate email: ' . $e->getMessage());
            throw new \FOSSBilling\InformationException('Estimate email could not be sent: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Accept estimate
     */
    public function acceptEstimate(int $id, $data): array
    {
        $estimate = $this->getEstimate($id);

        if ($estimate->status !== 'sent') {
            throw new \FOSSBilling\InformationException('Estimate cannot be accepted');
        }

        // Check if estimate is still valid
        if (strtotime($estimate->valid_until) < time()) {
            throw new \FOSSBilling\InformationException('Estimate has expired');
        }

        // Update estimate status
        $estimate->status = 'accepted';
        $estimate->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($estimate);

        $result = ['message' => 'Estimate accepted successfully'];

        // Optionally auto-convert to invoice
        if (isset($data['convert_to_invoice']) && $data['convert_to_invoice']) {
            $invoice_id = $this->convertToInvoice($id);
            $result['message'] = 'Estimate accepted and converted to invoice';
            $result['invoice_id'] = $invoice_id;
        }

        return $result;
    }

    /**
     * Admin accepts estimate on behalf of client
     */
    public function adminAcceptEstimate(int $id, $data): array
    {
        $estimate = $this->getEstimate($id);

        // Allow admin to accept estimates in sent, rejected, or even draft status
        if (in_array($estimate->status, ['accepted', 'converted'])) {
            throw new \FOSSBilling\InformationException('Estimate is already accepted or converted');
        }

        // Check if estimate is still valid (optional - admin can override)
        $override_validity = isset($data['override_validity']) && $data['override_validity'];
        if (!$override_validity && strtotime($estimate->valid_until) < time()) {
            throw new \FOSSBilling\InformationException('Estimate has expired. Use override_validity=true to accept anyway.');
        }

        // Update estimate status
        $estimate->status = 'accepted';
        $estimate->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($estimate);

        $result = ['message' => 'Estimate accepted successfully by admin'];

        // Optionally auto-convert to invoice
        if (isset($data['convert_to_invoice']) && $data['convert_to_invoice']) {
            $invoice_id = $this->convertToInvoice($id);
            $result['message'] = 'Estimate accepted by admin and converted to invoice';
            $result['invoice_id'] = $invoice_id;
        }

        // Optionally notify client
        if (isset($data['notify_client']) && $data['notify_client']) {
            try {
                $this->sendAcceptanceNotification($estimate);
                $result['message'] .= ' - Client notified';
            } catch (\Exception $e) {
                $result['message'] .= ' - Failed to notify client: ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Reject estimate
     */
    public function rejectEstimate(int $id, string $reason = ''): bool
    {
        $estimate = $this->getEstimate($id);

        if ($estimate->status !== 'sent') {
            throw new \FOSSBilling\InformationException('Estimate cannot be rejected');
        }

        // Update estimate status
        $estimate->status = 'rejected';
        $estimate->rejection_reason = $reason;
        $estimate->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($estimate);

        return true;
    }

    /**
     * Convert estimate to invoice
     */
    public function convertToInvoice(int $id): int
    {
        $estimate = $this->getEstimate($id);
        $client = $this->di['db']->findOne('client', 'id = ?', [$estimate->client_id]);

        if ($estimate->status === 'converted') {
            throw new \FOSSBilling\InformationException('Estimate already converted to invoice');
        }

        try {
            // Create invoice using basic structure
            $invoice = $this->di['db']->dispense('invoice');
            $invoice->client_id = $estimate->client_id;

            // Basic client info
            $invoice->buyer_first_name = $client->first_name;
            $invoice->buyer_last_name = $client->last_name;
            $invoice->buyer_company = $client->company ?? '';
            $invoice->buyer_company_vat = $client->company_vat ?? '';
            $invoice->buyer_company_number = $client->company_number ?? '';
            $invoice->buyer_email = $client->email;

            // Client address details
            $invoice->buyer_address = $client->address_1 ?? '';
            $invoice->buyer_city = $client->city ?? '';
            $invoice->buyer_state = $client->state ?? '';
            $invoice->buyer_country = $client->country ?? '';
            $invoice->buyer_zip = $client->postcode ?? '';

            // Client phone details
            $invoice->buyer_phone = $client->phone ?? '';
            $invoice->buyer_phone_cc = $client->phone_cc ?? '';

            // Company/seller details (you might want to get these from settings)
            $invoice->seller_company = $this->getSystemSetting('company_name') ?? '';
            $invoice->seller_company_vat = $this->getSystemSetting('company_vat') ?? '';
            $invoice->seller_company_number = $this->getSystemSetting('company_number') ?? '';
            $invoice->seller_address = $this->getSystemSetting('company_address_1') ?? '';
            $invoice->seller_phone = $this->getSystemSetting('company_tel') ?? '';
            $invoice->seller_email = $this->getSystemSetting('company_email') ?? '';

            // Invoice details
            $invoice->currency = $estimate->currency;
            $invoice->serie = $this->getSystemSetting('invoice_series') ?? '';
            $invoice->nr = $this->getNextInvoiceNumber();
            $invoice->hash = $this->generateInvoiceHash();
            $invoice->taxrate = $estimate->tax_rate;
            $invoice->status = 'unpaid';
            $invoice->notes = $estimate->notes . "\n\nConverted from Estimate #" . $estimate->estimate_number;
            $invoice->created_at = date('Y-m-d H:i:s');
            $invoice->updated_at = date('Y-m-d H:i:s');

            $invoice_id = $this->di['db']->store($invoice);

            // Copy items to invoice
            $items = $this->di['db']->find('estimate_item', 'estimate_id = ?', [$id]);
            foreach ($items as $item) {
                $invoiceItem = $this->di['db']->dispense('invoice_item');
                $invoiceItem->invoice_id = $invoice_id;
                $invoiceItem->type = 'custom';
                $invoiceItem->title = $item->title;
                // $invoiceItem->description = $item->description;
                $invoiceItem->quantity = $item->quantity;
                $invoiceItem->price = $item->price;
                $invoiceItem->charged = 0;
                $invoiceItem->created_at = date('Y-m-d H:i:s');
                $invoiceItem->updated_at = date('Y-m-d H:i:s');

                $this->di['db']->store($invoiceItem);
            }

            // Update estimate status
            $estimate->status = 'converted';
            $estimate->invoice_id = $invoice_id;
            $estimate->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($estimate);

            return $invoice_id;
        } catch (\Exception $e) {
            throw new \FOSSBilling\InformationException('Error converting estimate to invoice: ' . $e->getMessage());
        }
    }

    /**
     * Generate professionally styled PDF for estimate
     */
    public function generatePdf(int $id): string
    {
        $estimate = $this->getEstimate($id);
        $estimateData = $this->toApiArray($estimate);

        // Check if Dompdf is available
        if (!class_exists('Dompdf\Dompdf')) {
            // Try to load via composer autoload
            $autoloadPaths = [
                __DIR__ . '/../../vendor/autoload.php',
                __DIR__ . '/../../../vendor/autoload.php',
                __DIR__ . '/../../../../vendor/autoload.php',
                $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'
            ];

            foreach ($autoloadPaths as $autoload) {
                if (file_exists($autoload)) {
                    require_once $autoload;
                    break;
                }
            }

            if (!class_exists('Dompdf\Dompdf')) {
                throw new \FOSSBilling\InformationException('Dompdf not installed. Run: composer require dompdf/dompdf');
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->getOptions()->setChroot(__DIR__);
        $dompdf->getOptions()->setIsRemoteEnabled(false);

        // Load HTML template
        $html = $this->loadPdfTemplate($estimateData);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Load PDF HTML template with data
     */
    private function loadPdfTemplate($estimate): string
    {
        $templatePath = __DIR__ . '/html_email/estimate_pdf_template.html';

        if (!file_exists($templatePath)) {
            throw new \FOSSBilling\InformationException('PDF template not found: ' . $templatePath);
        }

        $template = file_get_contents($templatePath);
        return $this->renderPdfTemplate($template, $estimate);
    }

    /**
     * Render template with estimate data
     */
    private function renderPdfTemplate(string $template, array $estimate): string
    {
        // $client = $estimate['client'];
        $client = $this->di['db']->findOne('client', 'id = ?', [$estimate['client_id']]);

        $companyName = $this->getSystemSetting('company_name') ?? 'Your Company';
        $companyAddress1 = $this->getSystemSetting('company_address_1') ?? '';
        $companyAddress2 = $this->getSystemSetting('company_address_2') ?? '';
        $companyAddress3 = $this->getSystemSetting('company_address_3') ?? '';

        // Build address with proper HTML escaping and line breaks
        $addressParts = array_filter([
            htmlspecialchars($companyAddress1),
            htmlspecialchars($companyAddress2),
            htmlspecialchars($companyAddress3)
        ]); // array_filter removes empty strings

        $companyAddress = implode('<br>', $addressParts);

        $companyPhone = $this->getSystemSetting('company_tel') ?? '';
        $companyEmail = $this->getSystemSetting('company_email') ?? '';

        // Build items HTML
        $itemsHtml = '';
        foreach ($estimate['items'] as $item) {
            $itemsHtml .= '<tr>
                <td><div class="item-title">' . htmlspecialchars($item['title']) . '</div></td>
                <td><div class="item-description">' . htmlspecialchars($item['description']) . '</div></td>
                <td class="text-center">' . htmlspecialchars($item['quantity']) . '</td>
                <td class="text-right"><span class="currency">' . htmlspecialchars($estimate['currency']) . ' ' . number_format($item['price'], 2) . '</span></td>
                <td class="text-right"><span class="currency">' . htmlspecialchars($estimate['currency']) . ' ' . number_format($item['total'], 2) . '</span></td>
            </tr>';
        }

        // Build tax row if applicable
        $taxRowHtml = '';
        if ($estimate['tax_rate'] > 0) {
            $taxRowHtml = '<tr>
                <td><strong>Tax (' . htmlspecialchars($estimate['tax_rate']) . '%):</strong></td>
                <td class="text-right"><span class="currency">' . htmlspecialchars($estimate['currency']) . ' ' . number_format($estimate['tax_amount'], 2) . '</span></td>
            </tr>';
        }

        // Build notes section if applicable
        $notesHtml = '';
        if ($estimate['notes']) {
            $notesHtml = '<div class="notes-section">
                <div class="notes-title">Notes:</div>
                <div class="notes-content">' . nl2br(htmlspecialchars($estimate['notes'])) . '</div>
            </div>';
        }

        $clientAddress = $this->formatClientAddress($client);

        $hasPhoneCC = !empty($client['phone_cc']);
        $hasPhone = !empty($client['phone']);

        $clientPhone = '';
        if ($hasPhoneCC && $hasPhone) {
            $clientPhone = '+' . htmlspecialchars($client['phone_cc']) . ' ' . htmlspecialchars($client['phone']);
        } elseif ($hasPhone) {
            // If only phone number exists (no country code)
            $clientPhone = htmlspecialchars($client['phone']);
        }

        // Replace template variables
        $replacements = [
            '{{COMPANY_NAME}}' => htmlspecialchars($companyName),
            '{{COMPANY_ADDRESS}}' => $companyAddress,
            '{{COMPANY_PHONE}}' => htmlspecialchars($companyPhone),
            '{{COMPANY_EMAIL}}' => htmlspecialchars($companyEmail),
            '{{ESTIMATE_NUMBER}}' => htmlspecialchars($estimate['estimate_number']),
            '{{ESTIMATE_DATE}}' => date('F j, Y', strtotime($estimate['created_at'])),
            '{{ESTIMATE_VALID_UNTIL}}' => date('F j, Y', strtotime($estimate['valid_until'])),
            '{{CLIENT_NAME}}' => htmlspecialchars($client['first_name'] . ' ' . $client['last_name']),
            '{{CLIENT_COMPANY}}' => $client['company'] ? '<div>' . htmlspecialchars($client['company']) . '</div>' : '',
            '{{CLIENT_ADDRESS}}' => $clientAddress ? '<div>' . $clientAddress . '</div>' : '',
            '{{CLIENT_EMAIL}}' => htmlspecialchars($client['email']),
            '{{CLIENT_PHONE}}' => $clientPhone ? '<div>' . $clientPhone . '</div>' : '',
            '{{PROJECT_TITLE}}' => htmlspecialchars($estimate['title']),
            '{{ITEMS_HTML}}' => $itemsHtml,
            '{{SUBTOTAL}}' => htmlspecialchars($estimate['currency']) . ' ' . number_format($estimate['subtotal'], 2),
            '{{TAX_ROW}}' => $taxRowHtml,
            '{{TOTAL}}' => htmlspecialchars($estimate['currency']) . ' ' . number_format($estimate['total'], 2),
            '{{NOTES_SECTION}}' => $notesHtml,
            '{{CURRENT_DATE}}' => date('F j, Y \a\t g:i A')
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function getSystemSetting(string $key): ?string
    {
        $db = $this->di['db'];
        $setting = $db->findOne('setting', 'param = :param', [':param' => $key]);
        return $setting ? $setting->value : null;
    }

    private function formatClientAddress($client): string
    {
        $addressLine1 = trim($client['address_1'] ?? '');
        $addressLine2 = trim($client['address_2'] ?? '');
        $city = trim($client['city'] ?? '');
        $state = trim($client['state'] ?? '');
        $postcode = trim($client['postcode'] ?? '');
        $country = trim($client['country'] ?? '');

        $clientAddressParts = [];

        // Add address line 1 if it exists
        if (!empty($addressLine1)) {
            $clientAddressParts[] = htmlspecialchars($addressLine1);
        }

        // Add address line 2 if it exists
        if (!empty($addressLine2)) {
            $clientAddressParts[] = htmlspecialchars($addressLine2);
        }

        // Combine city, state, postcode on one line
        $cityStatePostcode = array_filter([
            htmlspecialchars($city),
            htmlspecialchars($state),
            htmlspecialchars($postcode),
            htmlspecialchars($country)
        ]);

        if (!empty($cityStatePostcode)) {
            $clientAddressParts[] = implode(', ', $cityStatePostcode);
        }

        // Add country on its own line if it exists
        // if (!empty($country)) {
        //     $clientAddressParts[] = htmlspecialchars($country);
        // }

        return implode('<br>', $clientAddressParts);
    }

    /**
     * Send acceptance notification to client
     */
    private function sendAcceptanceNotification($estimate): bool
    {
        $client = $this->di['db']->findOne('client', 'id = ?', [$estimate->client_id]);

        try {
            $estimateData = $this->toApiArray($estimate);
            $api = $this->di['api_admin'];

            $email = [
                'to_client' => $client->id,
                'code' => 'mod_estimate_accepted', // Different template for acceptance
                'estimate' => $estimateData,
                'client' => [
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'company' => $client->company
                ]
            ];

            $result = $api->email_template_send($email);

            return (bool)$result;
        } catch (\Exception $e) {
            error_log('Failed to send acceptance notification: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate unique estimate number
     */
    private function generateEstimateNumber(): string
    {
        $prefix = 'EST-';
        $year = date('Y');

        // Get last estimate number for this year
        $sql = 'SELECT estimate_number FROM estimate WHERE estimate_number LIKE ? ORDER BY id DESC LIMIT 1';
        $last_number = $this->di['db']->getCell($sql, [$prefix . $year . '%']);

        if ($last_number) {
            $number = intval(substr($last_number, -4)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create estimate items
     */
    private function createEstimateItems(int $estimate_id, array $items): float
    {
        $subtotal = 0;

        foreach ($items as $itemData) {
            $item = $this->di['db']->dispense('estimate_item');
            $item->estimate_id = $estimate_id;
            $item->title = $itemData['title'];
            $item->description = $itemData['description'] ?? '';
            $item->quantity = $itemData['quantity'];
            $item->price = $itemData['price'];
            $item->total = $itemData['quantity'] * $itemData['price'];

            $this->di['db']->store($item);

            $subtotal += $item->total;
        }

        return $subtotal;
    }

    /**
     * Get next invoice number
     */
    private function getNextInvoiceNumber(): int
    {
        $sql = 'SELECT MAX(nr) FROM invoice';
        $last_number = $this->di['db']->getCell($sql);

        return ($last_number ? $last_number : 0) + 1;
    }

    /**
     * Generate invoice hash
     */
    private function generateInvoiceHash(): string
    {
        return md5(uniqid(rand(), true));
    }
}
