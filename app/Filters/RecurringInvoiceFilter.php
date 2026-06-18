<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class RecurringInvoiceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ((int) date('d') >= 25) {
            $billing_date = date('Y-m-01', strtotime('first day of next month'));
        } else {
            $billing_date = date('Y-m-01');
        }

        $flag_file = WRITEPATH . 'cron_invoice_' . $billing_date . '.lock';

        if (file_exists($flag_file)) return;

        $db = \Config\Database::connect();

        $already_ran = $db->query("
            SELECT COUNT(*) as total FROM project_invoice
            WHERE DATE_FORMAT(invoice_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
            AND added_by = 0
            AND is_deleted = 0
        ", [$billing_date])->getRowArray();

        if ($already_ran['total'] > 0) {
            file_put_contents($flag_file, date('Y-m-d H:i:s'));
            return;
        }

        file_put_contents($flag_file, date('Y-m-d H:i:s'));

        $cron = new \App\Controllers\Cron();
        $cron->generate_pending_recurring_invoices($billing_date);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
