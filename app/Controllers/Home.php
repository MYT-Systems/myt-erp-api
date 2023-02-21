<?php

namespace App\Controllers;

// DOCKER TEST

class Home extends BaseController
{
    public function index()
    {
        return view('welcome_message');
    }
}
