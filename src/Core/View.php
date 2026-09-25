<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $content = self::capture($view, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture($layout, ['content' => $content] + self::$exported + $data);
    }

    /** Variables que una vista puede definir para el layout ($title, $scripts). */
    private static array $exported = [];

    public static function capture(string $view, array $data = []): string
    {
        $file = BASE_PATH . '/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista no encontrada: $view");
        }
        global $__old;
        if (!isset($__old)) {
            $__old = Session::pullOld();
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        self::$exported = array_intersect_key(get_defined_vars(), ['title' => 1, 'scripts' => 1]);
        return (string) ob_get_clean();
    }
}
