<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class RecurringSupplierPoFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /*
        // Same billing window as RecurringInvoiceFilter:
        // day 1-24  → bill current month (e.g. June 1-24  → billing_date = 2026-06-01)
        // day 25-31 → bill next month    (e.g. June 25-30 → billing_date = 2026-07-01)
        if ((int) date('d') >= 25) {
            $billing_date = date('Y-m-01', strtotime('first day of next month'));
        } else {
            $billing_date = date('Y-m-01');
        }

        // Single fixed-name lock file holding the last hour it ran, overwritten
        // each new hour (no pile-up of dated files). This only throttles how
        // often we bother checking, once per hour - it's not a
        // concurrency guard. generate_recurring_supplier_po() has its own
        // MySQL GET_LOCK for that, and get_unoccupied() only ever picks up
        // templates that haven't been generated yet regardless of how often we check.
        $flag_file = WRITEPATH . 'cron_supplier_po_hourly.lock';
        $this_hour = date('Y-m-d H');

        if (file_exists($flag_file) && trim(file_get_contents($flag_file)) === $this_hour) {
            return;
        }

        file_put_contents($flag_file, $this_hour);

        $cron = new \App\Controllers\Cron();
        $cron->generate_recurring_supplier_po($billing_date);
        */
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
