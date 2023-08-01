<?php

namespace App\Controllers;

class Reports extends MYTController
{
    
    public function __construct()
    {
        // Headers for API
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
       
        $this->_load_essentials();
    }

    /**
     * Search franchisee_sale_billings based on parameters passed
     */
    public function franchise_sales()
    {
        if (($response = $this->_api_verification('reports', 'franchise_sales')) !== true)
            return $response;

        $franchisee_id   = $this->request->getVar('franchisee_id') ? : NULL;
        $franchisee_name = $this->request->getVar('franchisee_name') ? : NULL;
        $branch_id       = $this->request->getVar('branch_id') ? : NULL;
        $type            = $this->request->getVar('type') ? : NULL;
        $date_from       = $this->request->getVar('date_from') ? : NULL;
        $date_to         = $this->request->getVar('date_to') ? : NULL;
        $payment_status  = $this->request->getVar('payment_status') ? : NULL;

        $franchise_sales = [];
        $billings        = [];
        
        if (!$type OR $type == 'invoice_sales')
            $franchise_sales = $this->franchiseeSalePaymentModel->get_payment_with_sale_details($franchisee_id, $franchisee_name, $branch_id, $date_from, $date_to, $payment_status);
        
        if (!$type OR $type != 'invoice_sales')
            $billings = $this->fsBillingPaymentModel->get_payment_with_billing_details($franchisee_id, $franchisee_name, $branch_id, $type, $date_from, $date_to, $payment_status);

        if (!$franchise_sales AND !$billings) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            $summary = [
                'total_royalty_fees' => 0,
                'total_marketing_fees' => 0,
                'total_invoices' => 0,
                'total_sales' => 0
            ];

            $sales_count = $franchise_sales ? count($franchise_sales) : 0;
            $billings_count = $billings ? count($billings) : 0;
            $max_count = $billings_count > $sales_count ? $billings_count : $sales_count;

            for ($i=0; $i<$max_count; $i++) {
                if ($i < $sales_count) {
                    $summary['total_royalty_fees'] += $franchise_sales[$i]['royalty_fee'];
                    $summary['total_marketing_fees'] += $franchise_sales[$i]['marketing_fee'];
                    $summary['total_invoices'] += $franchise_sales[$i]['sales_invoice'];
                    $summary['total_sales'] += ($franchise_sales[$i]['royalty_fee'] + $franchise_sales[$i]['marketing_fee'] + $franchise_sales[$i]['sales_invoice']);
                }

                if ($i < $billings_count) {
                    $summary['total_royalty_fees'] += $billings[$i]['royalty_fee'];
                    $summary['total_marketing_fees'] += $billings[$i]['marketing_fee'];
                    $summary['total_invoices'] += $billings[$i]['sales_invoice'];
                    $summary['total_sales'] += ($billings[$i]['royalty_fee'] + $billings[$i]['marketing_fee'] + $billings[$i]['sales_invoice']);   
                }
            }

            if ($franchise_sales AND $billings)
                $sales = array_merge($franchise_sales, $billings);
            elseif ($franchise_sales)
                $sales = $franchise_sales;
            elseif ($billings)
                $sales = $billings;
            else
                $sales = [];
            
            $response = $this->respond([
                'summary' => $summary,
                'data'   => $sales,
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get expense by type
     */
    public function get_expense_by_type()
    {
        if (($response = $this->_api_verification('reports', 'get_expense_by_type')) !== true)
            return $response;

        $expense_type = $this->request->getVar('expense_type');
        $date_from = $this->request->getVar('date_from');
        $date_to = $this->request->getVar('date_to');
        $payment_status = $this->request->getVar('payment_status');

        if (!$expenses = $this->reportModel->get_expense($expense_type, $date_from, $date_to, $payment_status)) {
            $response = $this->failNotFound('No report Found');
        } else {

            $expense_total_arr = [];
            $expense_type_arr = [];
            
            foreach ($expenses as $item) {
                $expense_total = (float) $item['expense_total'];
                $expense_type = $item['expense_type'];
            
                if (isset($expense_total_arr[$expense_type])) {
                    $expense_total_arr[$expense_type] += $expense_total;
                } else {
                    $expense_total_arr[$expense_type] = $expense_total;
                    $expense_type_arr[] = $expense_type;
                }
            }

            $expense_totals = [];

            foreach($expense_total_arr AS $expense_total_item) {
                $expense_totals[] = $expense_total_item;
            }

            $response = $this->respond([
                'expense_total' => $expense_totals,
                'expense_type' => $expense_type_arr,
                'status'  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get expense by type
     */
    public function get_expense_by_date()
    {
        if (($response = $this->_api_verification('reports', 'get_expense_by_date')) !== true)
            return $response;

        $expense_type = $this->request->getVar('expense_type');
        $date_from = $this->request->getVar('date_from');
        $date_to = $this->request->getVar('date_to');
        $payment_status = $this->request->getVar('payment_status');

        if (!$expenses = $this->reportModel->get_expense($expense_type, $date_from, $date_to, $payment_status)) {
            $response = $this->failNotFound('No report Found');
        } else {

            $expense_total_arr = [];
            $expense_date_arr = [];
            
            foreach ($expenses as $item) {
                $expense_total = (float) $item['expense_total'];
                $expense_date = $item['expense_date'];
            
                if (isset($expense_total_arr[$expense_date])) {
                    $expense_total_arr[$expense_date] += $expense_total;
                } else {
                    $expense_total_arr[$expense_date] = $expense_total;
                    $expense_date_arr[] = $expense_date;
                }
            }

            $expense_totals = [];

            foreach($expense_total_arr AS $expense_total_item) {
                $expense_totals[] = $expense_total_item;
            }

            $response = $this->respond([
                'expense_total' => $expense_totals,
                'expense_date' => $expense_date_arr,
                'status'  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get expense by type
     */
    public function get_expense()
    {
        if (($response = $this->_api_verification('reports', 'get_expense')) !== true)
            return $response;

        $expense_type = $this->request->getVar('expense_type');
        $date_from = $this->request->getVar('date_from');
        $date_to = $this->request->getVar('date_to');
        $payment_status = $this->request->getVar('payment_status');

        if (!$expenses = $this->reportModel->get_expense($expense_type, $date_from, $date_to, $payment_status)) {
            $response = $this->failNotFound('No report Found');
        } else {

            $summary = [
                'total_expense' => 0,
                'total_paid' => 0,
                'total_balance' => 0
            ];
            
            foreach($expenses AS $expense) {
                $summary['total_expense'] += $expense['expense_total'];
                $summary['total_paid'] += $expense['paid_amount'];
            }

                $summary['total_balance'] = $summary['total_expense'] - $summary['total_paid'];

            // $expenses['summary'] = $summary;

            $response = $this->respond([
                'expense' => $expenses,
                'summary' => $summary,
                'status'  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get project sales
     */
    public function get_project_sales()
    {
        if (($response = $this->_api_verification('reports', 'get_project_sales')) !== true)
            return $response;

        $project_id = $this->request->getVar('project_id');
        $date_from = $this->request->getVar('date_from');
        $date_to = $this->request->getVar('date_to');
        $customer_id = $this->request->getVar('customer_id');

        if (!$project_sales = $this->reportModel->get_project_sales($project_id, $date_from, $date_to, $customer_id)) {
            $response = $this->failNotFound('No report Found');
        } else {

            $summary = [
                'total_amount' => 0,
                'total_expense' => 0,
            ];
            
            foreach($project_sales AS $project_sale) {
                $summary['total_amount'] += $project_sale['amount'];
                $summary['total_expense'] += $project_sale['paid_amount'];
            }

                $summary['total_balance'] = $summary['total_amount'] - $summary['total_expense'];

            // $expenses['summary'] = $summary;

            $response = $this->respond([
                'project_sales' => $project_sales,
                'summary' => $summary,
                'status'  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get receivables
     */
    public function get_receive_payables()
    {
        if (($response = $this->_api_verification('reports', 'get_receivable')) !== true)
            return $response;

        $invoice_no  = $this->request->getVar('invoice_no') ? : null;
        $supplier_id = $this->request->getVar('supplier_id') ? : null;
        $vendor_id   = $this->request->getVar('vendor_id') ? : null;
        $date_from   = $this->request->getVar('date_from') ? : null;
        $date_to     = $this->request->getVar('date_to') ? : null;
        $payable     = $this->request->getVar('payable') ? : null;
        $paid        = $this->request->getVar('paid') ? : null;

        $receivables = $this->reportModel->get_receive_payables($invoice_no, $supplier_id, $vendor_id, $date_from, $date_to, $payable, $paid);

        if (!$receivables) {
            $response = $this->failNotFound('No report Found');
        } else {
            $summary = [
                'grand_total' => 0,
                'total_paid'    => 0,
                'total_balance' => 0,
                'total_receieve_payable' => 0,
                'total_supplies_receive_payable' => 0
            ];

            foreach ($receivables as $receivable) {
                $summary['grand_total'] += $receivable['amount'];
                $summary['total_paid']    += $receivable['paid_amount'];
                $summary['total_balance'] += $receivable['balance'];
                if ($receivable['type'] == 'receive')
                    $summary['total_receieve_payable'] += $receivable['amount'];
                elseif ($receivable['type'] == 'supplies_receive')
                    $summary['total_supplies_receive_payable'] += $receivable['amount'];
            }
        
            $response = $this->respond([
                'summary'     => $summary,
                'receivables' => $receivables,
                'status'      => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get receivables
     */
    public function get_franchisee_sales()
    {
        if (($response = $this->_api_verification('reports', 'get_franchisee_sales_report')) !== true)
            return $response;

        $franchisee_id    = $this->request->getVar('franchisee_id');
        $date_from        = $this->request->getVar('date_from') ? : null;
        $date_to          = $this->request->getVar('date_to') ? : null;
        $buyer_branch_id  = $this->request->getVar('buyer_branch_id') ? : null;
        $seller_branch_id = $this->request->getVar('seller_branch_id') ? : null;
        $payment_status   = $this->request->getVar('payment_status') ? : null;

        if (!$franchisee_sales_report = $this->reportModel->get_franchisee_sales($franchisee_id, $date_from, $date_to, $buyer_branch_id, $seller_branch_id, $payment_status)) {
            $response = $this->failNotFound('No report Found');
        } else {
            $response = $this->respond([
                'franchisee_sales_report' => $franchisee_sales_report,
                'status'                  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get payables aging
     */
    public function get_payables_aging()
    {
        if (($response = $this->_api_verification('reports', 'get_payables_aging')) !== true)
            return $response;

        $supplier_id    = $this->request->getVar('supplier_id');
        $expense_type   = $this->request->getVar('expense_type') ? : null;

        if (!$payables_aging = $this->reportModel->get_payables_aging($supplier_id, $expense_type)) {
            $response = $this->failNotFound('No report Found');
        } else {
            $general_summary = [
                'total_payables' => 0,
                'total_paid' => 0,
            ];
            foreach ($payables_aging as $key => $value) {
                $general_summary['total_payables'] += $value['total'];
                $general_summary['total_paid'] += $value['total_paid'];
            }

            $response = $this->respond([
                'summary' => $general_summary,
                'payables_aging' => $payables_aging,
                'status'                  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get statement of account
     */
    public function get_statement_of_account()
    {
        if (($response = $this->_api_verification('reports', 'get_statement_of_account')) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');
        $where = [
            'id' => $customer_id,
            'is_deleted' => 0
        ];
        $customer = $this->customerModel->select('', $where, 1);

        if (!$payables_aging = $this->reportModel->get_statement_of_account($customer_id)) {
            $response = $this->failNotFound('No report Found');
        } else {
            $general_summary = [
                'total_amount' => 0,
                'total_paid' => 0,
                'total_balance' => 0,
            ];
            foreach ($payables_aging as $key => $value) {
                $general_summary['total_amount'] += $value['total_amount'];
                $general_summary['total_paid'] += $value['total_paid'];
                $general_summary['total_balance'] += $value['total_amount'] - $value['total_paid'];
            }

            $response = $this->respond([
                'customer' => $customer,
                'summary' => $general_summary,
                'payables_aging' => $payables_aging,
                'status'                  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get receivables aging
     */
    public function get_receivables_aging()
    {
        if (($response = $this->_api_verification('reports', 'get_receivables_aging')) !== true)
            return $response;

        $customer_id    = $this->request->getVar('customer_id');
        $project_id   = $this->request->getVar('project_id') ? : null;

        if (!$receivables_aging = $this->reportModel->get_receivables_aging($customer_id, $project_id)) {
            $response = $this->failNotFound('No report Found');
        } else {
            $general_summary = [
                'total_receivables' => 0,
                'total_paid' => 0,
            ];
            foreach ($receivables_aging as $key => $value) {
                $general_summary['total_receivables'] += $value['total'];
                $general_summary['total_paid'] += $value['total_paid'];
            }

            $response = $this->respond([
                'summary' => $general_summary,
                'receivables_aging' => $receivables_aging,
                'status'                  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get Franchised Branch Payments
     */
    public function get_franchisee_branch_payments()
    {
        if (($response = $this->_api_verification('reports', 'get_franchisee_branch_payments')) !== true)
            return $response;

        // $franchisee_id, $date_from, $date_to, $branch_id, $payment_type
        $franchisee_id    = $this->request->getVar('franchisee_id');
        $date_from        = $this->request->getVar('date_from') ? : null;
        $date_to          = $this->request->getVar('date_to') ? : null;
        $branch_id        = $this->request->getVar('branch_id') ? : null;
        $payment_type     = $this->request->getVar('payment_type') ? : null;

        if (!$franchisee_branch_payments = $this->reportModel->get_franchisee_branch_payments($franchisee_id, $date_from, $date_to, $branch_id, $payment_type)) {
            $response = $this->failNotFound('No report Found');
        } else {
            $response = $this->respond([
                'franchisee_branch_payments' => $franchisee_branch_payments,
                'status'                     => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all the item tranfers transaction based on data and item id
     * Use by ERP for reporting
     */
    public function get_item_transfer_transactions()
    {
        if (($response = $this->_api_verification('reports', 'get_item_transfer_transactions')) !== true)
            return $response;

        $transfer_id        = $this->request->getVar('transfer_id');
        $branch_from        = $this->request->getVar('branch_from');
        $branch_to          = $this->request->getVar('branch_to');
        $transfer_number    = $this->request->getVar('transfer_number');
        $transfer_date_to   = $this->request->getVar('transfer_date_to');
        $transfer_date_from = $this->request->getVar('transfer_date_from');
        $remarks            = $this->request->getVar('remarks');
        $grand_total        = $this->request->getVar('grand_total');
        $status             = $this->request->getVar('status');
        $limit_by           = $this->request->getVar('limit_by');

        if (!$transfers = $this->transferModel->search($transfer_id, $branch_from, $branch_to, $transfer_number, $transfer_date_to, $transfer_date_from, $remarks, $grand_total, $status, $limit_by)) {
            $response = $this->failNotFound('No transfer found');
        } else {
            $transfer_report = [];
            foreach ($transfers as $key => $transfer) {
                $transfer_items = $this->transferItemModel->get_details_by_transfer_id($transfer['id']);
                
                // Check if the branch from is already in the array
                if (array_key_exists($transfer['branch_from_name'], $transfer_report)) {
                    $transfer_report[$transfer['branch_from_name']]['number_of_transfers']++;
                    $transfer_report[$transfer['branch_from_name']]['total_amount'] += $transfer['grand_total'];
                } else {
                    $transfer_report[$transfer['branch_from_name']] = [
                        'branch_from_name' => $transfer['branch_from_name'],
                        'number_of_transfers' => 1,
                        'total_amount' => $transfer['grand_total'],
                        'items' => []
                    ];
                }

                 // inserting the items
                foreach ($transfer_items as $key => $item) {
                    // check if the item is already in the array
                    if (array_key_exists($item['item_name'], $transfer_report[$transfer['branch_from_name']]['items'])) {
                        $transfer_report[$transfer['branch_from_name']]['items'][$item['item_name']]['quantity'] += $item['qty'];
                        $transfer_report[$transfer['branch_from_name']]['items'][$item['item_name']]['total_amount'] += $item['total'];
                    } else {
                        $transfer_report[$transfer['branch_from_name']]['items'][$item['item_name']] = [
                            'item_name' => $item['item_name'],
                            'quantity' => $item['qty'],
                            'total_amount' => $item['total']
                        ];
                    }
                }

                $transfer_report[$transfer['branch_from_name']]['items'] = array_values($transfer_report[$transfer['branch_from_name']]['items']);
            }

            
            
            $response = $this->respond([
                'data' => $transfer_report
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get dashboard reports
     */
    public function get_dashboard_reports()
    {
        if (($response = $this->_api_verification('reports', 'get_dashboard_reports')) !== true)
            return $response;
        
        if (!$reports = $this->reportModel->get_dash_reports()) {
            $response = $this->failNotFound('No report Found');
        } else {
            $for_approval_adjustments = $this->reportModel->get_adjustments_for_approval();
            $report_for_approval_adjustments = [];
            foreach ($for_approval_adjustments AS $for_approval_adjustment) {
                // check if the index exists
                $user_type = $for_approval_adjustment['user_type'];
                $user_type = $user_type == 'branch' ? 'branch' : 'office';
                $index = $for_approval_adjustment['branch_name'] . '-' . $user_type;
                if (!isset($report_for_approval_adjustments[$index])) {
                    $report_for_approval_adjustments[$index] = [
                        'count' => 0,
                        'branch_name' => $for_approval_adjustment['branch_name'],
                        'account_type' => $user_type
                    ];
                }
                $report_for_approval_adjustments[$index]['count'] += $for_approval_adjustment['count'];
            }

            $low_stocks_per_branch          = $this->reportModel->get_low_stocks_per_branch();
            $all_pending_requests           = $this->reportModel->get_all_pending_requests();
            $all_unprocess_franchisee_sales = $this->reportModel->get_all_unprocess_franchisee_sales();
            $all_for_approval_transfer      = $this->reportModel->get_all_for_approval_transfers();
            
            $reports['for_approval_adjustments'] = $report_for_approval_adjustments;
            $reports['low_stocks_per_branch'] = $low_stocks_per_branch;
            $reports['all_pending_requests'] = $all_pending_requests;
            $reports['all_unprocess_franchisee_sales'] = $all_unprocess_franchisee_sales;
            $reports['all_for_approval_transfer'] = $all_for_approval_transfer;
            $response = $this->respond([
                'dashboard_reports' => $reports,
                'status'            => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /*
    * List of expired contracts
    */
    public function get_expired_contracts()
    {
        if (($response = $this->_api_verification('reports', 'get_expired_contracts')) !== true)
            return $response;
        
        if (!$expired_contracts = $this->reportModel->get_expired_contracts()) {
            $response = $this->failNotFound('No expired contracts found');
        } else {
            $response = $this->respond([
                'expired_contracts' => $expired_contracts,
                'status'            => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /*
    * Get Franchisee sales item's amount and quantity based on date
    */
    public function get_franchisee_sale_item_report()
    {
        if (($response = $this->_api_verification('reports', 'get_franchisee_sale_item_report')) !== true)
            return $response;

        $franchisee_name = $this->request->getVar('franchisee_name');
        $item_id         = $this->request->getVar('item_id');
        $sales_date_from = $this->request->getVar('sales_date_from');
        $sales_date_to   = $this->request->getVar('sales_date_to');

        if (!$franchisee_sale_items = $this->franchiseeSaleModel->search_franchisee_sale_item($franchisee_name, $item_id, $sales_date_from, $sales_date_to)) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            $general_summary = [
                'total_amount' => 0,
                'total_quantity' => 0,
                'average_price' => 0
            ];
            $total_price = 0;
            foreach ($franchisee_sale_items as $key => $value) {
                $general_summary['total_amount'] += $value['total_subtotal'];
                $general_summary['total_quantity'] += $value['total_quantity'];
                $total_price += $value['average_price'];
            }

            $general_summary['average_price'] = $total_price / count($franchisee_sale_items);

            $response = $this->respond([
                'general_summary' => $general_summary,
                'summary'         => $franchisee_sale_items,
                'status'          => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /*
    * Get summary of purchased items
    */
    public function get_summary_of_purchased_items_from_suppliers()
    {
        if (($response = $this->_api_verification('reports', 'get_summary_of_purchased_items_from_suppliers')) !== true)
            return $response;

        $item_id            = $this->request->getVar('item_id') ?? null;
        $item_name          = $this->request->getVar('item_name') ?? null;
        $purchase_date_from = $this->request->getVar('purchase_date_from') ?? null;
        $purchase_date_to   = $this->request->getVar('purchase_date_to') ?? null;
        $receive_date_from  = $this->request->getVar('receive_date_from') ?? null;
        $receive_date_to    = $this->request->getVar('receive_date_to') ?? null;

        if ($filtered_receive_items = $this->receiveModel->get_summary_of_purchased_item($item_id, $item_name, $purchase_date_from, $purchase_date_to, $receive_date_from, $receive_date_to)) {
            $summary = [
                'total_quantity' => 0,
                'total_amount'   => 0,
            ];

            foreach ($filtered_receive_items as $key => $filtered_receive_item) {
                $summary['total_quantity'] += $filtered_receive_item['quantity'];
                $summary['total_amount'] += $filtered_receive_item['total_amount'];
            }
            
            $response = $this->respond([
                'summary'        => $summary,
                'purchase_items' => $filtered_receive_items,
                'status'         => 'success',
            ]);
        } else {
            $response = $this->failNotFound('No filtered_receive_items found');
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /*
    * Get summary of transferred items
    */
    public function get_summary_of_received_transfer_items()
    {
        if (($response = $this->_api_verification('reports', 'get_summary_of_transferred_items')) !== true)
            return $response;

        $item_id            = $this->request->getVar('item_id') ?? null;
        $item_name          = $this->request->getVar('item_name') ?? null;
        $transfer_date_from = $this->request->getVar('transfer_date_from') ?? null;
        $transfer_date_to   = $this->request->getVar('transfer_date_to') ?? null;
        $branch_from_id     = $this->request->getVar('branch_from_id') ?? null;
        $branch_to_id       = $this->request->getVar('branch_to_id') ?? null;

        if ($filtered_transfer_items = $this->transferModel->get_received_items($item_id, $item_name, $transfer_date_from, $transfer_date_to, $branch_from_id, $branch_to_id)) {
            $summary = [
                'total_quantity' => 0,
                'total_received' => 0,
                'total_amount'   => 0,
                'total_transfer_count' => 0,
            ];

            foreach ($filtered_transfer_items as $key => $filtered_transfer_item) {
                $summary['total_quantity']       += $filtered_transfer_item['quantity'];
                $summary['total_received']       += $filtered_transfer_item['received_qty'];
                $summary['total_amount']         += $filtered_transfer_item['total_amount'];
                $summary['total_transfer_count'] += $filtered_transfer_item['no_of_transfer'];
            }
            
            $response = $this->respond([
                'summary'        => $summary,
                'transfer_items' => $filtered_transfer_items,
                'status'         => 'success',
            ]);
        } else {
            $response = $this->failNotFound('No filtered_transfer_items found');
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->reportModel                = model('App\Models\Report');
        $this->customerModel                = model('App\Models\Customer');
        $this->branchModel                = model('App\Models\Branch');
        $this->transferModel              = model('App\Models\Transfer');
        $this->transferItemModel          = model('App\Models\Transfer_item');
        $this->franchiseeSaleModel        = model('App\Models\Franchisee_sale');
        $this->franchiseeSaleItemModel    = model('App\Models\Franchisee_sale_item');
        $this->franchiseeSalePaymentModel = model('App\Models\Franchisee_sale_payment');
        $this->fsBillingPaymentModel      = model('App\Models\Fs_billing_payment');
        $this->purchaseItemModel          = model('App\Models\Purchase_item');
        $this->receiveModel               = model('App\Models\Receive');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}