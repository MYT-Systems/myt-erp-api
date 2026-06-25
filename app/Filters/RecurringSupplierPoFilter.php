<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class RecurringSupplierPoFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Same billing window as RecurringInvoiceFilter:
        // day 1-24  → bill current month (e.g. June 1-24  → billing_date = 2026-06-01)
        // day 25-31 → bill next month    (e.g. June 25-30 → billing_date = 2026-07-01)
        if ((int) date('d') >= 25) {
            $billing_date = date('Y-m-01', strtotime('first day of next month'));
        } else {
            $billing_date = date('Y-m-01');
        }

        $flag_file = WRITEPATH . 'cron_supplier_po_' . $billing_date . '.lock';

        if (file_exists($flag_file)) return;

        file_put_contents($flag_file, date('Y-m-d H:i:s'));

        $cron = new \App\Controllers\Cron();
        $cron->generate_recurring_supplier_po($billing_date);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
