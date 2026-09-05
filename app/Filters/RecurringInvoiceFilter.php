<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class RecurringInvoiceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /*
        if ((int) date('d') >= 25) {
            $billing_date = date('Y-m-01', strtotime('first day of next month'));
        } else {
            $billing_date = date('Y-m-01');
        }

        // Single fixed-name lock file holding the last hour it ran, overwritten
        // each new hour (no pile-up of dated files). This only throttles how
        // often we bother checking, once per hour - it's not a
        // concurrency guard. generate_pending_recurring_invoices() has its own
        // MySQL GET_LOCK for that, and get_projects_to_bill() only ever returns
        // a project once per calendar month regardless of how often we check.
        $flag_file = WRITEPATH . 'cron_invoice_hourly.lock';
        $this_hour = date('Y-m-d H');

        if (file_exists($flag_file) && trim(file_get_contents($flag_file)) === $this_hour) {
            return;
        }

        file_put_contents($flag_file, $this_hour);

        $cron = new \App\Controllers\Cron();
        $cron->generate_pending_recurring_invoices($billing_date);
        */
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
