<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Container for toasts. Controllers flash one with
 * redirect()->with('toast', ['type' => 'success', 'message' => '...']).
 */
class ToastRegion extends Component
{
    private const TYPES = ['success', 'error', 'info'];

    /**
     * @return list<array{type: string, message: string}>
     */
    public function flashed(): array
    {
        $flash = session('toast');

        if (! is_array($flash) || ! is_string($flash['message'] ?? null)) {
            return [];
        }

        $type = is_string($flash['type'] ?? null) && in_array($flash['type'], self::TYPES, true)
            ? $flash['type']
            : 'info';

        return [['type' => $type, 'message' => $flash['message']]];
    }

    public function render(): View
    {
        return view('components.toast-region');
    }
}
