<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AuditController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $page = max(1, $this->intParam('page'));
        $total = (int) $this->db->value('SELECT COUNT(*) FROM audit_log');
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;
        $entries = $this->db->all(
            'SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
           ORDER BY a.id DESC LIMIT ' . self::PER_PAGE . " OFFSET $offset"
        );
        $this->render('audit/index', compact('entries', 'page', 'pages', 'total'));
    }
}
