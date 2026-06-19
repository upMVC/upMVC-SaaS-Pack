<?php

namespace App\Modules\TenantShop;

class Controller
{
    public function display(string $reqRoute, string $_reqMet): void
    {
        $parts = explode('/', trim($reqRoute, '/'));
        $slug  = $parts[1] ?? '';

        if ($slug === '') {
            header('Location: ' . BASE_URL);
            exit;
        }

        (new View())->storefront($slug);
    }
}
