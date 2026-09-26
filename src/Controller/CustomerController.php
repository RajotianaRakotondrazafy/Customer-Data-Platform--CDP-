<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Service\CustomerProfileService;

final class CustomerController
{
    public function __construct(private readonly CustomerProfileService $profiles)
    {
    }

    /** GET /api/customers/{id} */
    public function show(Request $request): Response
    {
        $profile = $this->profiles->get((int) $request->route('id'));
        if ($profile === null) {
            throw new HttpException(404, 'Customer not found.');
        }

        return Response::json(['data' => $profile]);
    }
}
