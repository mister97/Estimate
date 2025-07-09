<?php
/**
 * FOSSBilling Estimate Plugin - Client Controller
 */

namespace Box\Mod\Estimate\Controller;

class Client implements \FOSSBilling\InjectionAwareInterface
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
     * Methods maps client areas urls to corresponding methods
     */
    public function register(\Box_App &$app): void
    {
        $app->get('/estimate', 'get_index', [], static::class);
        $app->get('/estimate/', 'get_index', [], static::class);
        $app->get('/estimate/index', 'get_index', [], static::class);
        $app->get('/estimate/view/:id', 'get_view', ['id' => '[0-9]+'], static::class);
        $app->get('/estimate/pdf/:id', 'get_pdf', ['id' => '[0-9]+'], static::class);
    }

    public function get_index(\Box_App $app)
    {
        // Check if client is logged in
        $this->di['is_client_logged'];
        
        // Get client's estimates
        $api = $this->di['api_client'];
        
        // Get request parameters safely
        $data = [];
        if (isset($_GET['status'])) {
            $data['status'] = $_GET['status'];
        }
        if (isset($_GET['search'])) {
            $data['search'] = $_GET['search'];
        }
        
        try {
            $estimates = $api->estimate_get_list($data);
        } catch (\Exception $e) {
            $estimates = ['list' => [], 'total' => 0];
        }
        
        $params = [
            'estimates' => $estimates['list'],
            'total' => $estimates['total']
        ];

        return $app->render('mod_estimate_client_index', $params);
    }

    public function get_view(\Box_App $app, $id)
    {
        $this->di['is_client_logged'];
        
        $api = $this->di['api_client'];
        $estimate = $api->estimate_get(['id' => $id]);
        
        $params = [
            'estimate' => $estimate
        ];

        return $app->render('mod_estimate_client_view', $params);
    }

    public function get_pdf(\Box_App $app, $id)
    {
        $this->di['is_client_logged'];
        
        try {
            $api = $this->di['api_client'];
            $pdf_content = $api->estimate_pdf(['id' => $id]);
            
            // Get estimate details for filename
            $estimate = $api->estimate_get(['id' => $id]);
            $filename = 'estimate-' . $estimate['estimate_number'] . '.pdf';
            
            // Clear any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Set proper headers for PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf_content));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            // Output PDF content
            echo $pdf_content;
            exit;
            
        } catch (\Exception $e) {
            // If PDF generation fails, show error
            header('Content-Type: text/html');
            echo '<h1>PDF Generation Error</h1>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><a href="javascript:history.back()">Go Back</a></p>';
            exit;
        }
    }
}