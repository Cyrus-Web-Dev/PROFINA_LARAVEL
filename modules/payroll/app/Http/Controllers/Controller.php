<?php

namespace App\Http\Controllers;

/**
 * Extend Illuminate\Routing\Controller (bukan class kosong) supaya helper klasik
 * $this->middleware('permission:xxx')->only([...]) di constructor controller tetap berfungsi.
 */
abstract class Controller extends \Illuminate\Routing\Controller
{
    //
}
