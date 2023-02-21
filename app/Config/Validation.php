<?php

namespace Config;

use CodeIgniter\Validation\CreditCardRules;
use CodeIgniter\Validation\FileRules;
use CodeIgniter\Validation\FormatRules;
use CodeIgniter\Validation\Rules;

class Validation
{
    //--------------------------------------------------------------------
    // Setup
    //--------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var string[]
     */
    public $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // Supplier create
    public $supplier = [
        // 'bir_name' => [
        //     'label' => 'BIR Name',
        //     'rules'  => 'required',
        // ],
        'trade_name' => [
            'label' => 'Trade Name',
            'rules'  => 'required',
        ]
    ];

    // Banks create
    public $bank = [
        'name' => [
            'label' => 'Name',
            'rules'  => 'required',
        ],
    ];

    // Branch create
    public $branch = [
        'name' => [
            'label' => 'Branch Name',
            'rules'  => 'required',
        ],
        'address' => [
            'label' => 'Branch Address',
            'rules'  => 'required',
        ],
        'contact_person_no' => [
            'label' => 'Phone No',
            'rules'  => 'required',
        ],
        'initial_drawer' => [
            'label' => 'Initial Cash in Drawer',
            'rules'  => 'required',
        ],
    ];

    // Items create
    public $item = [
        'name' => [
            'label' => 'Name',
            'rules'  => 'required',
        ]
    ];

    // Product create
    public $product = [
        'name' => [
            'label' => 'Name',
            'rules'  => 'required',
        ],
    ];

    // User create
    public $user = [
        'first_name' => [
            'label' => 'First Name',
            'rules'  => 'required',
        ],
        'last_name' => [
            'label' => 'Last Name',
            'rules'  => 'required',
        ],
        'username' => [
            'label' => 'Username',
            'rules' => 'required'
        ],
        'email' => [
            'label' => 'Email',
            'rules' => 'required'
        ],
        'password' => [
            'label' => 'Password',
            'rules' => 'required'
        ]
    ];

    public $order = [
    ];

    public $purchase = [
        'supplier_id' => [
            'label' => 'Supplier ID',
            'rules'  => 'required',
        ],
        'vendor_id' => [
            'label' => 'Vendor ID',
            'rules'  => 'required',
        ]
    ];
}
