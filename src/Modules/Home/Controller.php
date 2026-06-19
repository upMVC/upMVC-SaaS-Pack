<?php

namespace App\Modules\Home;

class Controller
{
    public function display(string $reqRoute, string $reqMet): void
    {
        $view = new View();
        $view->landing();
    }
}
