<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class HealthController
{
    public function __construct(private readonly Database $db)
    {
    }

    public function index(Request $request): Response
    {
        try {
            $this->db->fetchOne('SELECT 1');
            $up = true;
        } catch (\PDOException) {
            $up = false;
        }

        return Response::json(
            ['status' => $up ? 'ok' : 'degraded', 'database' => $up ? 'up' : 'down'],
            $up ? 200 : 503,
        );
    }
}
