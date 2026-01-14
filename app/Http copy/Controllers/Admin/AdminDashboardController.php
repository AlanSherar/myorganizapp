<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AdminDashboardController extends Controller
{

    public function __construct()
    {
    }

    public function dashboard()
    {
        return view('admin.dashboard');
    }

}
