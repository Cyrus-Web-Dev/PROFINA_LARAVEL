<?php

namespace App\Http\Controllers;

/**
 * Extend Illuminate\Routing\Controller supaya $this->middleware(...) di constructor controller tetap berfungsi.
 */
abstract class Controller extends \Illuminate\Routing\Controller
{
}
