<?php
/**
 * FOSSBilling Estimate Plugin - Admin Controller
 */

namespace Box\Mod\Estimate\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected $di;

    public function setDi(\Pimple\Container|null $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * This method registers menu items in admin area navigation block
     */
    public function fetchNavigation(): array
    {
        return [
            'group' => [
                'index' => 1500,
                'location' => 'estimate',
                'label' => __trans('Estimates'),
                'class' => 'notes',
            ],
            'subpages' => [
                [
                    'location' => 'estimate',
                    'label' => __trans('Manage Estimates'),
                    'index' => 1500,
                    'uri' => $this->di['url']->adminLink('estimate'),
                    'class' => '',
                ],
            ],
        ];
    }

    /**
     * Methods maps admin areas urls to corresponding methods
     */
    public function register(\Box_App &$app): void
    {
        $app->get('/estimate', 'get_index', [], static::class);
        $app->get('/estimate/', 'get_index', [], static::class);
        $app->get('/estimate/index', 'get_index', [], static::class);
        $app->get('/estimate/create', 'get_create', [], static::class);
        $app->get('/estimate/edit/:id', 'get_edit', ['id' => '[0-9]+'], static::class);
        $app->get('/estimate/view/:id', 'get_view', ['id' => '[0-9]+'], static::class);
        $app->get('/estimate/pdf/:id', 'get_pdf', ['id' => '[0-9]+'], static::class);
    }

    public function get_index(\Box_App $app)
    {
        // Always call this method to validate if admin is logged in
        $this->di['is_admin_logged'];
        
        // Get estimates list
        $api = $this->di['api_admin'];
        
        // Get request parameters safely - only include non-empty values
        $data = [];
        if (isset($_GET['status']) && !empty(trim($_GET['status']))) {
            $data['status'] = trim($_GET['status']);
        }
        if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
            $data['search'] = trim($_GET['search']);
        }
        
        try {
            $estimates = $api->estimate_get_list($data);
        } catch (\Exception $e) {
            $estimates = ['list' => [], 'total' => 0];
        }
        
        // Get clients for filters
        $clients = $this->di['db']->getAll('SELECT id, first_name, last_name, company, email FROM client ORDER BY first_name, last_name');
        
        $params = [
            'estimates' => $estimates['list'],
            'total' => $estimates['total'],
            'clients' => $clients,
            'request' => $data
        ];

        return $app->render('mod_estimate_index', $params);
    }

    public function get_create(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        
        // Get clients list
        $clients = $this->di['db']->getAll('SELECT id, first_name, last_name, company, email FROM client ORDER BY first_name, last_name');
        
        $params = [
            'clients' => $clients,
            'estimate' => []
        ];

        return $app->render('mod_estimate_form', $params);
    }

    public function get_edit(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];
        
        $api = $this->di['api_admin'];
        $estimate = $api->estimate_get(['id' => $id]);
        
        // Get clients list
        $clients = $this->di['db']->getAll('SELECT id, first_name, last_name, company, email FROM client ORDER BY first_name, last_name');
        
        $params = [
            'estimate' => $estimate,
            'clients' => $clients
        ];

        return $app->render('mod_estimate_form', $params);
    }

    public function get_view(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];
        
        $api = $this->di['api_admin'];
        $estimate = $api->estimate_get(['id' => $id]);
        
        $params = [
            'estimate' => $estimate
        ];

        return $app->render('mod_estimate_view', $params);
    }

    public function get_pdf(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];
        
        $api = $this->di['api_admin'];
        $pdf_content = $api->estimate_pdf(['id' => $id]);
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="estimate-' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdf_content));
        
        echo $pdf_content;
        exit;
    }
}